<?php
/**
 * includes/rate_limit.php – Limitation du nombre de requêtes
 * 
 * Implémente un rate limiting par fenêtre glissante d'1 heure.
 * - Utilisateurs gratuits : 50 requêtes/heure
 * - Utilisateurs premium  : 200 requêtes/heure
 */

require_once __DIR__ . '/db.php';

/**
 * Vérifie et enregistre une requête pour le rate limiting.
 * Termine l'exécution avec une erreur 429 si la limite est atteinte.
 * 
 * @param int    $userId   ID de l'utilisateur
 * @param string $userType 'free' ou 'premium'
 * @param string $endpoint Nom de l'endpoint (ex: 'chat_send')
 */
function checkRateLimit(int $userId, string $userType, string $endpoint): void {
    $pdo   = getDB();
    $limit = ($userType === 'premium')
        ? (int)($_ENV['RATE_LIMIT_PREMIUM'] ?? 200)
        : (int)($_ENV['RATE_LIMIT_FREE'] ?? 50);
    
    // Fenêtre de 1 heure
    $windowStart = date('Y-m-d H:00:00'); // Heure courante arrondie
    
    try {
        // Upsert : incrémenter ou créer le compteur
        $stmt = $pdo->prepare("
            INSERT INTO rate_limiting (user_id, endpoint, request_count, window_start)
            VALUES (?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE request_count = request_count + 1
        ");
        $stmt->execute([$userId, $endpoint, $windowStart]);
        
        // Vérifier le compteur actuel
        $stmt = $pdo->prepare("
            SELECT request_count FROM rate_limiting
            WHERE user_id = ? AND endpoint = ? AND window_start = ?
        ");
        $stmt->execute([$userId, $endpoint, $windowStart]);
        $row = $stmt->fetch();
        
        if ($row && (int)$row['request_count'] > $limit) {
            $resetAt = date('H:i', strtotime($windowStart) + 3600);
            jsonResponse([
                'error'    => 'Limite de requêtes atteinte.',
                'message'  => "Tu as atteint ta limite de {$limit} requêtes/heure. Réinitialisation à {$resetAt}.",
                'limit'    => $limit,
                'used'     => (int)$row['request_count'],
                'reset_at' => $resetAt,
                'code'     => 'RATE_LIMIT_EXCEEDED'
            ], 429);
        }
        
    } catch (PDOException $e) {
        // En cas d'erreur de BDD, on laisse passer (fail open)
        logError('Rate limit error: ' . $e->getMessage());
    }
}

/**
 * Nettoie les anciennes entrées de rate limiting (à appeler depuis un cron).
 * Supprime les entrées de plus de 2 heures.
 */
function cleanRateLimitTable(): void {
    $pdo = getDB();
    $stmt = $pdo->prepare("DELETE FROM rate_limiting WHERE window_start < ?");
    $stmt->execute([date('Y-m-d H:i:s', strtotime('-2 hours'))]);
}
