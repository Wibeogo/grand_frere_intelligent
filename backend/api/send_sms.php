<?php
/**
 * api/send_sms.php – Envoi de SMS via l'API Orange Burkina Faso
 * 
 * Utilise l'API Orange SMS Messaging v1 (OAuth2 Client Credentials).
 * Documentation : https://developer.orange.com/apis/sms-bf/
 * 
 * Ce fichier contient la fonction send_sms() utilisable depuis d'autres scripts.
 * Il peut aussi être appelé directement en POST pour un envoi ponctuel.
 */

require_once __DIR__ . '/../includes/db.php';

/**
 * Envoie un SMS via l'API Orange Burkina Faso.
 * 
 * @param string $phone   Numéro destinataire (ex: +22670123456 ou 70123456)
 * @param string $message Contenu du SMS (max 160 caractères recommandé)
 * @return array ['success' => bool, 'message' => string, 'data' => array]
 */
function send_sms(string $phone, string $message): array {
    // --- 1. Formater le numéro de téléphone ---
    $phone = preg_replace('/\D/', '', $phone);
    
    // Ajouter l'indicatif Burkina Faso si absent
    if (strlen($phone) === 8) {
        $phone = '226' . $phone;
    }
    $formattedPhone = 'tel:+' . $phone;
    
    // --- 2. Obtenir le token OAuth2 Orange ---
    $accessToken = getOrangeAccessToken();
    
    if (!$accessToken) {
        logError("SMS: Impossible d'obtenir le token Orange pour {$phone}");
        return [
            'success' => false,
            'message' => 'Erreur d\'authentification avec Orange.',
        ];
    }
    
    // --- 3. Envoyer le SMS ---
    $senderAddress = urlencode($_ENV['ORANGE_SMS_SENDER'] ?? 'tel:+22601000000');
    $sendUrl       = rtrim($_ENV['ORANGE_SMS_SEND_URL'], '/') . '/' . $senderAddress . '/requests';
    
    $body = json_encode([
        'outboundSMSMessageRequest' => [
            'address'              => [$formattedPhone],
            'senderAddress'        => $_ENV['ORANGE_SMS_SENDER'] ?? 'tel:+22601000000',
            'outboundSMSTextMessage' => [
                'message' => $message,
            ],
            'senderName'           => 'Grand Frère',
        ],
    ]);
    
    $ch = curl_init($sendUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError= curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        logError("SMS cURL error pour {$phone}: {$curlError}");
        return ['success' => false, 'message' => 'Erreur réseau lors de l\'envoi du SMS.'];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 201 && isset($responseData['outboundSMSMessageRequest'])) {
        return [
            'success' => true,
            'message' => 'SMS envoyé avec succès.',
            'data'    => $responseData,
        ];
    }
    
    // Gestion des erreurs spécifiques Orange
    $errorMessage = getOrangeErrorMessage($httpCode, $responseData);
    logError("SMS Orange erreur HTTP {$httpCode} pour {$phone}: " . json_encode($responseData));
    
    return [
        'success' => false,
        'message' => $errorMessage,
        'http_code' => $httpCode,
    ];
}

/**
 * Obtient un token d'accès OAuth2 depuis Orange.
 * Utilise un cache simple en fichier pour éviter de redemander trop souvent.
 */
function getOrangeAccessToken(): ?string {
    // Cache du token (valide 50 minutes, Orange donne 60 minutes)
    $cacheFile = sys_get_temp_dir() . '/orange_token_bf.json';
    
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached && isset($cached['expires_at']) && $cached['expires_at'] > time()) {
            return $cached['access_token'];
        }
    }
    
    // Demander un nouveau token
    $clientId     = $_ENV['ORANGE_SMS_CLIENT_ID']     ?? '';
    $clientSecret = $_ENV['ORANGE_SMS_CLIENT_SECRET'] ?? '';
    $tokenUrl     = $_ENV['ORANGE_SMS_TOKEN_URL']     ?? 'https://api.orange.com/oauth/v3/token';
    
    if (empty($clientId) || empty($clientSecret)) {
        logError('SMS: ORANGE_SMS_CLIENT_ID ou CLIENT_SECRET non configurés dans .env');
        return null;
    }
    
    $credentials = base64_encode("{$clientId}:{$clientSecret}");
    
    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        logError("SMS: Erreur token Orange HTTP {$httpCode}: {$response}");
        return null;
    }
    
    $tokenData = json_decode($response, true);
    $accessToken = $tokenData['access_token'] ?? null;
    
    if ($accessToken) {
        // Mettre en cache pour 50 minutes
        file_put_contents($cacheFile, json_encode([
            'access_token' => $accessToken,
            'expires_at'   => time() + 3000, // 50 minutes
        ]));
    }
    
    return $accessToken;
}

/**
 * Traduit les codes d'erreur Orange en messages lisibles.
 */
function getOrangeErrorMessage(int $httpCode, ?array $response): string {
    return match($httpCode) {
        400 => 'Numéro de téléphone invalide ou message mal formaté.',
        401 => 'Identifiants Orange invalides ou token expiré.',
        403 => 'Crédit SMS insuffisant ou accès non autorisé à ce numéro.',
        404 => 'Endpoint Orange introuvable. Vérifiez la configuration.',
        429 => 'Limite de SMS atteinte. Réessayez dans quelques minutes.',
        500, 503 => 'Erreur du serveur Orange. Réessayez plus tard.',
        default => "Erreur inconnue (HTTP {$httpCode}).",
    };
}

// --- Utilisation directe en POST (optionnel) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_direct_api_call'])) {
    require_once __DIR__ . '/../includes/auth.php';
    // Seulement pour les administrateurs (ajouter vérification de rôle admin si nécessaire)
    $authPayload = requireAuth();
    
    $phone   = trim($_POST['phone']   ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (empty($phone) || empty($message)) {
        jsonResponse(['error' => 'phone et message sont obligatoires.'], 400);
    }
    
    $result = send_sms($phone, $message);
    jsonResponse($result, $result['success'] ? 200 : 500);
}
