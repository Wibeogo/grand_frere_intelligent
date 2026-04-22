<?php
/**
 * api/chat_upload_audio.php – Traitement du message vocal
 * 
 * POST /api/chat_upload_audio.php
 * Headers : Authorization: Bearer <token>
 * Body JSON : { "transcribed_text": "texte transcrit par Flutter", "concours": "police" }
 * 
 * Note : La transcription vocale est effectuée côté Flutter via speech_to_text.
 * Ce endpoint reçoit le TEXTE transcrit et le traite comme un message normal.
 * L'enregistrement audio peut optionnellement être envoyé pour archive.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ia_helper.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/concours_data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée.'], 405);
}

$authPayload = requireAuth();
$user        = requirePremiumOrTrial($authPayload);
$userId      = (int) $user['id'];

checkRateLimit($userId, $user['user_type'], 'chat_audio');

// Lire le texte transcrit
$input           = json_decode(file_get_contents('php://input'), true);
$transcribedText = trim($input['transcribed_text'] ?? '');
$concours        = trim($input['concours'] ?? $user['concours_cible'] ?? '');
$audioUrl        = null;

if (empty($transcribedText)) {
    jsonResponse(['error' => 'Le texte transcrit est vide. Veuillez réessayer l\'enregistrement.'], 400);
}

// Traitement optionnel du fichier audio (si envoyé via multipart)
if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
    $audioFile   = $_FILES['audio'];
    $maxSize     = (int)($_ENV['MAX_FILE_SIZE'] ?? 5242880);
    $allowedAudio = ['audio/mpeg', 'audio/ogg', 'audio/webm', 'audio/mp4', 'audio/wav', 'audio/x-m4a'];
    
    if ($audioFile['size'] <= $maxSize) {
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($audioFile['tmp_name']);
        
        if (in_array($mimeType, $allowedAudio)) {
            $uploadDir = rtrim($_ENV['UPLOAD_DIR'] ?? __DIR__ . '/../uploads/audio/'), '/') . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0750, true);
            }
            $filename = uniqid('audio_') . '_' . $userId . '.opus';
            move_uploaded_file($audioFile['tmp_name'], $uploadDir . $filename);
            $audioUrl = ($_ENV['APP_URL'] ?? '') . '/uploads/audio/' . $filename;
        }
    }
}

try {
    $pdo = getDB();
    
    // Récupérer l'historique récent
    $stmtHistory = $pdo->prepare("
        SELECT message, is_user FROM conversations
        WHERE user_id = ?
        ORDER BY created_at DESC LIMIT 8
    ");
    $stmtHistory->execute([$userId]);
    $history = array_reverse($stmtHistory->fetchAll());
    
    // Construire le prompt avec contexte
    $concoursContext = !empty($concours) ? buildConcoursContext($concours) . "\n\n" : '';
    
    $historyText = '';
    foreach ($history as $h) {
        $role        = $h['is_user'] ? 'Étudiant' : 'Grand Frère';
        $historyText .= "{$role}: {$h['message']}\n";
    }
    
    $fullPrompt  = $concoursContext;
    if (!empty($historyText)) {
        $fullPrompt .= "HISTORIQUE :\n{$historyText}\n\n";
    }
    $fullPrompt .= "Note: L'étudiant a posé cette question oralement (message vocal).\n";
    $fullPrompt .= "Étudiant (vocal): {$transcribedText}\nGrand Frère:";
    
    // Appel IA
    $iaResponse = call_ia($fullPrompt);
    
    // Sauvegarder la conversation
    $stmt = $pdo->prepare("
        INSERT INTO conversations (user_id, message, is_user, audio_url, created_at)
        VALUES (?, ?, 1, ?, NOW())
    ");
    $stmt->execute([$userId, "🎤 " . $transcribedText, $audioUrl]);
    
    $stmtIA = $pdo->prepare("
        INSERT INTO conversations (user_id, message, is_user, created_at)
        VALUES (?, ?, 0, NOW())
    ");
    $stmtIA->execute([$userId, $iaResponse]);
    
    jsonResponse([
        'success'          => true,
        'transcribed_text' => $transcribedText,
        'response'         => $iaResponse,
        'audio_url'        => $audioUrl,
        'timestamp'        => date('Y-m-d H:i:s'),
    ]);
    
} catch (Exception $e) {
    logError('Erreur chat_upload_audio: ' . $e->getMessage());
    jsonResponse(['error' => 'Erreur lors du traitement du message vocal.'], 500);
}
