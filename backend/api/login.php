<?php
/**
 * api/login.php – Connexion d'un utilisateur existant
 * 
 * POST /api/login.php
 * Body JSON : { "email", "password" }
 * 
 * Retourne un token JWT et le statut complet de l'abonnement.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée.'], 405);
}

$input    = json_decode(file_get_contents('php://input'), true);
$email    = trim($input['email']    ?? '');
$password = trim($input['password'] ?? '');

if (empty($email) || empty($password)) {
    jsonResponse(['error' => 'Email et mot de passe sont obligatoires.'], 400);
}

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        SELECT id, email, password, full_name, phone, user_type,
               subscription_expiry, free_trial_start, free_trial_end, concours_cible, fcm_token
        FROM users WHERE email = ? LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // Vérification utilisateur et mot de passe
    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(['error' => 'Email ou mot de passe incorrect.'], 401);
    }
    
    // Calculer le statut premium et les jours restants
    $now        = new DateTime();
    $isPremium  = false;
    $trialLeft  = 0;
    $subLeft    = 0;
    $status     = 'expired';
    
    // Vérifier période d'essai
    if (!empty($user['free_trial_end'])) {
        $trialEnd = new DateTime($user['free_trial_end']);
        if ($trialEnd > $now) {
            $isPremium = true;
            $trialLeft = $trialEnd->diff($now)->days + 1;
            $status    = 'trial';
        }
    }
    
    // Vérifier abonnement premium (peut superposer l'essai)
    if (!empty($user['subscription_expiry'])) {
        $subExpiry = new DateTime($user['subscription_expiry']);
        if ($subExpiry > $now) {
            $isPremium = true;
            $subLeft   = $subExpiry->diff($now)->days + 1;
            $status    = 'premium';
        }
    }
    
    // Générer le token JWT
    $token = generateToken((int)$user['id'], $user['email'], $user['user_type']);
    
    jsonResponse([
        'success' => true,
        'message' => 'Connexion réussie !',
        'token'   => $token,
        'user'    => [
            'id'                  => (int) $user['id'],
            'email'               => $user['email'],
            'full_name'           => $user['full_name'],
            'phone'               => $user['phone'],
            'user_type'           => $user['user_type'],
            'concours_cible'      => $user['concours_cible'],
            'is_premium'          => $isPremium,
            'subscription_status' => $status,        // 'trial', 'premium', 'expired'
            'trial_days_left'     => $trialLeft,
            'subscription_days_left' => $subLeft,
            'trial_ends_at'       => $user['free_trial_end'],
            'subscription_expiry' => $user['subscription_expiry'],
        ],
    ]);
    
} catch (PDOException $e) {
    logError('Erreur login: ' . $e->getMessage());
    jsonResponse(['error' => 'Erreur de connexion au serveur.'], 500);
}
