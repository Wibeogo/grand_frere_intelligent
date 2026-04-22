<?php
/**
 * auth.php - Gestion de l'authentification Admin
 */
session_start();

// Remonter d'un dossier pour accéder au db.php principal
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/db_updater.php';

try {
    $pdo = getDB();
    // Toujours vérifier que les tables admin existent
    runAdminDbUpdates($pdo);
} catch (Exception $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

function isLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit;
    }
}

function getAdminUser($pdo) {
    if (!isLoggedIn()) return null;
    $stmt = $pdo->prepare("SELECT id, email, role FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
