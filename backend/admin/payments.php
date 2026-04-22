<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$message = '';
$messageType = 'success';

// Action: Validation manuelle d'un paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'validate_payment') {
    $paymentId = (int)$_POST['payment_id'];
    $userId = (int)$_POST['user_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Mettre à jour le statut du paiement
        $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', updated_at = NOW() WHERE id = ? AND status != 'completed'");
        $stmt->execute([$paymentId]);
        
        if ($stmt->rowCount() > 0) {
            // Mettre à jour l'utilisateur (ajouter 30 jours de Premium par défaut pour un paiement validé manuellement)
            $updateUser = $pdo->prepare("
                UPDATE users 
                SET user_type = 'premium', 
                    subscription_expiry = DATE_ADD(IFNULL(subscription_expiry, NOW()), INTERVAL 30 DAY) 
                WHERE id = ?
            ");
            $updateUser->execute([$userId]);
            
            $pdo->commit();
            $message = "Paiement validé manuellement. L'abonnement Premium a été activé.";
        } else {
            $pdo->rollBack();
            $message = "Ce paiement est déjà validé ou introuvable.";
            $messageType = "warning";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Erreur lors de la validation du paiement.";
        $messageType = "danger";
    }
}

// Filtres
$filterStatus = $_GET['status'] ?? '';
$filterEmail = $_GET['email'] ?? '';

$whereClauses = [];
$params = [];

if (!empty($filterStatus)) {
    $whereClauses[] = "p.status = ?";
    $params[] = $filterStatus;
}
if (!empty($filterEmail)) {
    $whereClauses[] = "u.email LIKE ?";
    $params[] = "%$filterEmail%";
}

$whereSql = '';
if (count($whereClauses) > 0) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Récupération des paiements
try {
    $sql = "
        SELECT p.*, u.email as user_email, u.full_name 
        FROM payments p 
        LEFT JOIN users u ON p.user_id = u.id 
        $whereSql 
        ORDER BY p.created_at DESC 
        LIMIT 200
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total des revenus pour les filtres actuels
    $sqlTotal = "
        SELECT SUM(p.amount) 
        FROM payments p 
        LEFT JOIN users u ON p.user_id = u.id 
        $whereSql AND p.status = 'completed'
    ";
    $stmtTotal = $pdo->prepare($sqlTotal);
    $stmtTotal->execute($params);
    $filteredRevenue = $stmtTotal->fetchColumn() ?: 0;
    
} catch (PDOException $e) {
    die("Erreur de récupération des paiements.");
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pb-5">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom border-secondary">
        <h1 class="h2">Gestion des Paiements</h1>
        <div class="h5 mb-0 text-success fw-bold">
            Revenus (Filtrés) : <?= number_format($filteredRevenue, 0, ',', ' ') ?> FCFA
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="card shadow mb-4 bg-dark border-secondary">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-secondary small">Email utilisateur</label>
                    <input type="text" name="email" class="form-control bg-dark text-white border-secondary" placeholder="Rechercher par email" value="<?= htmlspecialchars($filterEmail) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small">Statut</label>
                    <select name="status" class="form-select bg-dark text-white border-secondary">
                        <option value="">Tous les statuts</option>
                        <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Complété (Payé)</option>
                        <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>En attente</option>
                        <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Échoué</option>
                        <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrer</button>
                    <a href="payments.php" class="btn btn-outline-secondary">Réinitialiser</a>
                    <button type="button" class="btn btn-success ms-2" onclick="exportTableToCSV('paiements.csv')"><i class="bi bi-file-earmark-excel"></i> Export CSV</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card shadow h-100">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Référence / Transaction</th>
                            <th>Utilisateur</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($payments)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted">Aucun paiement trouvé avec ces critères.</td></tr>
                        <?php endif; ?>
                        
                        <?php foreach($payments as $p): ?>
                        <tr>
                            <td>
                                <div class="fw-bold small text-monospace"><?= htmlspecialchars($p['transaction_id'] ?: 'N/A') ?></div>
                                <div class="small text-muted">Réf: <?= htmlspecialchars($p['senfenico_reference'] ?: 'N/A') ?></div>
                            </td>
                            <td>
                                <div class="text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($p['user_email']) ?>">
                                    <?= htmlspecialchars($p['user_email']) ?>
                                </div>
                            </td>
                            <td class="fw-bold"><?= number_format($p['amount'], 0, ',', ' ') ?> FCFA</td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($p['payment_method'] ?: 'Inconnu') ?></span></td>
                            <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
                            <td>
                                <?php if($p['status'] == 'completed'): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Complété</span>
                                <?php elseif($p['status'] == 'pending'): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>En attente</span>
                                <?php elseif($p['status'] == 'failed'): ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Échoué</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($p['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if($p['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="validate_payment">
                                        <input type="hidden" name="payment_id" value="<?= $p['id'] ?>">
                                        <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Valider manuellement ce paiement et accorder le statut Premium à l\'utilisateur ?');" title="Forcer la validation">
                                            <i class="bi bi-check2-all"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
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
