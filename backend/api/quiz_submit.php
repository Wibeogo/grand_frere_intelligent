<?php
/**
 * api/quiz_submit.php – Soumission et correction des réponses du quiz
 * 
 * POST /api/quiz_submit.php
 * Headers : Authorization: Bearer <token>
 * Body JSON : {
 *   "quiz_data_encoded": "...",
 *   "quiz_token": "...",
 *   "answers": { "1": "A", "2": "C", ... },
 *   "category": "police"
 * }
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée.'], 405);
}

$authPayload = requireAuth();
$user        = requirePremiumOrTrial($authPayload);
$userId      = (int) $user['id'];

$input           = json_decode(file_get_contents('php://input'), true);
$quizDataEncoded = trim($input['quiz_data_encoded'] ?? '');
$quizToken       = trim($input['quiz_token']       ?? '');
$userAnswers     = $input['answers'] ?? [];
$category        = trim($input['category'] ?? '');

if (empty($quizDataEncoded) || empty($quizToken) || empty($userAnswers)) {
    jsonResponse(['error' => 'Données du quiz manquantes.'], 400);
}

// Vérifier l'intégrité du token (anti-triche)
$expectedToken = hash_hmac('sha256', $quizDataEncoded, $_ENV['JWT_SECRET']);
if (!hash_equals($expectedToken, $quizToken)) {
    jsonResponse(['error' => 'Token de quiz invalide. Tentative de triche détectée.'], 403);
}

// Décoder les données du quiz
$quizData = json_decode(base64_decode($quizDataEncoded), true);

if (!$quizData) {
    jsonResponse(['error' => 'Données de quiz corrompues.'], 400);
}

// Vérifier l'expiration du quiz
if (isset($quizData['expires_at']) && $quizData['expires_at'] < time()) {
    jsonResponse(['error' => 'Ce quiz a expiré. Générez un nouveau quiz.'], 400);
}

// Vérifier que le quiz appartient à cet utilisateur
if ((int)($quizData['user_id'] ?? 0) !== $userId) {
    jsonResponse(['error' => 'Ce quiz ne vous appartient pas.'], 403);
}

$questions = $quizData['questions'] ?? [];
if (empty($questions)) {
    jsonResponse(['error' => 'Questions du quiz introuvables.'], 400);
}

// Correction des réponses
$score      = 0;
$total      = count($questions);
$details    = [];

foreach ($questions as $q) {
    $qId            = (string) $q['id'];
    $userAnswer     = strtoupper(trim($userAnswers[$qId] ?? ''));
    $correctAnswer  = strtoupper(trim($q['correct_answer'] ?? ''));
    $isCorrect      = ($userAnswer === $correctAnswer);
    
    if ($isCorrect) {
        $score++;
    }
    
    $details[] = [
        'id'             => $q['id'],
        'question'       => $q['question'],
        'options'        => $q['options'],
        'user_answer'    => $userAnswer,
        'correct_answer' => $correctAnswer,
        'is_correct'     => $isCorrect,
        'explanation'    => $q['explanation'] ?? '',
        'difficulty'     => $q['difficulty'] ?? 'moyen',
    ];
}

$percentage = $total > 0 ? round(($score / $total) * 100) : 0;
$categoryUsed = $category ?: ($quizData['category'] ?? 'general');

// Avis basé sur le score
$appreciation = match(true) {
    $percentage >= 80 => '🏆 Excellent ! Tu maîtrises très bien ce sujet.',
    $percentage >= 60 => '👍 Bien ! Continue tes efforts.',
    $percentage >= 40 => '📚 Assez bien, mais il faut retravailler certains points.',
    default           => '💪 Ne te décourage pas ! Révise et réessaie.',
};

// Sauvegarder le score en BDD
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO quiz_scores (user_id, score, total, category, details, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$userId, $score, $total, $categoryUsed, json_encode($details)]);
    
    jsonResponse([
        'success'      => true,
        'score'        => $score,
        'total'        => $total,
        'percentage'   => $percentage,
        'appreciation' => $appreciation,
        'details'      => $details,
        'category'     => $categoryUsed,
    ]);
    
} catch (PDOException $e) {
    logError('Erreur quiz_submit: ' . $e->getMessage());
    jsonResponse(['error' => 'Erreur lors de la sauvegarde du score.'], 500);
}
