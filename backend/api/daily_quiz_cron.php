<?php
/**
 * api/daily_quiz_cron.php – Script cron d'envoi du quiz quotidien
 * 
 * CRON : 0 8 * * * php /home/user/public_html/api/daily_quiz_cron.php
 * (Exécuté tous les jours à 8h via Hostinger)
 * 
 * - Génère 1 question de quiz pour chaque utilisateur actif
 * - Envoie un email (PHPMailer) avec la question
 * - Envoie un SMS (Orange API) avec un résumé court
 * - Enregistre dans daily_quiz_log
 */

// Sécurité : ce script ne doit être exécuté que via CLI ou avec le bon secret
if (php_sapi_name() !== 'cli') {
    $secret = $_GET['cron_secret'] ?? '';
    if (empty($_ENV['JWT_SECRET']) || $secret !== substr($_ENV['JWT_SECRET'], 0, 16)) {
        http_response_code(403);
        die('Accès refusé.');
    }
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ia_helper.php';
require_once __DIR__ . '/../api/send_sms.php';
require_once __DIR__ . '/../api/push_notification.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "[" . date('Y-m-d H:i:s') . "] Début du cron daily_quiz\n";

try {
    $pdo = getDB();
    
    // Récupérer tous les utilisateurs actifs (premium ou en période d'essai)
    $now  = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("
        SELECT id, email, full_name, phone, concours_cible, fcm_token, user_type,
               subscription_expiry, free_trial_end
        FROM users
        WHERE (
            (user_type = 'premium' AND subscription_expiry > ?)
            OR (free_trial_end > ?)
        )
        AND email IS NOT NULL
    ");
    $stmt->execute([$now, $now]);
    $users = $stmt->fetchAll();
    
    echo "[" . date('H:i:s') . "] {$_count_users} utilisateurs actifs trouvés.\n";
    $countUsers = count($users);
    echo "[" . date('H:i:s') . "] {$countUsers} utilisateurs actifs trouvés.\n";
    
    $successCount = 0;
    $errorCount   = 0;
    
    foreach ($users as $user) {
        try {
            $userId   = (int) $user['id'];
            $category = $user['concours_cible'] ?? 'general';
            
            // --- Générer une question de quiz via IA ---
            $prompt = <<<PROMPT
Génère UNE SEULE question de culture générale ou de matière concours pour les concours burkinabè.
Concours ciblé : {$category}

FORMAT JSON STRICT :
{
  "question": "Texte de la question",
  "options": { "A": "...", "B": "...", "C": "...", "D": "..." },
  "correct_answer": "A",
  "explanation": "Explication courte de 2 phrases."
}
PROMPT;
            
            $raw  = call_ia($prompt, null, '', true);
            $qData = json_decode($raw, true);
            
            if (!$qData || !isset($qData['question'])) {
                logError("daily_quiz: IA invalide pour user {$userId}");
                $errorCount++;
                continue;
            }
            
            $question  = $qData['question'];
            $options   = $qData['options'];
            $correct   = $qData['correct_answer'];
            $explain   = $qData['explanation'];
            $optionsStr = implode(' | ', array_map(fn($k, $v) => "{$k}) {$v}", array_keys($options), $options));
            
            // --- Enregistrer dans daily_quiz_log ---
            $pdo->prepare("
                INSERT INTO daily_quiz_log (user_id, quiz_date, question, correct_answer, sent_at)
                VALUES (?, CURDATE(), ?, ?, NOW())
            ")->execute([$userId, $question, $correct]);
            
            $firstName = explode(' ', $user['full_name'] ?? $user['email'])[0];
            
            // --- Envoyer l'email ---
            $emailSent = sendDailyEmail($user, $question, $optionsStr, $explain, $correct);
            
            // --- Envoyer le SMS (résumé très court) ---
            if (!empty($user['phone'])) {
                $smsText = "🎓 Quiz du jour GrandFrere:\n{$question}\n{$optionsStr}\nBonne chance {$firstName}!";
                // Limiter à 160 chars
                if (strlen($smsText) > 155) {
                    $smsText = "🎓GrandFrere Quiz: " . substr($question, 0, 100) . "... Répondre dans l'app!";
                }
                send_sms($user['phone'], $smsText);
            }
            
            // --- Notification push ---
            if (!empty($user['fcm_token'])) {
                sendPushNotification(
                    [$user['fcm_token']],
                    '📚 Question du jour !',
                    substr($question, 0, 100) . '...',
                    ['type' => 'daily_quiz', 'screen' => '/quiz']
                );
            }
            
            $successCount++;
            echo "[" . date('H:i:s') . "] ✅ User {$userId} ({$user['email']}) – quiz envoyé.\n";
            
            // Petite pause pour ne pas surcharger les APIs
            usleep(500000); // 0.5 seconde
            
        } catch (Exception $e) {
            logError("daily_quiz user {$user['id']}: " . $e->getMessage());
            $errorCount++;
        }
    }
    
    echo "[" . date('H:i:s') . "] Terminé : {$successCount} envois | {$errorCount} erreurs.\n";
    
} catch (PDOException $e) {
    logError('daily_quiz_cron PDO: ' . $e->getMessage());
    echo "ERREUR BDD: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Envoie le quiz quotidien par email via PHPMailer.
 */
function sendDailyEmail(array $user, string $question, string $options, string $explanation, string $correct): bool {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST']     ?? 'smtp.hostinger.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USER']     ?? '';
        $mail->Password   = $_ENV['MAIL_PASS']     ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($_ENV['MAIL_PORT'] ?? 587);
        $mail->CharSet    = 'UTF-8';
        
        $mail->setFrom($_ENV['MAIL_FROM'] ?? '', $_ENV['MAIL_FROM_NAME'] ?? 'Grand Frère');
        $mail->addAddress($user['email'], $user['full_name'] ?? '');
        
        $firstName = explode(' ', $user['full_name'] ?? 'Candidat')[0];
        $today     = date('d/m/Y');
        
        $mail->isHTML(true);
        $mail->Subject = "📚 Ta question du jour – Grand Frère Intelligent ({$today})";
        $mail->Body    = buildEmailHtml($firstName, $question, $options, $explanation, $correct);
        $mail->AltBody = "Question du jour : {$question}\n{$options}\nBonne réponse : {$correct}\nExplication : {$explanation}";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        logError("Email send error pour {$user['email']}: " . $e->getMessage());
        return false;
    }
}

/**
 * Génère le HTML de l'email du quiz quotidien.
 */
function buildEmailHtml(string $firstName, string $question, string $options, string $explanation, string $correct): string {
    $today = date('d/m/Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quiz du Jour – Grand Frère Intelligent</title></head>
<body style="margin:0;padding:0;background:#0f0c29;font-family:'Segoe UI',Arial,sans-serif;">
<div style="max-width:600px;margin:0 auto;background:linear-gradient(135deg,#0f0c29,#302b63,#24243e);border-radius:16px;overflow:hidden;">
  <div style="background:linear-gradient(135deg,#5C35D9,#9B59B6);padding:32px;text-align:center;">
    <h1 style="color:#F0B429;font-size:28px;margin:0;">🎓 Grand Frère Intelligent</h1>
    <p style="color:rgba(255,255,255,0.8);margin:8px 0 0;">Question du jour – {$today}</p>
  </div>
  <div style="padding:32px;">
    <p style="color:#e0e0e0;font-size:16px;">Bonjour <strong style="color:#F0B429;">{$firstName}</strong> 👋</p>
    <p style="color:#e0e0e0;">Voici ta question du jour pour avancer dans ta préparation !</p>
    <div style="background:rgba(92,53,217,0.2);border:1px solid rgba(92,53,217,0.5);border-radius:12px;padding:20px;margin:20px 0;">
      <p style="color:white;font-size:15px;font-weight:600;margin:0 0 16px;">{$question}</p>
      <p style="color:#a0a0c0;font-size:14px;margin:0;">{$options}</p>
    </div>
    <details style="background:rgba(240,180,41,0.1);border:1px solid rgba(240,180,41,0.3);border-radius:12px;padding:16px;cursor:pointer;">
      <summary style="color:#F0B429;font-weight:600;">Voir la réponse</summary>
      <p style="color:#4CAF50;font-size:16px;margin:12px 0 8px;font-weight:700;">✅ Bonne réponse : {$correct}</p>
      <p style="color:#e0e0e0;font-size:14px;">{$explanation}</p>
    </details>
    <div style="text-align:center;margin-top:24px;">
      <a href="#" style="background:linear-gradient(135deg,#5C35D9,#9B59B6);color:white;padding:14px 32px;border-radius:50px;text-decoration:none;font-weight:700;">
        📱 Ouvrir l'application
      </a>
    </div>
  </div>
  <div style="background:rgba(0,0,0,0.3);padding:16px;text-align:center;">
    <p style="color:rgba(255,255,255,0.4);font-size:12px;margin:0;">Grand Frère Intelligent © 2024 – Tu es capable !</p>
  </div>
</div>
</body></html>
HTML;
}
