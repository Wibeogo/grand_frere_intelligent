<?php
/**
 * api/chat_upload_image.php – Correction de photos par l'IA (LLaVA)
 * 
 * POST /api/chat_upload_image.php (multipart/form-data)
 * Headers : Authorization: Bearer <token>
 * Form : image (fichier), prompt (optionnel)
 * 
 * - Reçoit une image (sujet ou réponse manuscrite)
 * - La convertit en base64
 * - Appelle LLaVA via Colab pour correction
 * - Sauvegarde la réponse dans la conversation
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ia_helper.php';
require_once __DIR__ . '/../includes/rate_limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée.'], 405);
}

$authPayload = requireAuth();
$user        = requirePremiumOrTrial($authPayload);
$userId      = (int) $user['id'];

checkRateLimit($userId, $user['user_type'], 'chat_upload_image');

// Vérifier qu'un fichier a été envoyé
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = match ($_FILES['image']['error'] ?? -1) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'L\'image est trop volumineuse (max 5 Mo).',
        UPLOAD_ERR_NO_FILE  => 'Aucune image reçue.',
        default             => 'Erreur lors de l\'upload de l\'image.',
    };
    jsonResponse(['error' => $errorMsg], 400);
}

$file     = $_FILES['image'];
$maxSize  = (int)($_ENV['MAX_FILE_SIZE'] ?? 5242880); // 5 Mo
$allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Valider la taille
if ($file['size'] > $maxSize) {
    jsonResponse(['error' => 'L\'image dépasse la taille maximale de 5 Mo.'], 400);
}

// Valider le type MIME réel (pas juste l'extension)
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, $allowed)) {
    jsonResponse(['error' => 'Type de fichier non autorisé. Utilisez JPG, PNG ou WebP.'], 400);
}

// Prompt utilisateur (optionnel)
$customPrompt = trim($_POST['prompt'] ?? '');

try {
    // Convertir l'image en base64
    $imageData   = file_get_contents($file['tmp_name']);
    $imageBase64 = base64_encode($imageData);
    
    // Sauvegarder l'image sur le serveur
    $uploadDir = rtrim($_ENV['UPLOAD_DIR'] ?? __DIR__ . '/../uploads/images/'), '/') . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0750, true);
    }
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
    $filename = uniqid('img_') . '_' . $userId . '.' . $ext;
    $filepath = $uploadDir . $filename;
    move_uploaded_file($file['tmp_name'], $filepath);
    
    $imageUrl = ($_ENV['APP_URL'] ?? '') . '/uploads/images/' . $filename;
    
    // Construire le prompt pour LLaVA
    $prompt = !empty($customPrompt)
        ? $customPrompt
        : "Analyse attentivement cette image. 
           Si c'est un sujet d'examen ou une question de concours burkinabè, extrait la question et fournis une réponse complète et détaillée.
           Si c'est une réponse manuscrite d'un étudiant, corrige-la, pointe les erreurs et explique les corrections.
           Si c'est un document ou une note de cours, résume et explique les concepts clés.
           Réponds en français avec un ton pédagogique et bienveillant.";
    
    // Appel LLaVA
    $iaResponse = call_ia($prompt, $imageBase64, 'llava:7b');
    
    // Sauvegarder dans la conversation
    $pdo = getDB();
    
    // Message utilisateur (avec image)
    $stmt = $pdo->prepare("
        INSERT INTO conversations (user_id, message, is_user, image_url, created_at)
        VALUES (?, ?, 1, ?, NOW())
    ");
    $userMsg = !empty($customPrompt) ? $customPrompt : '📷 Image envoyée pour correction';
    $stmt->execute([$userId, $userMsg, $imageUrl]);
    
    // Réponse IA
    $stmtIA = $pdo->prepare("
        INSERT INTO conversations (user_id, message, is_user, created_at)
        VALUES (?, ?, 0, NOW())
    ");
    $stmtIA->execute([$userId, $iaResponse]);
    
    jsonResponse([
        'success'    => true,
        'response'   => $iaResponse,
        'image_url'  => $imageUrl,
        'timestamp'  => date('Y-m-d H:i:s'),
    ]);
    
} catch (Exception $e) {
    logError('Erreur chat_upload_image: ' . $e->getMessage());
    jsonResponse(['error' => 'Erreur lors du traitement de l\'image.'], 500);
}
