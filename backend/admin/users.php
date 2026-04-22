<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$message = '';
$messageType = 'success';

// Traitement des actions (Passer en Premium)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $userId = (int)$_POST['user_id'];
    
    if ($_POST['action'] === 'make_premium') {
        $days = (int)($_POST['days'] ?? 30);
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET user_type = 'premium', 
                    subscription_expiry = DATE_ADD(IFNULL(subscription_expiry, NOW()), INTERVAL ? DAY) 
                WHERE id = ?
            ");
            if ($stmt->execute([$days, $userId])) {
                $message = "L'utilisateur a été mis à niveau vers Premium pour $days jours.";
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de la mise à jour.";
            $messageType = "danger";
        }
    } elseif ($_POST['action'] === 'revoke_premium') {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET user_type = 'free', 
                    subscription_expiry = NULL 
                WHERE id = ?
            ");
            if ($stmt->execute([$userId])) {
                $message = "Le statut Premium a été révoqué pour cet utilisateur.";
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de la mise à jour.";
            $messageType = "danger";
        }
    }
}

// Recherche
$search = $_GET['search'] ?? '';
$where = '';
$params = [];
if (!empty($search)) {
    $where = "WHERE email LIKE ? OR full_name LIKE ? OR phone LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

// Récupération des utilisateurs
try {
    $stmt = $pdo->prepare("SELECT * FROM users $where ORDER BY created_at DESC LIMIT 100");
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur base de données.");
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pb-5">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom border-secondary">
        <h1 class="h2">Gestion des Utilisateurs</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Liste des Utilisateurs (Top 100)</h6>
            
            <div class="d-flex">
                <form class="d-flex me-2" method="GET" action="users.php">
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                        <?php if(!empty($search)): ?>
                            <a href="users.php" class="btn btn-outline-danger"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
                <button type="button" class="btn btn-sm btn-success" onclick="exportTableToCSV('utilisateurs.csv')">
                    <i class="bi bi-file-earmark-excel me-1"></i> Excel/CSV
                </button>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Email & Nom</th>
                            <th>Téléphone</th>
                            <th>Statut</th>
                            <th>Expiration Premium</th>
                            <th>Inscription</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($users)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">Aucun utilisateur trouvé</td></tr>
                        <?php endif; ?>
                        
                        <?php foreach($users as $u): ?>
                            <?php 
                                $isPremium = ($u['user_type'] === 'premium');
                                // Verifier si l'abonnement est vraiment actif
                                $isActive = false;
                                if ($isPremium && !empty($u['subscription_expiry'])) {
                                    $expiry = new DateTime($u['subscription_expiry']);
                                    $now = new DateTime();
                                    $isActive = ($expiry > $now);
                                }
                            ?>
                        <tr>
                            <td>#<?= $u['id'] ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($u['full_name'] ?? 'Non renseigné') ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($u['phone']) ?></td>
                            <td>
                                <?php if($isActive): ?>
                                    <span class="badge bg-success"><i class="bi bi-star-fill me-1"></i>Premium</span>
                                <?php elseif($isPremium): ?>
                                    <span class="badge bg-danger">Expiré</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Gratuit</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?= !empty($u['subscription_expiry']) ? date('d/m/Y H:i', strtotime($u['subscription_expiry'])) : '-' ?>
                            </td>
                            <td class="small text-muted">
                                <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                            </td>
                            <td class="text-end">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow">
                                        <li><h6 class="dropdown-header">Gestion Abonnement</h6></li>
                                        <li>
                                            <form method="POST" class="px-3 py-1">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="action" value="make_premium">
                                                <div class="input-group input-group-sm mb-2">
                                                    <input type="number" name="days" class="form-control" value="30" min="1">
                                                    <span class="input-group-text">Jours</span>
                                                </div>
                                                <button type="submit" class="btn btn-sm btn-success w-100"><i class="bi bi-star me-1"></i> Ajouter Premium</button>
                                            </form>
                                        </li>
                                        <?php if($isPremium): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="action" value="revoke_premium">
                                                <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Retirer le premium ?');">
                                                    <i class="bi bi-x-circle me-1"></i> Révoquer Premium
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
