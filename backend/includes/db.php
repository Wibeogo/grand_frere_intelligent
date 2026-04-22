<?php
/**
 * includes/db.php – Connexion PDO à la base de données MySQL
 * 
 * Charge les variables d'environnement via vlucas/phpdotenv
 * et retourne une instance PDO réutilisable.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Charger le fichier .env depuis la racine du backend
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Valider les variables obligatoires
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'JWT_SECRET']);

/**
 * Retourne une connexion PDO (singleton simple).
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $host    = $_ENV['DB_HOST'] ?? 'localhost';
        $port    = $_ENV['DB_PORT'] ?? '3306';
        $dbname  = $_ENV['DB_NAME'];
        $user    = $_ENV['DB_USER'];
        $pass    = $_ENV['DB_PASS'];
        
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Journaliser sans exposer les détails
            logError('Erreur connexion BDD : ' . $e->getMessage());
            jsonResponse(['error' => 'Erreur de connexion au serveur.'], 500);
            exit;
        }
    }
    
    return $pdo;
}

/**
 * Envoie une réponse JSON et termine l'exécution.
 */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Journalise une erreur dans logs/error.log
 */
function logError(string $message): void {
    $logDir = __DIR__ . '/../logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $uri = $_SERVER['REQUEST_URI'] ?? 'N/A';
    $logLine = "[{$timestamp}] [{$ip}] [{$uri}] {$message}" . PHP_EOL;
    file_put_contents($logDir . 'error.log', $logLine, FILE_APPEND | LOCK_EX);
}

// Initialiser la connexion au chargement du fichier
getDB();
