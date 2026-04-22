<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Récupérer les statistiques globales
try {
    // Nombre total d'utilisateurs
    $stmtUsers = $pdo->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmtUsers->fetchColumn();

    // Nombre d'utilisateurs premium
    $stmtPremium = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'premium'");
    $premiumUsers = $stmtPremium->fetchColumn();

    // Revenus (Paiements complétés)
    $stmtRevenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'completed'");
    $totalRevenue = $stmtRevenue->fetchColumn() ?: 0;

    // Conversations du jour
    $stmtConv = $pdo->query("SELECT COUNT(*) FROM conversations WHERE DATE(created_at) = CURDATE()");
    $dailyConversations = $stmtConv->fetchColumn();

    // Derniers paiements
    $stmtLastPayments = $pdo->query("
        SELECT p.amount, p.status, p.created_at, u.email 
        FROM payments p 
        JOIN users u ON p.user_id = u.id 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ");
    $lastPayments = $stmtLastPayments->fetchAll(PDO::FETCH_ASSOC);

    // Données pour le graphique (inscriptions des 7 derniers jours)
    $stmtChart = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM users 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ");
    $chartData = $stmtChart->fetchAll(PDO::FETCH_ASSOC);
    
    $dates = [];
    $counts = [];
    foreach ($chartData as $row) {
        $dates[] = $row['date'];
        $counts[] = $row['count'];
    }
} catch (PDOException $e) {
    die("Erreur de récupération des statistiques.");
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pb-5">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom border-secondary">
        <h1 class="h2">Tableau de Bord</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button type="button" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-calendar3 me-1"></i> Aujourd'hui
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card border-left-primary h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Utilisateurs</div>
                            <div class="h3 mb-0 fw-bold text-white"><?= number_format($totalUsers, 0, ',', ' ') ?></div>
                        </div>
                        <div class="col-auto text-primary stat-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card border-left-success h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Utilisateurs Premium</div>
                            <div class="h3 mb-0 fw-bold text-white"><?= number_format($premiumUsers, 0, ',', ' ') ?></div>
                        </div>
                        <div class="col-auto text-success stat-icon">
                            <i class="bi bi-star-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card border-left-info h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Revenus (FCFA)</div>
                            <div class="h3 mb-0 fw-bold text-white"><?= number_format($totalRevenue, 0, ',', ' ') ?></div>
                        </div>
                        <div class="col-auto text-info stat-icon">
                            <i class="bi bi-currency-exchange"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card stat-card border-left-warning h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Conversations (Aujourd'hui)</div>
                            <div class="h3 mb-0 fw-bold text-white"><?= number_format($dailyConversations, 0, ',', ' ') ?></div>
                        </div>
                        <div class="col-auto text-warning stat-icon">
                            <i class="bi bi-chat-dots-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts and Tables -->
    <div class="row">
        <!-- Area Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Inscriptions (7 derniers jours)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 300px;">
                        <canvas id="myAreaChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Derniers Paiements</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($lastPayments)): ?>
                                    <tr><td colspan="3" class="text-center py-3 text-muted">Aucun paiement récent</td></tr>
                                <?php endif; ?>
                                <?php foreach($lastPayments as $payment): ?>
                                <tr>
                                    <td class="small text-truncate" style="max-width: 120px;" title="<?= htmlspecialchars($payment['email']) ?>">
                                        <?= htmlspecialchars($payment['email']) ?>
                                    </td>
                                    <td class="fw-bold text-success"><?= number_format($payment['amount'], 0, ',', ' ') ?></td>
                                    <td>
                                        <?php if($payment['status'] == 'completed'): ?>
                                            <span class="badge bg-success">Payé</span>
                                        <?php elseif($payment['status'] == 'pending'): ?>
                                            <span class="badge bg-warning text-dark">En attente</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Échoué</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 text-center border-top border-secondary">
                        <a href="payments.php" class="btn btn-sm btn-outline-primary">Voir tous les paiements</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

</div>
</div>

<!-- Chart.js Setup -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById("myAreaChart");
    var myLineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($dates) ?>,
            datasets: [{
                label: "Nouveaux Utilisateurs",
                lineTension: 0.3,
                backgroundColor: "rgba(13, 110, 253, 0.05)",
                borderColor: "rgba(13, 110, 253, 1)",
                pointRadius: 3,
                pointBackgroundColor: "rgba(13, 110, 253, 1)",
                pointBorderColor: "rgba(13, 110, 253, 1)",
                pointHoverRadius: 5,
                pointHoverBackgroundColor: "rgba(13, 110, 253, 1)",
                pointHoverBorderColor: "rgba(13, 110, 253, 1)",
                pointHitRadius: 10,
                pointBorderWidth: 2,
                data: <?= json_encode($counts) ?>,
                fill: true
            }],
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: { left: 10, right: 25, top: 25, bottom: 0 }
            },
            scales: {
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { maxTicksLimit: 7, color: '#adb5bd' }
                },
                y: {
                    ticks: { maxTicksLimit: 5, padding: 10, color: '#adb5bd' },
                    grid: { color: "rgba(255, 255, 255, 0.1)", drawBorder: false, borderDash: [2, 2] }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
