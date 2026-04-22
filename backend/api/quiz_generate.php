<?php
/**
 * api/quiz_generate.php – Génération d'un quiz via l'IA
 * 
 * POST /api/quiz_generate.php
 * Headers : Authorization: Bearer <token>
 * Body JSON : { "category": "police", "matiere": "Droit", "nb_questions": 10 }
 * 
 * Retourne un JSON avec les questions QCM (sans les bonnes réponses).
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ia_helper.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/concours_data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée.'], 405);
}

$authPayload = requireAuth();
$user        = requirePremiumOrTrial($authPayload);
$userId      = (int) $user['id'];

checkRateLimit($userId, $user['user_type'], 'quiz_generate');

$input       = json_decode(file_get_contents('php://input'), true);
$category    = trim($input['category']     ?? $user['concours_cible'] ?? 'general');
$matiere     = trim($input['matiere']      ?? '');
$nbQuestions = min(max((int)($input['nb_questions'] ?? 10), 5), 20);

// Contexte du concours
$concoursContext = buildConcoursContext($category, $matiere);
$concoursInfo    = getConcoursInfo($category);

// Construire le prompt pour générer le quiz
$prompt = <<<PROMPT
{$concoursContext}

Génère exactement {$nbQuestions} questions QCM (Questionnaire à Choix Multiple) pour le concours "{$concoursInfo['nom']}".
{$matiereText}

FORMAT JSON STRICT – Retourne UNIQUEMENT ce JSON, sans commentaire :
{
  "quiz_title": "Titre du quiz",
  "category": "{$category}",
  "matiere": "{$matiere}",
  "questions": [
    {
      "id": 1,
      "question": "Texte de la question",
      "options": {
        "A": "Option A",
        "B": "Option B",
        "C": "Option C",
        "D": "Option D"
      },
      "correct_answer": "A",
      "explanation": "Explication pédagogique de la bonne réponse en 2-3 phrases.",
      "difficulty": "facile"
    }
  ]
}

RÈGLES :
- difficulty peut être : "facile", "moyen", "difficile"
- Varie les niveaux de difficulté
- Les questions doivent être réalistes et conformes aux concours burkinabè réels
- Les explications doivent être claires et pédagogiques
PROMPT;

// Insertion du nb de questions dans le prompt
$matiereText = !empty($matiere) ? "MATIÈRE CIBLÉE : {$matiere}" : "Varie les matières du concours.";
$prompt = str_replace('{$matiereText}', $matiereText, $prompt);
$prompt = str_replace('{$nbQuestions}', $nbQuestions, $prompt);

// Appel IA
$rawResponse = call_ia($prompt, null, '', true); // returnJson = true

// Parser le JSON retourné par l'IA
$quizData = json_decode($rawResponse, true);

if (!$quizData || !isset($quizData['questions']) || empty($quizData['questions'])) {
    logError('quiz_generate: IA n\'a pas retourné de JSON valide: ' . $rawResponse);
    jsonResponse(['error' => 'Impossible de générer le quiz. Veuillez réessayer.'], 500);
}

// Retirer les bonnes réponses et les explications (envoyées lors de quiz_submit)
$questionsForUser = [];
foreach ($quizData['questions'] as $q) {
    $questionsForUser[] = [
        'id'         => $q['id'],
        'question'   => $q['question'],
        'options'    => $q['options'],
        'difficulty' => $q['difficulty'] ?? 'moyen',
    ];
}

// Stocker le quiz complet en session côté serveur (ou encoder sécurisé)
// Pour simplifier, on encode les réponses dans un token signé
$quizSecret = base64_encode(json_encode([
    'questions'  => $quizData['questions'],
    'user_id'    => $userId,
    'category'   => $category,
    'expires_at' => time() + 3600, // Valide 1 heure
]));
$quizToken = hash_hmac('sha256', $quizSecret, $_ENV['JWT_SECRET']);

jsonResponse([
    'success'     => true,
    'quiz_title'  => $quizData['quiz_title'] ?? "Quiz {$concoursInfo['nom']}",
    'category'    => $category,
    'matiere'     => $matiere,
    'nb_questions'=> count($questionsForUser),
    'questions'   => $questionsForUser,
    // Token contenant les réponses (à renvoyer lors de quiz_submit)
    'quiz_data_encoded' => $quizSecret,
    'quiz_token'        => $quizToken,
]);
