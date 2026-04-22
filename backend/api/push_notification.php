<?php
/**
 * api/push_notification.php – Envoi de notifications push via Firebase FCM
 * 
 * Utilise l'API FCM HTTP v1 pour envoyer des notifications push
 * aux utilisateurs via leurs tokens Firebase enregistrés.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

/**
 * Envoie une notification push FCM à un ou plusieurs tokens.
 * 
 * @param array  $fcmTokens Liste des tokens FCM
 * @param string $title     Titre de la notification
 * @param string $body      Corps de la notification
 * @param array  $data      Données supplémentaires (optionnel)
 * @return array Résultat de l'envoi
 */
function sendPushNotification(array $fcmTokens, string $title, string $body, array $data = []): array {
    $fcmServerKey = $_ENV['FCM_SERVER_KEY'] ?? '';
    
    if (empty($fcmServerKey)) {
        logError('FCM: FCM_SERVER_KEY non configuré dans .env');
        return ['success' => false, 'message' => 'Configuration FCM manquante.'];
    }
    
    if (empty($fcmTokens)) {
        return ['success' => false, 'message' => 'Aucun token FCM fourni.'];
    }
    
    $results = ['sent' => 0, 'failed' => 0, 'errors' => []];
    
    // Envoyer par lots de 500 (limite FCM)
    $batches = array_chunk($fcmTokens, 500);
    
    foreach ($batches as $batch) {
        $payload = [
            'notification' => [
                'title' => $title,
                'body'  => $body,
                'sound' => 'default',
                'icon'  => 'notification_icon',
                'color' => '#5C35D9',
            ],
            'data'              => $data,
            'registration_ids'  => $batch,
            'android'           => [
                'notification' => [
                    'channel_id'   => 'grand_frere_channel',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ],
            ],
            'apns' => [
                'payload' => [
                    'aps' => ['sound' => 'default'],
                ],
            ],
        ];
        
        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: key=' . $fcmServerKey,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200 && isset($responseData['success'])) {
            $results['sent']   += (int) $responseData['success'];
            $results['failed'] += (int) $responseData['failure'];
            
            if ($responseData['failure'] > 0 && isset($responseData['results'])) {
                foreach ($responseData['results'] as $i => $r) {
                    if (isset($r['error'])) {
                        $results['errors'][] = $r['error'];
                    }
                }
            }
        } else {
            logError("FCM error HTTP {$httpCode}: {$response}");
            $results['failed'] += count($batch);
        }
    }
    
    return array_merge(['success' => $results['sent'] > 0], $results);
}

/**
 * Envoie une notification à un utilisateur spécifique par son ID.
 */
function notifyUser(int $userId, string $title, string $body, array $data = []): bool {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT fcm_token FROM users WHERE id = ? AND fcm_token IS NOT NULL");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user || empty($user['fcm_token'])) {
            return false;
        }
        
        $result = sendPushNotification([$user['fcm_token']], $title, $body, $data);
        return $result['success'];
        
    } catch (PDOException $e) {
        logError("notifyUser PDO: " . $e->getMessage());
        return false;
    }
}

// --- Endpoint API direct (optionnel, pour admin) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authPayload = requireAuth();
    $user        = getAuthUser($authPayload);
    
    $input   = json_decode(file_get_contents('php://input'), true);
    $title   = trim($input['title']   ?? '');
    $body    = trim($input['body']    ?? '');
    $userId  = (int) ($input['user_id'] ?? 0);
    
    if (empty($title) || empty($body)) {
        jsonResponse(['error' => 'title et body sont requis.'], 400);
    }
    
    if ($userId > 0) {
        $result = notifyUser($userId, $title, $body);
    } else {
        jsonResponse(['error' => 'user_id requis.'], 400);
    }
    
    jsonResponse(['success' => $result]);
}
