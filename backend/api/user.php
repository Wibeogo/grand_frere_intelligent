<?php
/**
 * api/user.php – Profil et mise à jour utilisateur
 * 
 * GET  /api/user.php          → Retourne le profil + statut abonnement
 * PUT  /api/user.php          → Met à jour : full_name, concours_cible, fcm_token
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/concours_data.php';

$method = $_SERVER['REQUEST_METHOD'];
if (!in_array($method, ['GET', 'PUT'])) {
    jsonResponse(['error' => 'Méthode non autorisée.'], 405);
}

// Authentification requise
$authPayload = requireAuth();
$userId      = (int) $authPayload['sub'];

try {
    $pdo = getDB();
    
    if ($method === 'GET') {
        // --- Récupérer le profil complet ---
        $stmt = $pdo->prepare("
            SELECT id, email, full_name, phone, user_type,
                   subscription_expiry, free_trial_start, free_trial_end,
                   concours_cible, fcm_token, created_at
            FROM users WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['error' => 'Utilisateur introuvable.'], 404);
        }
        
        // Statut abonnement
        $now       = new DateTime();
        $isPremium = false;
        $trialLeft = 0;
        $subLeft   = 0;
        $status    = 'expired';
        
        if (!empty($user['free_trial_end'])) {
            $trialEnd = new DateTime($user['free_trial_end']);
            if ($trialEnd > $now) {
                $isPremium = true;
                $trialLeft = (int) $trialEnd->diff($now)->days + 1;
                $status    = 'trial';
            }
        }
        
        if (!empty($user['subscription_expiry'])) {
            $subExpiry = new DateTime($user['subscription_expiry']);
            if ($subExpiry > $now) {
                $isPremium = true;
                $subLeft   = (int) $subExpiry->diff($now)->days + 1;
                $status    = 'premium';
            }
        }
        
        // Statistiques rapides
        $stmtStats = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM quiz_scores WHERE user_id = ?) AS total_quiz,
                (SELECT COALESCE(AVG(ROUND(score / total * 100, 1)), 0) FROM quiz_scores WHERE user_id = ?) AS avg_score,
                (SELECT COUNT(*) FROM exam_white_results WHERE user_id = ?) AS total_exams,
                (SELECT COUNT(*) FROM conversations WHERE user_id = ? AND is_user = 1) AS total_messages
        ");
        $stmtStats->execute([$userId, $userId, $userId, $userId]);
        $stats = $stmtStats->fetch();
        
        // Récupérer le concours cible si défini
        $concoursInfo = null;
        if (!empty($user['concours_cible'])) {
            $info = getConcoursInfo($user['concours_cible']);
            $concoursInfo = [
                'key'         => $user['concours_cible'],
                'nom'         => $info['nom'],
                'emoji'       => $info['emoji'],
                'description' => $info['description'],
            ];
        }
        
        jsonResponse([
            'success' => true,
            'user'    => [
                'id'                  => (int) $user['id'],
                'email'               => $user['email'],
                'full_name'           => $user['full_name'],
                'phone'               => $user['phone'],
                'user_type'           => $user['user_type'],
                'is_premium'          => $isPremium,
                'subscription_status' => $status,
                'trial_days_left'     => $trialLeft,
                'subscription_days_left' => $subLeft,
                'trial_ends_at'       => $user['free_trial_end'],
                'subscription_expiry' => $user['subscription_expiry'],
                'concours_cible'      => $user['concours_cible'],
                'concours_info'       => $concoursInfo,
                'created_at'          => $user['created_at'],
                'stats'               => [
                    'total_quiz'     => (int) $stats['total_quiz'],
                    'avg_score'      => (float) $stats['avg_score'],
                    'total_exams'    => (int) $stats['total_exams'],
                    'total_messages' => (int) $stats['total_messages'],
                ],
            ],
        ]);
        
    } elseif ($method === 'PUT') {
        // --- Mettre à jour le profil ---
        $input         = json_decode(file_get_contents('php://input'), true);
        $fullName      = trim($input['full_name'] ?? '');
        $concoursCible = trim($input['concours_cible'] ?? '');
        $fcmToken      = trim($input['fcm_token'] ?? '');
        
        $updates = [];
        $params  = [];
        
        if (!empty($fullName)) {
            $updates[] = 'full_name = ?';
            $params[]  = $fullName;
        }
        
        if (!empty($concoursCible)) {
            // Valider que le concours existe
            $allConcours = getConcoursList();
            if (!array_key_exists($concoursCible, $allConcours)) {
                jsonResponse(['error' => 'Concours invalide.'], 400);
            }
            $updates[] = 'concours_cible = ?';
            $params[]  = $concoursCible;
        }
        
        if (!empty($fcmToken)) {
            $updates[] = 'fcm_token = ?';
            $params[]  = $fcmToken;
        }
        
        if (empty($updates)) {
            jsonResponse(['error' => 'Aucun champ à mettre à jour.'], 400);
        }
        
        $params[] = $userId;
        $sql      = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt     = $pdo->prepare($sql);
        $stmt->execute($params);
        
        jsonResponse([
            'success' => true,
            'message' => 'Profil mis à jour avec succès.',
        ]);
    }
    
} catch (PDOException $e) {
    logError('Erreur user.php: ' . $e->getMessage());
    jsonResponse(['error' => 'Erreur serveur.'], 500);
}
