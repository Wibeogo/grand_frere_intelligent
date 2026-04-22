<?php
/**
 * api/chat_send.php – Envoi d'un message texte au "Grand Frère"
 * 
 * POST /api/chat_send.php
 * Headers : Authorization: Bearer <token>
 * Body JSON : { "message": "ta question", "concours": "police" }
 * 
 * - Vérifie l'accès premium/essai
 * - Applique le rate limiting
 * - Appelle l'IA Mistral via Colab
 * - Sauvegarde la conversation
 * - Retourne la réponse de l'IA
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ia_helper.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/concours_data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée.'], 405);
}

// Authentification et vérification premium
$authPayload = requireAuth();
$user        = requirePremiumOrTrial($authPayload);
$userId      = (int) $user['id'];

// Rate limiting
checkRateLimit($userId, $user['user_type'], 'chat_send');

// Lire l'input
$input   = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$concours = trim($input['concours'] ?? $user['concours_cible'] ?? '');

if (empty($message)) {
    jsonResponse(['error' => 'Le message ne peut pas être vide.'], 400);
}

if (strlen($message) > 5000) {
    jsonResponse(['error' => 'Le message est trop long (maximum 5000 caractères).'], 400);
}

try {
    $pdo = getDB();
    
    // Récupérer le contexte de conversation (5 derniers échanges)
    $stmtHistory = $pdo->prepare("
        SELECT message, is_user FROM conversations
        WHERE user_id = ?
        ORDER BY created_at DESC LIMIT 10
    ");
    $stmtHistory->execute([$userId]);
    $history = array_reverse($stmtHistory->fetchAll());
    
    // Construire le prompt complet
    $concoursContext = !empty($concours) ? buildConcoursContext($concours) . "\n\n" : '';
    
    $historyText = '';
    foreach ($history as $h) {
        $role = $h['is_user'] ? 'Étudiant' : 'Grand Frère';
        $historyText .= "{$role}: {$h['message']}\n";
    }
    
    $fullPrompt = $concoursContext;
    if (!empty($historyText)) {
        $fullPrompt .= "HISTORIQUE DE LA CONVERSATION :\n{$historyText}\n\n";
    }
    $fullPrompt .= "Étudiant: {$message}\nGrand Frère:";
    
    // Appel à l'IA
    $iaResponse = call_ia($fullPrompt);
    
    // Sauvegarder le message de l'utilisateur
    $stmtSave = $pdo->prepare("
        INSERT INTO conversations (user_id, message, is_user, created_at)
        VALUES (?, ?, 1, NOW())
    ");
    $stmtSave->execute([$userId, $message]);
    
    // Sauvegarder la réponse de l'IA
    $stmtSave->execute([$userId, $iaResponse]);
    // is_user = 0 pour la réponse IA
    $pdo->prepare("
        UPDATE conversations SET is_user = 0
        WHERE user_id = ? AND is_user = 1
        ORDER BY id DESC LIMIT 1
    ")->execute([$userId]);
    // Correction : two separate inserts
    $stmtIA = $pdo->prepare("
        INSERT INTO conversations (user_id, message, is_user, created_at)
        VALUES (?, ?, 0, NOW())
    ");
    $stmtIA->execute([$userId, $iaResponse]);
    
    jsonResponse([
        'success'      => true,
        'response'     => $iaResponse,
        'timestamp'    => date('Y-m-d H:i:s'),
    ]);
    
} catch (PDOException $e) {
    logError('Erreur chat_send: ' . $e->getMessage());
    jsonResponse(['error' => 'Erreur serveur lors de l\'envoi du message.'], 500);
}
