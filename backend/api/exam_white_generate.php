<?php
/**
 * api/exam_white_generate.php – Génération d'un examen blanc complet
 * 
 * POST /api/exam_white_generate.php
 * Headers : Authorization: Bearer <token>
 * Body JSON : {
 *   "concours": "police",
 *   "nb_qcm": 20,
 *   "nb_ouvertes": 2,
 *   "duree_minutes": 120
 * }
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

checkRateLimit($userId, $user['user_type'], 'exam_white_generate');

$input       = json_decode(file_get_contents('php://input'), true);
$concours    = trim($input['concours']       ?? $user['concours_cible'] ?? 'police');
$nbQcm       = min(max((int)($input['nb_qcm']      ?? 20), 5), 40);
$nbOuvertes  = min(max((int)($input['nb_ouvertes'] ?? 2),  0), 5);
$dureMins    = min(max((int)($input['duree_minutes'] ?? 120), 30), 360);

$concoursInfo    = getConcoursInfo($concours);
$concoursContext = buildConcoursContext($concours);

$totalQuestions = $nbQcm + $nbOuvertes;

$prompt = <<<PROMPT
{$concoursContext}

GÉNÈRE UN EXAMEN BLANC COMPLET pour le concours "{$concoursInfo['nom']}".

COMPOSITION :
- {$nbQcm} questions QCM (4 options, une seule bonne réponse)
- {$nbOuvertes} questions ouvertes/à développer
- Durée totale : {$dureMins} minutes
- Barème : chaque QCM vaut 1 point, chaque question ouverte vaut selon la complexité (entre 3 et 10 points)

FORMAT JSON STRICT – Retourne UNIQUEMENT ce JSON :
{
  "exam_title": "Examen blanc – Concours [Nom]",
  "concours": "{$concours}",
  "duration_minutes": {$dureMins},
  "total_points": 0,
  "instructions": "Instructions générales de l'examen...",
  "sections": [
    {
      "section_id": 1,
      "section_title": "PARTIE I : Questions à Choix Multiples",
      "type": "qcm",
      "questions": [
        {
          "id": 1,
          "question": "...",
          "options": { "A": "...", "B": "...", "C": "...", "D": "..." },
          "correct_answer": "A",
          "points": 1,
          "explanation": "..."
        }
      ]
    },
    {
      "section_id": 2,
      "section_title": "PARTIE II : Questions à Développer",
      "type": "ouverte",
      "questions": [
        {
          "id": 21,
          "question": "...",
          "points": 5,
          "model_answer": "Réponse modèle détaillée...",
          "keywords": ["mot-clé1", "mot-clé2"]
        }
      ]
    }
  ]
}

Calcule le total_points en sommant tous les points de chaque question.
PROMPT;

$rawResponse = call_ia($prompt, null, '', true);
$examData    = json_decode($rawResponse, true);

if (!$examData || !isset($examData['sections'])) {
    logError('exam_white_generate: JSON invalide: ' . $rawResponse);
    jsonResponse(['error' => 'Impossible de générer l\'examen blanc. Réessayez.'], 500);
}

// Retirer les réponses pour l'utilisateur
$examForUser = $examData;
foreach ($examForUser['sections'] as &$section) {
    foreach ($section['questions'] as &$q) {
        // Masquer les réponses
        if ($section['type'] === 'qcm') {
            unset($q['correct_answer'], $q['explanation']);
        } elseif ($section['type'] === 'ouverte') {
            unset($q['model_answer'], $q['keywords']);
        }
    }
}
unset($section, $q);

// Encoder les données complètes (avec réponses) pour la correction
$examEncoded = base64_encode(json_encode([
    'exam'       => $examData,
    'user_id'    => $userId,
    'concours'   => $concours,
    'expires_at' => time() + ($dureMins * 60) + 3600,
]));
$examToken = hash_hmac('sha256', $examEncoded, $_ENV['JWT_SECRET']);

jsonResponse([
    'success'       => true,
    'exam'          => $examForUser,
    'exam_encoded'  => $examEncoded,
    'exam_token'    => $examToken,
]);
