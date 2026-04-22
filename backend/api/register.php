<?php
/**
 * api/register.php – Inscription d'un nouvel utilisateur
 * 
 * POST /api/register.php
 * Body JSON : { "email", "password", "phone", "full_name" }
 * 
 * - Crée le compte avec password_hash()
 * - Active une période d'essai gratuit de 3 jours
 * - Retourne un token JWT
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Autoriser seulement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée.'], 405);
}

// Lire et décoder le corps JSON
$input = json_decode(file_get_contents('php://input'), true);

// Validation des champs obligatoires
$email    = trim($input['email']    ?? '');
$password = trim($input['password'] ?? '');
$phone    = trim($input['phone']    ?? '');
$fullName = trim($input['full_name'] ?? '');

if (empty($email) || empty($password) || empty($phone)) {
    jsonResponse(['error' => 'Email, mot de passe et téléphone sont obligatoires.'], 400);
}

// Validation format email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Format d\'email invalide.'], 400);
}

// Validation mot de passe (minimum 6 caractères)
if (strlen($password) < 6) {
    jsonResponse(['error' => 'Le mot de passe doit contenir au moins 6 caractères.'], 400);
}

// Validation téléphone burkinabè (format : 7X/6X XXXXXXXX, 8 chiffres)
$phoneCleaned = preg_replace('/\D/', '', $phone);
if (strlen($phoneCleaned) < 8 || strlen($phoneCleaned) > 15) {
    jsonResponse(['error' => 'Numéro de téléphone invalide.'], 400);
}

try {
    $pdo = getDB();
    
    // Vérifier si l'email existe déjà
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Cet email est déjà utilisé.'], 409);
    }
    
    // Hasher le mot de passe
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Calculer la période d'essai (3 jours)
    $now          = date('Y-m-d H:i:s');
    $trialEnd     = date('Y-m-d H:i:s', strtotime('+3 days'));
    
    // Insérer l'utilisateur
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password, phone, full_name, user_type, free_trial_start, free_trial_end, created_at)
        VALUES (?, ?, ?, ?, 'free', ?, ?, ?)
    ");
    $stmt->execute([$email, $hashedPassword, $phoneCleaned, $fullName ?: null, $now, $trialEnd, $now]);
    
    $userId = (int) $pdo->lastInsertId();
    
    // Générer le token JWT
    $token = generateToken($userId, $email, 'free');
    
    // Calculer les jours d'essai restants
    $trialDaysLeft = 3;
    
    jsonResponse([
        'success'          => true,
        'message'          => 'Compte créé avec succès ! Ton essai gratuit de 3 jours commence maintenant. 🎉',
        'token'            => $token,
        'user' => [
            'id'               => $userId,
            'email'            => $email,
            'full_name'        => $fullName ?: null,
            'phone'            => $phoneCleaned,
            'user_type'        => 'free',
            'is_premium'       => true, // Période d'essai = accès premium
            'trial_days_left'  => $trialDaysLeft,
            'trial_ends_at'    => $trialEnd,
            'concours_cible'   => null,
        ],
    ], 201);
    
} catch (PDOException $e) {
    logError('Erreur register: ' . $e->getMessage());
    jsonResponse(['error' => 'Erreur lors de la création du compte. Veuillez réessayer.'], 500);
}
