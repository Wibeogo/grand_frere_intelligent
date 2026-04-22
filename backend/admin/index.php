<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/db_updater.php';

try {
    $pdo = getDB();
    runAdminDbUpdates($pdo);
} catch (Exception $e) {
    die("Erreur base de données.");
}

// Redirection si déjà connecté
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        $stmt = $pdo->prepare("SELECT id, password, role FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            
            // Maj last_login
            $upd = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
            $upd->execute([$admin['id']]);

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Identifiants incorrects.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Admin GFI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #121212;
            color: #e0e0e0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            background-color: #1e2124;
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
        }
        .form-control {
            background-color: #2b2f33;
            border: 1px solid #3c4146;
            color: #fff;
            padding: 0.8rem;
        }
        .form-control:focus {
            background-color: #2b2f33;
            color: #fff;
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .btn-primary {
            padding: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>

<div class="login-card text-center">
    <i class="bi bi-robot text-primary mb-3" style="font-size: 3.5rem;"></i>
    <h3 class="fw-bold mb-4">Admin Panel</h3>
    <p class="text-muted mb-4">Grand Frère Intelligent</p>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php">
        <div class="mb-3 text-start">
            <label class="form-label text-secondary small">Adresse Email</label>
            <input type="email" name="email" class="form-control rounded-3" placeholder="admin@tiragepromobf.com" required>
        </div>
        <div class="mb-4 text-start">
            <label class="form-label text-secondary small">Mot de passe</label>
            <input type="password" name="password" class="form-control rounded-3" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 rounded-3">Se connecter</button>
    </form>
</div>

</body>
</html>
