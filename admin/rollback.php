<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/audit.php';
start_app_session();

$batchId = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
if (!$batchId || $batchId < 1) {
    http_response_code(400);
    exit('Invalid batch ID.');
}

function rollback_escape(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$batchStmt = $pdo->prepare('SELECT batch_id, file_name, status FROM import_batches WHERE batch_id = ?');
$batchStmt->execute([$batchId]);
$batch = $batchStmt->fetch(PDO::FETCH_ASSOC);
if (!$batch) {
    http_response_code(404);
    exit('Batch not found.');
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM scholarships WHERE import_batch_id = ?');
$countStmt->execute([$batchId]);
$scholarshipCount = (int)$countStmt->fetchColumn();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
    if (($batch['status'] ?? '') !== 'committed') {
        $error = 'Only committed batches can be rolled back.';
    } else {
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare('SELECT status FROM import_batches WHERE batch_id = ? FOR UPDATE');
            $lock->execute([$batchId]);
            if ($lock->fetchColumn() !== 'committed') {
                throw new RuntimeException('Batch is no longer committed.');
            }

            // Remove only relationships belonging to scholarships in this batch.
            foreach (['saved_scholarships', 'scholarship_categories', 'scholarship_fields', 'scholarship_grades'] as $table) {
                $delete = $pdo->prepare("DELETE FROM `$table` WHERE scholarship_id IN (SELECT scholarship_id FROM scholarships WHERE import_batch_id = ?)");
                $delete->execute([$batchId]);
            }
            $deleteScholarships = $pdo->prepare('DELETE FROM scholarships WHERE import_batch_id = ?');
            $deleteScholarships->execute([$batchId]);
            $deleted = $deleteScholarships->rowCount();

            $update = $pdo->prepare("UPDATE import_batches SET status = 'rolled_back' WHERE batch_id = ? AND status = 'committed'");
            $update->execute([$batchId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Could not update batch status.');
            }
            log_admin_action($pdo, 'rollback', 'import_batches', $batchId, 1 /* existing development administrator user_id */);
            $pdo->commit();
            $batch['status'] = 'rolled_back';
            $scholarshipCount = 0;
            $message = "Rollback completed. Removed $deleted scholarships from batch $batchId.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Batch rollback ' . $batchId . ': ' . $e->getMessage());
            $error = 'Rollback failed. No changes were saved. Check the PHP error log.';
        }
    }
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-5" style="max-width:850px">
    <h1>Rollback scholarship batch</h1>
    <p><strong>File:</strong> <?= rollback_escape($batch['file_name']) ?> |
       <strong>Batch:</strong> <?= (int)$batchId ?> |
       <strong>Status:</strong> <?= rollback_escape($batch['status']) ?></p>
    <?php if ($message): ?><div class="alert alert-success"><?= rollback_escape($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= rollback_escape($error) ?></div><?php endif; ?>
    <p>Scholarships currently linked to this batch: <strong><?= $scholarshipCount ?></strong></p>
    <?php if ($batch['status'] === 'committed'): ?>
        <div class="alert alert-warning">This will permanently remove scholarships imported in this batch and their associated saved, category, field, and grade records. Other batches will not be changed.</div>
        <form method="post">
            <?= csrf_field() ?>
            <button class="btn btn-danger" type="submit" onclick="return confirm('Rollback this batch and delete its imported scholarships? This cannot be undone.');">Confirm rollback</button>
        </form>
    <?php else: ?>
        <p>This batch is not available for rollback. Only committed batches can be rolled back.</p>
    <?php endif; ?>
    <p class="mt-4"><a href="validate.php?batch_id=<?= (int)$batchId ?>">Back to staging review</a></p>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
