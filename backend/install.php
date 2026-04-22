<?php
/**
 * install.php – Création des tables MySQL pour Grand Frère Intelligent
 * 
 * UTILISATION : Accéder à https://votredomaine.com/api/install.php UNE SEULE FOIS
 * puis SUPPRIMER ce fichier immédiatement après l'installation.
 * 
 * IMPORTANT : Ce script crée toutes les tables nécessaires.
 * Si les tables existent déjà, elles ne seront pas recréées (IF NOT EXISTS).
 */

// Charger les variables d'environnement
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Protection basique : nécessite un paramètre secret en GET
$installSecret = $_GET['secret'] ?? '';
if ($installSecret !== ($_ENV['JWT_SECRET'] ?? 'changeme')) {
    http_response_code(403);
    die(json_encode(['error' => 'Accès refusé. Paramètre secret manquant ou incorrect.']));
}

try {
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};port={$_ENV['DB_PORT']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'],
        $_ENV['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $tables = [];

    // Table : users
    $tables[] = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        full_name VARCHAR(255),
        user_type ENUM('free', 'premium') DEFAULT 'free',
        subscription_expiry DATETIME NULL,
        free_trial_start DATETIME NULL,
        free_trial_end DATETIME NULL,
        concours_cible VARCHAR(100) NULL COMMENT 'Concours ciblé par l utilisateur',
        fcm_token TEXT NULL COMMENT 'Token Firebase Cloud Messaging',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Table : conversations
    $tables[] = "CREATE TABLE IF NOT EXISTS conversations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        is_user TINYINT(1) DEFAULT 1 COMMENT '1 = message utilisateur, 0 = réponse IA',
        image_url VARCHAR(500) NULL,
        audio_url VARCHAR(500) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Table : quiz_scores
    $tables[] = "CREATE TABLE IF NOT EXISTS quiz_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        score INT NOT NULL DEFAULT 0,
        total INT NOT NULL DEFAULT 10,
        category VARCHAR(100) NOT NULL COMMENT 'Nom du concours ou matière',
        details JSON NULL COMMENT 'Détail des réponses en JSON',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Table : daily_quiz_log
    $tables[] = "CREATE TABLE IF NOT EXISTS daily_quiz_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        quiz_date DATE NOT NULL,
        question TEXT NOT NULL,
        correct_answer VARCHAR(500) NULL,
        user_answer VARCHAR(500) NULL,
        is_correct TINYINT(1) NULL,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Table : exam_white_results
    $tables[] = "CREATE TABLE IF NOT EXISTS exam_white_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        exam_name VARCHAR(255) NOT NULL,
        score INT NOT NULL DEFAULT 0,
        total_questions INT NOT NULL,
        score_percentage DECIMAL(5,2) NULL,
        duration INT NULL COMMENT 'Durée réelle en minutes',
        details JSON NULL COMMENT 'Réponses et corrections en JSON',
        date_taken DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Table : payments
    $tables[] = "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        amount INT NOT NULL DEFAULT 2800,
        plan_type VARCHAR(20) DEFAULT 'monthly',
        transaction_id VARCHAR(255) NULL,
        senfenico_reference VARCHAR(255) NULL COMMENT 'Référence checkout Senfenico',
        status ENUM('pending','completed','failed','cancelled') DEFAULT 'pending',
        payment_method VARCHAR(50) NULL COMMENT 'orange_money, moov_money, etc.',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Table : push_subscriptions (pour FCM)
    $tables[] = "CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        fcm_token TEXT NOT NULL,
        device_type VARCHAR(20) NULL COMMENT 'android, ios',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Table : rate_limiting
    $tables[] = "CREATE TABLE IF NOT EXISTS rate_limiting (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        endpoint VARCHAR(100) NOT NULL,
        request_count INT DEFAULT 1,
        window_start DATETIME NOT NULL,
        UNIQUE KEY unique_rate (user_id, endpoint, window_start),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // Exécuter toutes les requêtes
    $results = [];
    foreach ($tables as $sql) {
        $pdo->exec($sql);
        // Extraire le nom de la table
        preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $matches);
        $results[] = "✅ Table '{$matches[1]}' créée ou déjà existante.";
    }

    echo "<pre style='font-family:monospace; background:#1a1a2e; color:#00ff88; padding:20px; font-size:14px;'>";
    echo "🎓 GRAND FRÈRE INTELLIGENT – Installation de la base de données\n";
    echo str_repeat("=", 60) . "\n\n";
    foreach ($results as $r) {
        echo $r . "\n";
    }
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ Installation terminée avec succès !\n\n";
    echo "⚠️  IMPORTANT : Supprimez ce fichier (install.php) immédiatement !\n";
    echo "   rm install.php  ou  via le gestionnaire de fichiers Hostinger\n";
    echo "</pre>";

} catch (PDOException $e) {
    http_response_code(500);
    echo "<pre style='background:#1a1a2e; color:#ff4444; padding:20px;'>";
    echo "❌ Erreur de connexion ou de création :\n";
    echo $e->getMessage();
    echo "</pre>";
}
