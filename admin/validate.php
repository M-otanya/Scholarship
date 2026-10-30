<?php
declare(strict_types=1);
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/import_validator.php';
require __DIR__ . '/../includes/security.php';
start_app_session();
$id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid batch ID'); }
$batchQuery = $pdo->prepare('SELECT batch_id, file_name, total_rows, uploaded_at FROM import_batches WHERE batch_id = ?');
$batchQuery->execute([$id]);
$batch = $batchQuery->fetch(PDO::FETCH_ASSOC);
if (!$batch) { http_response_code(404); exit('Batch not found'); }
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Invalid CSRF token'); }
    try { import_validate_batch($pdo, (int)$id); $notice = 'Validation completed. No scholarships were published.'; }
    catch (Throwable $e) { error_log($e->getMessage()); $notice = 'Validation failed. Check PHP logs and verify reference tables.'; }
}
$stmt = $pdo->prepare('SELECT row_number, raw_data, row_status, error_message FROM import_staging_rows WHERE batch_id = ? ORDER BY staging_id');
$stmt->execute([$id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
function import_review_escape($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-5">
<h1>Staging review</h1>
<p><strong>File:</strong> <?= import_review_escape($batch['file_name']) ?> | <strong>Rows:</strong> <?= count($rows) ?> | <strong>Batch:</strong> <?= import_review_escape($id) ?></p>
<div class="alert alert-info">Rows stay in staging. Click Validate batch to run field checks and duplicate detection. No records are published.</div>
<?php if ($notice): ?><div class="alert alert-secondary"><?= import_review_escape($notice) ?></div><?php endif; ?>
<form method="post" class="mb-4"><?= csrf_field() ?><button class="btn btn-primary" type="submit">Validate batch</button></form>
<?php $counts = array_count_values(array_column($rows, 'row_status')); ?>
<p><strong>Ready:</strong> <?= (int)($counts['Ready'] ?? 0) ?> &nbsp; <strong>Warning:</strong> <?= (int)($counts['Warning'] ?? 0) ?> &nbsp; <strong>Duplicate:</strong> <?= (int)($counts['Duplicate'] ?? 0) ?> &nbsp; <strong>Error:</strong> <?= (int)($counts['Error'] ?? 0) ?></p>
<div class="table-responsive"><table class="table table-striped table-bordered table-sm"><thead><tr><th>CSV row</th><th>Status</th><th>Validation reasons</th><th>Raw scholarship data</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?>
<tr><td><?= import_review_escape($row['row_number']) ?></td><td><?= import_review_escape($row['row_status']) ?></td><td><?= import_review_escape($row['error_message'] ?: '—') ?></td><td><pre style="white-space:pre-wrap;overflow-wrap:anywhere"><?= import_review_escape(json_encode(json_decode($row['raw_data'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php
// Get the latest batch status
$statusStmt = $pdo->prepare(
    'SELECT status FROM import_batches WHERE batch_id = ?'
);
$statusStmt->execute([$id]);
$batchStatus = $statusStmt->fetchColumn();

// Count rows eligible for committing
$eligibleRows = (int)($counts['Ready'] ?? 0)
              + (int)($counts['Warning'] ?? 0);
?>

<?php if ($batchStatus === 'validated' && $eligibleRows > 0): ?>
    <a class="btn btn-success me-2"
       href="commit.php?batch_id=<?= (int)$id ?>">
        Commit Batch
    </a>
<?php endif; ?>
<a class="btn btn-outline-primary" href="import.php">Upload another CSV</a>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
