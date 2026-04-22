<?php
/**
 * api/payment_initiate.php – Initiation d'un paiement via Senfenico
 * 
 * POST /api/payment_initiate.php
 * Headers : Authorization: Bearer <token>
 * Body JSON : { "plan_type": "monthly" }
 * 
 * Crée un checkout Senfenico et retourne l'authorization_url
 * que Flutter ouvrira dans une WebView.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée.'], 405);
}

$authPayload = requireAuth();
$user        = getAuthUser($authPayload);
$userId      = (int) $user['id'];

$input    = json_decode(file_get_contents('php://input'), true);
$planType = trim($input['plan_type'] ?? 'monthly');

// Plans disponibles
$plans = [
    'monthly' => ['amount' => 2800, 'label' => 'Abonnement mensuel – Grand Frère Intelligent'],
];

if (!isset($plans[$planType])) {
    jsonResponse(['error' => 'Plan d\'abonnement invalide.'], 400);
}

$plan   = $plans[$planType];
$amount = $plan['amount'];

$senfenicoApiUrl = rtrim($_ENV['SENFENICO_API_URL'] ?? 'https://api.senfenico.com/v1', '/');
$secretKey       = $_ENV['SENFENICO_SECRET_KEY']   ?? '';
$successUrl      = $_ENV['SENFENICO_SUCCESS_URL']  ?? '';
$cancelUrl       = $_ENV['SENFENICO_CANCEL_URL']   ?? '';
$webhookUrl      = ($_ENV['APP_URL'] ?? '') . '/payment_webhook.php';

if (empty($secretKey)) {
    logError('SENFENICO_SECRET_KEY non configuré dans .env');
    jsonResponse(['error' => 'Configuration de paiement incomplète.'], 500);
}

try {
    $pdo = getDB();
    
    // Créer un enregistrement de paiement en statut pending
    $stmt = $pdo->prepare("
        INSERT INTO payments (user_id, amount, plan_type, status, created_at)
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$userId, $amount, $planType]);
    $paymentId = $pdo->lastInsertId();
    
    // Appel à l'API Senfenico
    $checkoutData = [
        'amount'      => $amount,
        'currency'    => 'XOF',
        'description' => $plan['label'],
        'customer'    => [
            'email' => $user['email'],
            'name'  => $user['full_name'] ?? $user['email'],
        ],
        'metadata'    => [
            'payment_id' => $paymentId,
            'user_id'    => $userId,
            'plan_type'  => $planType,
        ],
        'success_url' => $successUrl,
        'cancel_url'  => $cancelUrl,
        'webhook_url' => $webhookUrl,
    ];
    
    $ch = curl_init($senfenicoApiUrl . '/checkouts');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($checkoutData),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    
    if ($curlErr) {
        logError("Senfenico cURL error: {$curlErr}");
        jsonResponse(['error' => 'Erreur de connexion au service de paiement.'], 500);
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        logError("Senfenico HTTP {$httpCode}: {$response}");
        $errMsg = $responseData['message'] ?? 'Erreur du service de paiement.';
        jsonResponse(['error' => $errMsg], 500);
    }
    
    $authorizationUrl  = $responseData['authorization_url'] ?? $responseData['payment_url'] ?? null;
    $senfenicoRef      = $responseData['reference']         ?? $responseData['id']          ?? null;
    
    if (empty($authorizationUrl)) {
        logError('Senfenico: pas d\'authorization_url dans la réponse: ' . $response);
        jsonResponse(['error' => 'Impossible d\'initier le paiement. Réessayez.'], 500);
    }
    
    // Mettre à jour la référence Senfenico dans le paiement
    $pdo->prepare("UPDATE payments SET senfenico_reference = ? WHERE id = ?")
        ->execute([$senfenicoRef, $paymentId]);
    
    jsonResponse([
        'success'           => true,
        'authorization_url' => $authorizationUrl,
        'payment_id'        => $paymentId,
        'amount'            => $amount,
        'currency'          => 'XOF',
        'plan_type'         => $planType,
    ]);
    
} catch (PDOException $e) {
    logError('Erreur payment_initiate: ' . $e->getMessage());
    jsonResponse(['error' => 'Erreur serveur lors de l\'initiation du paiement.'], 500);
}
