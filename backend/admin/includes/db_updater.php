<?php
/**
 * db_updater.php - Script exécuté automatiquement côté admin
 * pour créer les tables requises (admins, documents) si elles n'existent pas.
 */

function runAdminDbUpdates(PDO $pdo) {
    try {
        // Création table admins
        $sqlAdmins = "CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('superadmin', 'admin') DEFAULT 'admin',
            last_login DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $pdo->exec($sqlAdmins);

        // Création table documents (RAG)
        $sqlDocs = "CREATE TABLE IF NOT EXISTS documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            category VARCHAR(100) NULL,
            file_path VARCHAR(500) NOT NULL,
            uploaded_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES admins(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $pdo->exec($sqlDocs);

        // Vérifier si un compte admin existe, sinon créer admin@tiragepromobf.com / password123
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
        if ($stmt->fetchColumn() == 0) {
            $defaultEmail = 'admin@tiragepromobf.com';
            $defaultPass = password_hash('password123', PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO admins (email, password, role) VALUES (?, ?, 'superadmin')");
            $insert->execute([$defaultEmail, $defaultPass]);
        }
    } catch (PDOException $e) {
        // En prod, logguer l'erreur au lieu de l'afficher
        error_log("Erreur DB Updater Admin: " . $e->getMessage());
    }
}
