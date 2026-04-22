<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

// Récupération des statistiques globales des quiz
try {
    // Top catégories
    $stmtCats = $pdo->query("
        SELECT category, COUNT(*) as nb_quizzes, AVG(score/total*100) as avg_score 
        FROM quiz_scores 
        GROUP BY category 
        ORDER BY nb_quizzes DESC 
        LIMIT 5
    ");
    $topCategories = $stmtCats->fetchAll(PDO::FETCH_ASSOC);

    // Activité des quiz sur les 14 derniers jours
    $stmtActivity = $pdo->query("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM quiz_scores 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ");
    $activityData = $stmtActivity->fetchAll(PDO::FETCH_ASSOC);
    $actDates = [];
    $actCounts = [];
    foreach ($activityData as $row) {
        $actDates[] = $row['date'];
        $actCounts[] = $row['count'];
    }

    // Derniers quiz joués
    $stmtRecent = $pdo->query("
        SELECT q.*, u.email as user_email 
        FROM quiz_scores q 
        LEFT JOIN users u ON q.user_id = u.id 
        ORDER BY q.created_at DESC 
        LIMIT 20
    ");
    $recentQuizzes = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques des examens blancs
    $stmtExams = $pdo->query("
        SELECT exam_name, COUNT(*) as attempts, AVG(score_percentage) as avg_score 
        FROM exam_white_results 
        GROUP BY exam_name 
        ORDER BY attempts DESC 
        LIMIT 5
    ");
    $topExams = $stmtExams->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Erreur de récupération des statistiques des quiz.");
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pb-5">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom border-secondary">
        <h1 class="h2">Statistiques des Quiz & Examens</h1>
    </div>

    <div class="row">
        <!-- Graphique Activité -->
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Activité des Quiz (14 derniers jours)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area" style="height: 300px;">
                        <canvas id="quizActivityChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Catégories -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Matières les plus jouées</h6>
                </div>
                <div class="card-body">
                    <?php if(empty($topCategories)): ?>
                        <p class="text-muted">Aucune donnée disponible.</p>
                    <?php endif; ?>
                    
                    <?php foreach($topCategories as $cat): ?>
                        <h6 class="small font-weight-bold text-light">
                            <?= htmlspecialchars($cat['category']) ?> 
                            <span class="float-end"><?= $cat['nb_quizzes'] ?> quiz (Moy: <?= number_format($cat['avg_score'], 1) ?>%)</span>
                        </h6>
                        <div class="progress mb-4 bg-dark border border-secondary" style="height: 8px;">
                            <?php 
                            $color = 'bg-primary';
                            if ($cat['avg_score'] < 50) $color = 'bg-danger';
                            elseif ($cat['avg_score'] > 75) $color = 'bg-success';
                            ?>
                            <div class="progress-bar <?= $color ?>" role="progressbar" style="width: <?= $cat['avg_score'] ?>%"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Récents -->
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Derniers Quiz Réalisés</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Matière / Catégorie</th>
                                    <th>Score</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($recentQuizzes)): ?>
                                    <tr><td colspan="4" class="text-center py-3 text-muted">Aucun quiz enregistré.</td></tr>
                                <?php endif; ?>
                                
                                <?php foreach($recentQuizzes as $q): ?>
                                    <?php 
                                        $percent = ($q['total'] > 0) ? ($q['score'] / $q['total']) * 100 : 0;
                                        $badgeColor = 'bg-secondary';
                                        if ($percent >= 75) $badgeColor = 'bg-success';
                                        elseif ($percent < 50) $badgeColor = 'bg-danger';
                                        else $badgeColor = 'bg-warning text-dark';
                                    ?>
                                <tr>
                                    <td class="small"><?= htmlspecialchars($q['user_email']) ?></td>
                                    <td><?= htmlspecialchars($q['category']) ?></td>
                                    <td>
                                        <span class="badge <?= $badgeColor ?>"><?= $q['score'] ?> / <?= $q['total'] ?></span>
                                    </td>
                                    <td class="small text-muted"><?= date('d/m H:i', strtotime($q['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById("quizActivityChart");
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($actDates) ?>,
            datasets: [{
                label: "Quiz complétés",
                backgroundColor: "#0d6efd",
                hoverBackgroundColor: "#0b5ed7",
                borderColor: "#0d6efd",
                data: <?= json_encode($actCounts) ?>,
            }],
        },
        options: {
            maintainAspectRatio: false,
            layout: { padding: { left: 10, right: 25, top: 25, bottom: 0 } },
            scales: {
                x: { grid: { display: false, drawBorder: false }, ticks: { color: '#adb5bd' } },
                y: { ticks: { padding: 10, color: '#adb5bd', stepSize: 1 }, grid: { color: "rgba(255, 255, 255, 0.1)" } }
            },
            plugins: { legend: { display: false } }
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
