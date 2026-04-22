<?php
/**
 * api/exam_white_submit.php – Soumission et correction de l'examen blanc
 * 
 * POST /api/exam_white_submit.php
 * Headers : Authorization: Bearer <token>
 * Body JSON : {
 *   "exam_encoded": "...",
 *   "exam_token": "...",
 *   "answers": { "1": "B", "2": "D", ..., "21": "Réponse ouverte..." },
 *   "duration_taken": 95
 * }
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/ia_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée.'], 405);
}

$authPayload = requireAuth();
$user        = requirePremiumOrTrial($authPayload);
$userId      = (int) $user['id'];

$input          = json_decode(file_get_contents('php://input'), true);
$examEncoded    = trim($input['exam_encoded']  ?? '');
$examToken      = trim($input['exam_token']    ?? '');
$userAnswers    = $input['answers']            ?? [];
$durationTaken  = (int) ($input['duration_taken'] ?? 0);

if (empty($examEncoded) || empty($examToken)) {
    jsonResponse(['error' => 'Données de l\'examen manquantes.'], 400);
}

// Vérifier le token HMAC
$expectedToken = hash_hmac('sha256', $examEncoded, $_ENV['JWT_SECRET']);
if (!hash_equals($expectedToken, $examToken)) {
    jsonResponse(['error' => 'Token d\'examen invalide.'], 403);
}

$examPayload = json_decode(base64_decode($examEncoded), true);
if (!$examPayload || (int)($examPayload['user_id'] ?? 0) !== $userId) {
    jsonResponse(['error' => 'Examen invalide ou non autorisé.'], 403);
}

$examData = $examPayload['exam'];
$concours = $examPayload['concours'] ?? 'general';

$totalScore    = 0;
$totalPossible = (int)($examData['total_points'] ?? 0);
$correctedSections = [];

foreach ($examData['sections'] as $section) {
    $sectionResults = [];
    
    foreach ($section['questions'] as $q) {
        $qId       = (string) $q['id'];
        $userAnswer = trim($userAnswers[$qId] ?? '');
        
        if ($section['type'] === 'qcm') {
            // Correction automatique QCM
            $isCorrect = (strtoupper($userAnswer) === strtoupper($q['correct_answer'] ?? ''));
            $earned    = $isCorrect ? ($q['points'] ?? 1) : 0;
            $totalScore += $earned;
            
            $sectionResults[] = [
                'id'             => $q['id'],
                'question'       => $q['question'],
                'user_answer'    => strtoupper($userAnswer),
                'correct_answer' => $q['correct_answer'],
                'is_correct'     => $isCorrect,
                'points_earned'  => $earned,
                'points_max'     => $q['points'] ?? 1,
                'explanation'    => $q['explanation'] ?? '',
            ];
            
        } elseif ($section['type'] === 'ouverte') {
            // Correction des questions ouvertes par IA
            $maxPoints   = $q['points'] ?? 5;
            $modelAnswer = $q['model_answer'] ?? '';
            $keywords    = $q['keywords']     ?? [];
            
            // Correction IA si réponse fournie
            $earnedPoints = 0;
            $iaFeedback   = 'Pas de réponse fournie.';
            
            if (!empty($userAnswer) && strlen($userAnswer) > 10) {
                $correctionPrompt = <<<PROMPT
Tu es un correcteur d'examen expert pour les concours burkinabè.

QUESTION : {$q['question']}
RÉPONSE MODÈLE : {$modelAnswer}
MOTS-CLÉS ATTENDUS : {$keywordsText}

RÉPONSE DE L'ÉTUDIANT : {$userAnswer}

TÂCHE : Note cette réponse sur {$maxPoints} points.
- Évalue la pertinence, la précision et la complétude.
- Vérifie si les mots-clés importants sont présents.
- Donne une note entière entre 0 et {$maxPoints}.

RÉPONDS EN JSON :
{
  "note": 3,
  "commentaire": "Explication de la note...",
  "points_forts": ["..."],
  "points_ameliorer": ["..."]
}
PROMPT;
                $keywordsText = implode(', ', $keywords);
                $correctionPrompt = str_replace('{$keywordsText}', $keywordsText, $correctionPrompt);
                
                $iaRaw      = call_ia($correctionPrompt, null, '', true);
                $iaCorrect  = json_decode($iaRaw, true);
                
                if ($iaCorrect && isset($iaCorrect['note'])) {
                    $earnedPoints = min(max((int)$iaCorrect['note'], 0), $maxPoints);
                    $iaFeedback   = $iaCorrect['commentaire'] ?? 'Correction disponible.';
                    $totalScore  += $earnedPoints;
                }
            }
            
            $sectionResults[] = [
                'id'             => $q['id'],
                'question'       => $q['question'],
                'user_answer'    => $userAnswer,
                'model_answer'   => $modelAnswer,
                'points_earned'  => $earnedPoints,
                'points_max'     => $maxPoints,
                'ia_feedback'    => $iaFeedback,
            ];
        }
    }
    
    $correctedSections[] = [
        'section_id'    => $section['section_id'],
        'section_title' => $section['section_title'],
        'type'          => $section['type'],
        'questions'     => $sectionResults,
    ];
}

$percentage   = $totalPossible > 0 ? round(($totalScore / $totalPossible) * 100, 1) : 0;
$examTitle    = $examData['exam_title'] ?? 'Examen Blanc';

$appreciation = match(true) {
    $percentage >= 80 => '🏆 Admis avec mention ! Excellente performance.',
    $percentage >= 60 => '✅ Admis ! Tu dépasses la barre de 60%. Continues ainsi.',
    $percentage >= 50 => '⚠️ Limite d\'admission. Il faut encore travailler.',
    default           => '❌ Non admis cette fois. Révise et recommence !',
};

// Sauvegarder le résultat
try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("
        INSERT INTO exam_white_results (user_id, exam_name, score, total_questions, score_percentage, duration, details, date_taken)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $totalQuestions = array_sum(array_map(fn($s) => count($s['questions']), $correctedSections));
    
    $stmt->execute([
        $userId,
        $examTitle,
        $totalScore,
        $totalQuestions,
        $percentage,
        $durationTaken,
        json_encode($correctedSections),
    ]);
    
    jsonResponse([
        'success'       => true,
        'score'         => $totalScore,
        'total_possible'=> $totalPossible,
        'percentage'    => $percentage,
        'appreciation'  => $appreciation,
        'duration_taken'=> $durationTaken,
        'sections'      => $correctedSections,
    ]);
    
} catch (PDOException $e) {
    logError('Erreur exam_white_submit: ' . $e->getMessage());
    jsonResponse(['error' => 'Erreur lors de la correction de l\'examen.'], 500);
}
