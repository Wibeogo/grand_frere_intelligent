<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$message = '';
$messageType = 'success';
$envFile = __DIR__ . '/../.env';

// Si .env n'existe pas mais .env.example oui, on le copie
if (!file_exists($envFile) && file_exists(__DIR__ . '/../.env.example')) {
    copy(__DIR__ . '/../.env.example', $envFile);
}

// Fonction utilitaire pour lire le .env
function getEnvVariables($path) {
    $vars = [];
    if (file_exists($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2) + [NULL, NULL];
            if ($name !== NULL && $value !== NULL) {
                $vars[trim($name)] = trim($value);
            }
        }
    }
    return $vars;
}

// Traitement de la sauvegarde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_env') {
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES);
        $newLines = [];
        
        $updates = [
            'IA_API_URL' => $_POST['IA_API_URL'] ?? '',
            'RATE_LIMIT_FREE' => (int)($_POST['RATE_LIMIT_FREE'] ?? 50),
            'RATE_LIMIT_PREMIUM' => (int)($_POST['RATE_LIMIT_PREMIUM'] ?? 200),
            'APP_DEBUG' => isset($_POST['APP_DEBUG']) ? 'true' : 'false'
        ];

        $updatedKeys = [];
        foreach ($lines as $line) {
            $isUpdated = false;
            foreach ($updates as $key => $value) {
                // Si la ligne commence par la clé
                if (preg_match("/^$key=/", trim($line))) {
                    $newLines[] = "$key=$value";
                    $updatedKeys[] = $key;
                    $isUpdated = true;
                    break;
                }
            }
            if (!$isUpdated) {
                $newLines[] = $line;
            }
        }
        
        // Ajouter les clés manquantes à la fin
        foreach ($updates as $key => $value) {
            if (!in_array($key, $updatedKeys)) {
                $newLines[] = "$key=$value";
            }
        }
        
        if (file_put_contents($envFile, implode(PHP_EOL, $newLines))) {
            $message = "Les paramètres ont été mis à jour avec succès.";
        } else {
            $message = "Impossible d'écrire dans le fichier .env. Vérifiez les permissions (chmod 644).";
            $messageType = "danger";
        }
    } else {
        $message = "Le fichier .env est introuvable.";
        $messageType = "danger";
    }
}

$envVars = getEnvVariables($envFile);

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pb-5">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom border-secondary">
        <h1 class="h2">Paramètres de l'Application</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-xl-8 col-lg-10 mb-4">
            <div class="card shadow border-secondary bg-dark">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-gear-fill me-2"></i>Configuration Environnement (.env)</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save_env">
                        
                        <h5 class="text-light border-bottom border-secondary pb-2 mb-3 mt-2"><i class="bi bi-robot me-2 text-info"></i>Intelligence Artificielle</h5>
                        
                        <div class="mb-4">
                            <label class="form-label text-secondary fw-bold">URL de l'API IA (Ollama / Colab Cloudflare Tunnel)</label>
                            <input type="url" name="IA_API_URL" class="form-control bg-dark text-white border-primary" 
                                   value="<?= htmlspecialchars($envVars['IA_API_URL'] ?? '') ?>" 
                                   placeholder="https://votre-url-colab.trycloudflare.com" required>
                            <div class="form-text text-warning mt-2">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                <strong>Important :</strong> Cette URL change à chaque fois que vous redémarrez Google Colab. N'oubliez pas de la mettre à jour ici.
                            </div>
                        </div>

                        <h5 class="text-light border-bottom border-secondary pb-2 mb-3 mt-5"><i class="bi bi-speedometer2 me-2 text-warning"></i>Limites d'utilisation (Rate Limiting)</h5>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-secondary">Requêtes max / jour (Comptes Gratuits)</label>
                                <input type="number" name="RATE_LIMIT_FREE" class="form-control bg-dark text-white border-secondary" 
                                       value="<?= htmlspecialchars($envVars['RATE_LIMIT_FREE'] ?? 50) ?>">
                            </div>
                            <div class="col-md-6 mt-3 mt-md-0">
                                <label class="form-label text-secondary">Requêtes max / jour (Comptes Premium)</label>
                                <input type="number" name="RATE_LIMIT_PREMIUM" class="form-control bg-dark text-white border-secondary" 
                                       value="<?= htmlspecialchars($envVars['RATE_LIMIT_PREMIUM'] ?? 200) ?>">
                            </div>
                        </div>

                        <h5 class="text-light border-bottom border-secondary pb-2 mb-3 mt-5"><i class="bi bi-bug me-2 text-danger"></i>Développement</h5>
                        
                        <div class="mb-4 form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="appDebug" name="APP_DEBUG" 
                                   <?= (isset($envVars['APP_DEBUG']) && $envVars['APP_DEBUG'] === 'true') ? 'checked' : '' ?>>
                            <label class="form-check-label text-secondary" for="appDebug">
                                Activer le Mode Debug (APP_DEBUG)
                            </label>
                            <div class="form-text text-muted">Affiche les erreurs détaillées de PHP. À désactiver en production.</div>
                        </div>

                        <hr class="border-secondary mt-5 mb-4">
                        
                        <button type="submit" class="btn btn-primary px-5"><i class="bi bi-save me-2"></i> Enregistrer les modifications</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
