<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$message = '';
$messageType = 'success';

// Dossier de téléchargement
$uploadDir = __DIR__ . '/../uploads/documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Traitement de l'upload ou suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'upload') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        
        if (empty($title) || !isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            $message = "Veuillez fournir un titre et sélectionner un fichier valide.";
            $messageType = "danger";
        } else {
            $fileInfo = pathinfo($_FILES['document']['name']);
            $ext = strtolower($fileInfo['extension'] ?? '');
            
            if ($ext !== 'pdf') {
                $message = "Seuls les fichiers PDF sont autorisés pour le RAG.";
                $messageType = "danger";
            } else {
                // Générer un nom unique
                $filename = uniqid('rag_') . '.pdf';
                $destination = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['document']['tmp_name'], $destination)) {
                    // Sauvegarde en DB
                    try {
                        $stmt = $pdo->prepare("INSERT INTO documents (title, filename, category, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $title, 
                            $fileInfo['basename'], 
                            $category, 
                            'uploads/documents/' . $filename,
                            $_SESSION['admin_id']
                        ]);
                        $message = "Document téléchargé et indexé avec succès.";
                    } catch (PDOException $e) {
                        unlink($destination); // Supprimer le fichier si erreur DB
                        $message = "Erreur base de données lors de l'enregistrement.";
                        $messageType = "danger";
                    }
                } else {
                    $message = "Erreur lors de la copie du fichier sur le serveur.";
                    $messageType = "danger";
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $docId = (int)$_POST['doc_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
            $stmt->execute([$docId]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doc) {
                // Supprimer le fichier physique
                $filePath = __DIR__ . '/../' . $doc['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Supprimer de la DB
                $del = $pdo->prepare("DELETE FROM documents WHERE id = ?");
                $del->execute([$docId]);
                $message = "Document supprimé avec succès.";
            }
        } catch (PDOException $e) {
            $message = "Erreur lors de la suppression.";
            $messageType = "danger";
        }
    }
}

// Récupération des documents
try {
    $stmt = $pdo->query("SELECT d.*, a.email as admin_email FROM documents d LEFT JOIN admins a ON d.uploaded_by = a.id ORDER BY d.created_at DESC");
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de récupération des documents.");
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pb-5">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-4 border-bottom border-secondary">
        <h1 class="h2">Base de Connaissances (RAG)</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Formulaire d'upload -->
        <div class="col-md-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-cloud-arrow-up me-2"></i>Nouveau Document</h6>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload">
                        
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Titre du document</label>
                            <input type="text" name="title" class="form-control bg-dark text-white border-secondary" required placeholder="Ex: Sujet SVT Bac 2023">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Catégorie</label>
                            <select name="category" class="form-select bg-dark text-white border-secondary">
                                <option value="Sujets">Sujets d'Examen</option>
                                <option value="Cours">Cours / Leçons</option>
                                <option value="Correction">Corrections</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-secondary small">Fichier (PDF uniquement)</label>
                            <input class="form-control bg-dark text-white border-secondary" type="file" name="document" accept=".pdf" required>
                            <div class="form-text text-muted">Ce fichier sera utilisé par l'IA pour générer des réponses (RAG).</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-upload me-1"></i> Uploader et Indexer</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Liste des documents -->
        <div class="col-md-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-file-earmark-pdf me-2"></i>Documents Indexés</h6>
                    <span class="badge bg-primary rounded-pill"><?= count($documents) ?></span>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Catégorie</th>
                                    <th>Ajouté par</th>
                                    <th>Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($documents)): ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted">Aucun document dans la base de connaissances.</td></tr>
                                <?php endif; ?>
                                
                                <?php foreach($documents as $doc): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($doc['title']) ?>">
                                            <i class="bi bi-filetype-pdf text-danger me-1"></i>
                                            <?= htmlspecialchars($doc['title']) ?>
                                        </div>
                                        <div class="small text-muted text-truncate" style="max-width: 200px;"><?= htmlspecialchars($doc['filename']) ?></div>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($doc['category']) ?></span></td>
                                    <td class="small text-muted"><?= htmlspecialchars($doc['admin_email'] ?? 'Inconnu') ?></td>
                                    <td class="small text-muted"><?= date('d/m/Y', strtotime($doc['created_at'])) ?></td>
                                    <td class="text-end">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer définitivement ce document de la base IA ?');">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
