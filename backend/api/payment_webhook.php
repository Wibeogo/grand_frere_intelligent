<?php
/**
 * api/payment_webhook.php – Réception et validation des webhooks Senfenico
 * 
 * POST /api/payment_webhook.php (appelé par Senfenico après paiement)
 * 
 * - Vérifie la signature HMAC de Senfenico
 * - Si paiement réussi : active l'abonnement premium 30 jours
 * - Retourne 200 pour confirmer la réception à Senfenico
 */

require_once __DIR__ . '/../includes/db.php';

// NE PAS utiliser requireAuth() ici – c'est un webhook de Senfenico
// La sécurité est assurée par la vérification de signature HMAC

// Lire le corps brut (important pour HMAC)
$rawBody    = file_get_contents('php://input');
$webhookSig = $_SERVER['HTTP_X_SENFENICO_SIGNATURE'] ?? 
              $_SERVER['HTTP_X_SIGNATURE']           ?? '';

// --- Vérification de la signature HMAC ---
$webhookSecret = $_ENV['SENFENICO_WEBHOOK_SECRET'] ?? '';

if (!empty($webhookSecret)) {
    $expectedSig = hash_hmac('sha256', $rawBody, $webhookSecret);
    
    // Comparaison sécurisée pour éviter les timing attacks
    if (!hash_equals($expectedSig, $webhookSig)) {
        logError("Webhook Senfenico: signature invalide. Reçu: {$webhookSig}");
        http_response_code(401);
        echo json_encode(['error' => 'Signature invalide.']);
        exit;
    }
}

$payload = json_decode($rawBody, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload JSON invalide.']);
    exit;
}

// Extraire les données du webhook
$eventType = $payload['event']  ?? $payload['status']      ?? '';
$reference = $payload['reference'] ?? $payload['id']        ?? '';
$metadata  = $payload['metadata']  ?? $payload['data']['metadata'] ?? [];
$paymentId = (int) ($metadata['payment_id'] ?? 0);
$userId    = (int) ($metadata['user_id']    ?? 0);
$planType  = $metadata['plan_type']          ?? 'monthly';
$amount    = (int)($payload['amount']        ?? $payload['data']['amount'] ?? 0);

// Journaliser le webhook reçu
logError("Webhook Senfenico reçu: event={$eventType}, ref={$reference}, user={$userId}, payment={$paymentId}");

// Vérifier les données nécessaires
if ($paymentId === 0 || $userId === 0) {
    // Essayer de retrouver le paiement par référence Senfenico
    if (!empty($reference)) {
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT id, user_id, plan_type FROM payments WHERE senfenico_reference = ? LIMIT 1");
            $stmt->execute([$reference]);
            $payment = $stmt->fetch();
            if ($payment) {
                $paymentId = (int) $payment['id'];
                $userId    = (int) $payment['user_id'];
                $planType  = $payment['plan_type'];
            }
        } catch (PDOException $e) {
            logError('Webhook PDO: ' . $e->getMessage());
        }
    }
}

if ($paymentId === 0 || $userId === 0) {
    http_response_code(200); // Retourner 200 pour que Senfenico ne réessaie pas
    echo json_encode(['message' => 'Paiement non trouvé, ignorer.']);
    exit;
}

try {
    $pdo = getDB();
    
    // Vérifier les événements de succès (les noms peuvent varier selon Senfenico)
    $successEvents = ['payment.completed', 'checkout.success', 'payment_success', 'completed', 'success'];
    $failureEvents = ['payment.failed', 'checkout.failed', 'payment_failed', 'failed', 'cancelled'];
    
    if (in_array(strtolower($eventType), $successEvents) || $payload['status'] === 'success') {
        
        // Vérifier que ce paiement n'a pas déjà été traité (idempotence)
        $stmt = $pdo->prepare("SELECT status FROM payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $existingPayment = $stmt->fetch();
        
        if ($existingPayment && $existingPayment['status'] === 'completed') {
            http_response_code(200);
            echo json_encode(['message' => 'Paiement déjà traité.']);
            exit;
        }
        
        // --- Activer l'abonnement premium ---
        $newExpiry = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Si l'utilisateur a déjà un abonnement actif, prolonger depuis la date d'expiration
        $stmtUser = $pdo->prepare("SELECT subscription_expiry FROM users WHERE id = ?");
        $stmtUser->execute([$userId]);
        $currentUser = $stmtUser->fetch();
        
        if (!empty($currentUser['subscription_expiry'])) {
            $currentExpiry = new DateTime($currentUser['subscription_expiry']);
            if ($currentExpiry > new DateTime()) {
                // Prolonger depuis la date existante
                $newExpiry = date('Y-m-d H:i:s', $currentExpiry->getTimestamp() + (30 * 24 * 3600));
            }
        }
        
        // Mettre à jour l'utilisateur
        $pdo->prepare("
            UPDATE users 
            SET user_type = 'premium', subscription_expiry = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$newExpiry, $userId]);
        
        // Mettre à jour le paiement
        $pdo->prepare("
            UPDATE payments 
            SET status = 'completed', transaction_id = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$reference, $paymentId]);
        
        logError("Webhook: Abonnement premium activé pour user={$userId} jusqu'au {$newExpiry}");
        
    } elseif (in_array(strtolower($eventType), $failureEvents)) {
        
        // Marquer le paiement comme échoué
        $pdo->prepare("
            UPDATE payments SET status = 'failed', updated_at = NOW() WHERE id = ?
        ")->execute([$paymentId]);
        
        logError("Webhook: Paiement échoué pour user={$userId}, payment={$paymentId}");
    }
    
    // Toujours retourner 200 à Senfenico pour éviter les re-envois
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Webhook traité.']);
    
} catch (PDOException $e) {
    logError('Erreur payment_webhook PDO: ' . $e->getMessage());
    // Retourner 200 quand même (sinon Senfenico va continuer à envoyer)
    http_response_code(200);
    echo json_encode(['message' => 'Erreur interne traitée.']);
}
