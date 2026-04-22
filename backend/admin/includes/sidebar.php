        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="sidebar-sticky pt-3">
                
                <div class="text-center mb-4 mt-2">
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-2" style="width: 60px; height: 60px; font-size: 24px;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div class="small text-muted">Connecté en tant que</div>
                    <div class="fw-bold"><?= htmlspecialchars($adminUser['email'] ?? 'Admin') ?></div>
                </div>

                <hr class="text-secondary">

                <ul class="nav flex-column mb-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : '' ?>" aria-current="page" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            Tableau de Bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'active' : '' ?>" href="users.php">
                            <i class="bi bi-people"></i>
                            Utilisateurs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'documents.php') ? 'active' : '' ?>" href="documents.php">
                            <i class="bi bi-file-earmark-pdf"></i>
                            Documents RAG
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'payments.php') ? 'active' : '' ?>" href="payments.php">
                            <i class="bi bi-currency-exchange"></i>
                            Paiements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'quiz.php') ? 'active' : '' ?>" href="quiz.php">
                            <i class="bi bi-journal-check"></i>
                            Quiz & Stats
                        </a>
                    </li>
                </ul>

                <hr class="text-secondary mt-5">
                
                <ul class="nav flex-column mb-2">
                    <li class="nav-item">
                        <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : '' ?>" href="settings.php">
                            <i class="bi bi-gear"></i>
                            Paramètres
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
