<?php
/**
 * api/check_trial_expiry.php – Vérification et basculement des essais expirés
 * 
 * CRON : 0 1 * * * php /home/user/public_html/api/check_trial_expiry.php
 * (Exécuté tous les jours à 1h du matin via Hostinger)
 * 
 * - Trouve les utilisateurs dont l'essai gratuit a expiré
 * - Vérifie s'ils ont un abonnement actif
 * - Sinon, les notifie et leur rappelle de s'abonner
 * - Nettoie les anciennes données de rate limiting
 */

if (php_sapi_name() !== 'cli') {
    $secret = $_GET['cron_secret'] ?? '';
    require_once __DIR__ . '/../vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
    if ($secret !== substr($_ENV['JWT_SECRET'] ?? '', 0, 16)) {
        http_response_code(403);
        die('Accès refusé.');
    }
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../api/send_sms.php';
require_once __DIR__ . '/../api/push_notification.php';
require_once __DIR__ . '/../includes/rate_limit.php';

use PHPMailer\PHPMailer\PHPMailer;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
try { $dotenv->load(); } catch (Exception $e) {}

echo "[" . date('Y-m-d H:i:s') . "] Début check_trial_expiry\n";

try {
    $pdo = getDB();
    $now = date('Y-m-d H:i:s');
    
    // 1. Trouver les utilisateurs dont l'essai vient d'expirer (dans les dernières 24h)
    $stmt = $pdo->prepare("
        SELECT id, email, full_name, phone, fcm_token, user_type, subscription_expiry, free_trial_end
        FROM users
        WHERE free_trial_end BETWEEN DATE_SUB(?, INTERVAL 24 HOUR) AND ?
        AND (subscription_expiry IS NULL OR subscription_expiry < ?)
    ");
    $stmt->execute([$now, $now, $now]);
    $expiredTrialUsers = $stmt->fetchAll();
    
    echo "[" . date('H:i:s') . "] " . count($expiredTrialUsers) . " essais expirés trouvés.\n";
    
    foreach ($expiredTrialUsers as $user) {
        $firstName = explode(' ', $user['full_name'] ?? 'Candidat')[0];
        
        // Notification push de rappel
        if (!empty($user['fcm_token'])) {
            sendPushNotification(
                [$user['fcm_token']],
                '⏰ Ton essai gratuit est terminé !',
                'Abonne-toi pour seulement 2 800 FCFA/mois et continue ta préparation. 🎓',
                ['type' => 'trial_expired', 'screen' => '/subscription']
            );
        }
        
        // SMS de rappel (court)
        if (!empty($user['phone'])) {
            $smsText = "Grand Frere: Ton essai gratuit est termine. Abonne-toi pour 2800 FCFA/mois et continue! Telechargez l'app.";
            send_sms($user['phone'], $smsText);
        }
        
        // Email de rappel
        sendTrialExpiredEmail($user);
        
        echo "[" . date('H:i:s') . "] ✅ Rappel envoyé à user {$user['id']} ({$user['email']})\n";
        
        usleep(300000); // 0.3s entre chaque envoi
    }
    
    // 2. Désactiver les abonnements premium expirés
    $stmt2 = $pdo->prepare("
        UPDATE users 
        SET user_type = 'free'
        WHERE user_type = 'premium' 
        AND subscription_expiry < ?
        AND subscription_expiry IS NOT NULL
    ");
    $stmt2->execute([$now]);
    $downgradedCount = $stmt2->rowCount();
    echo "[" . date('H:i:s') . "] {$downgradedCount} abonnements premium expirés désactivés.\n";
    
    // 3. Nettoyer la table de rate limiting (entrées > 2 heures)
    cleanRateLimitTable();
    echo "[" . date('H:i:s') . "] Table rate_limiting nettoyée.\n";
    
    echo "[" . date('H:i:s') . "] check_trial_expiry terminé avec succès.\n";
    
} catch (PDOException $e) {
    logError('check_trial_expiry PDO: ' . $e->getMessage());
    echo "ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Envoie un email de rappel d'expiration de l'essai gratuit.
 */
function sendTrialExpiredEmail(array $user): void {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST']   ?? 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USER']   ?? '';
        $mail->Password   = $_ENV['MAIL_PASS']   ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);
        $mail->CharSet    = 'UTF-8';
        
        $mail->setFrom($_ENV['MAIL_FROM'] ?? '', 'Grand Frère Intelligent');
        $mail->addAddress($user['email'], $user['full_name'] ?? '');
        
        $firstName = explode(' ', $user['full_name'] ?? 'Candidat')[0];
        
        $mail->isHTML(true);
        $mail->Subject = '⏰ Ton essai gratuit est terminé – Passe au Premium !';
        $mail->Body    = <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0f0c29;font-family:Arial,sans-serif;">
<div style="max-width:600px;margin:0 auto;background:linear-gradient(135deg,#0f0c29,#302b63);border-radius:16px;overflow:hidden;">
  <div style="background:linear-gradient(135deg,#c0392b,#e74c3c);padding:32px;text-align:center;">
    <h1 style="color:white;margin:0;">⏰ Essai gratuit expiré</h1>
  </div>
  <div style="padding:32px;">
    <p style="color:#e0e0e0;font-size:16px;">Bonsoir <strong style="color:#F0B429;">{$firstName}</strong>,</p>
    <p style="color:#e0e0e0;">Tes 3 jours d'essai gratuit sont maintenant écoulés. Pour continuer ta préparation aux concours, passe à l'abonnement Premium.</p>
    <div style="background:linear-gradient(135deg,rgba(92,53,217,0.3),rgba(155,89,182,0.3));border-radius:16px;padding:24px;margin:24px 0;text-align:center;">
      <p style="color:#F0B429;font-size:32px;font-weight:800;margin:0;">2 800 FCFA</p>
      <p style="color:#e0e0e0;margin:8px 0;">par mois – Accès illimité</p>
      <ul style="list-style:none;padding:0;color:#e0e0e0;text-align:left;margin:16px auto;max-width:300px;">
        <li>✅ Chat illimité avec le Grand Frère</li>
        <li>✅ Quiz quotidiens personnalisés</li>
        <li>✅ Examens blancs complets</li>
        <li>✅ Correction de photos</li>
        <li>✅ Suivi de progression</li>
      </ul>
    </div>
    <div style="text-align:center;">
      <a href="#" style="background:linear-gradient(135deg,#F0B429,#e67e22);color:white;padding:16px 40px;border-radius:50px;text-decoration:none;font-weight:700;font-size:18px;">
        💳 S'abonner maintenant
      </a>
    </div>
  </div>
</div>
</body></html>
HTML;
        $mail->send();
    } catch (Exception $e) {
        logError("trial_expired email error: " . $e->getMessage());
    }
}
