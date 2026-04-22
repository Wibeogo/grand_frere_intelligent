<?php
/**
 * includes/auth.php – Gestion des tokens JWT
 * 
 * Fournit des fonctions pour :
 * - Générer un token JWT lors du login/register
 * - Valider le token JWT sur les endpoints protégés
 * - Récupérer l'utilisateur courant à partir du token
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

require_once __DIR__ . '/db.php';

/**
 * Génère un token JWT pour un utilisateur donné.
 * 
 * @param int    $userId   ID de l'utilisateur
 * @param string $email    Email de l'utilisateur
 * @param string $userType 'free' ou 'premium'
 * @return string Token JWT signé
 */
function generateToken(int $userId, string $email, string $userType): string {
    $secret  = $_ENV['JWT_SECRET'];
    $expiry  = (int)($_ENV['JWT_EXPIRY'] ?? 86400); // 24h par défaut
    $now     = time();
    
    $payload = [
        'iss' => 'grand-frere-intelligent',
        'iat' => $now,
        'exp' => $now + $expiry,
        'sub' => $userId,
        'email' => $email,
        'user_type' => $userType,
    ];
    
    return JWT::encode($payload, $secret, 'HS256');
}

/**
 * Valide le token JWT depuis l'en-tête Authorization.
 * Termine l'exécution avec une erreur 401 si le token est invalide.
 * 
 * @return array Payload décodé du token
 */
function requireAuth(): array {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    
    if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
        jsonResponse(['error' => 'Token d\'authentification manquant.'], 401);
    }
    
    $token = substr($authHeader, 7);
    
    try {
        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
        return (array) $decoded;
    } catch (ExpiredException $e) {
        jsonResponse(['error' => 'Session expirée. Veuillez vous reconnecter.'], 401);
    } catch (SignatureInvalidException $e) {
        jsonResponse(['error' => 'Token invalide.'], 401);
    } catch (Exception $e) {
        logError('JWT Error: ' . $e->getMessage());
        jsonResponse(['error' => 'Authentification échouée.'], 401);
    }
}

/**
 * Vérifie que l'utilisateur authentifié a accès premium (abonnement actif ou essai en cours).
 * 
 * @param array $authPayload Payload JWT retourné par requireAuth()
 * @return array Données utilisateur complètes depuis la BDD
 */
function requirePremiumOrTrial(array $authPayload): array {
    $pdo    = getDB();
    $userId = (int) $authPayload['sub'];
    
    $stmt = $pdo->prepare("
        SELECT id, email, full_name, user_type, subscription_expiry,
               free_trial_start, free_trial_end, concours_cible
        FROM users WHERE id = ? LIMIT 1
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['error' => 'Utilisateur introuvable.'], 404);
    }
    
    $now = new DateTime();
    $hasPremium = false;
    
    // Vérifier abonnement premium actif
    if ($user['user_type'] === 'premium' && !empty($user['subscription_expiry'])) {
        $expiry = new DateTime($user['subscription_expiry']);
        if ($expiry > $now) {
            $hasPremium = true;
        }
    }
    
    // Vérifier période d'essai gratuit
    if (!$hasPremium && !empty($user['free_trial_end'])) {
        $trialEnd = new DateTime($user['free_trial_end']);
        if ($trialEnd > $now) {
            $hasPremium = true;
        }
    }
    
    if (!$hasPremium) {
        jsonResponse([
            'error'   => 'Accès premium requis.',
            'message' => 'Votre période d\'essai est terminée. Abonnez-vous pour 2800 FCFA/mois.',
            'code'    => 'SUBSCRIPTION_REQUIRED'
        ], 403);
    }
    
    return $user;
}

/**
 * Récupère l'utilisateur sans vérifier le premium (utilisé pour les endpoints publics ou de profil).
 */
function getAuthUser(array $authPayload): array {
    $pdo    = getDB();
    $userId = (int) $authPayload['sub'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['error' => 'Utilisateur introuvable.'], 404);
    }
    
    return $user;
}
