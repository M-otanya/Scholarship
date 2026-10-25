<?php
declare(strict_types=1);
require __DIR__ . '/../includes/db.php';

$stmt = $pdo->query(
    'SELECT batch_id, file_name, contributing_student, uploaded_at, total_rows, '
    . 'ready_rows, warning_rows, duplicate_rows, error_rows, status '
    . 'FROM import_batches ORDER BY uploaded_at DESC, batch_id DESC'
);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

function history_escape(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>
<main class="container py-5">
    <h1>Import history</h1>
    <p class="text-muted">Previous scholarship uploads and their validation results.</p>
    <?php if (!$batches): ?>
        <p>No import batches found.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered table-sm align-middle">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>File</th>
                        <th>Contributing student</th>
                        <th>Uploaded</th>
                        <th>Total rows</th>
                        <th>Ready</th>
                        <th>Warning</th>
                        <th>Duplicate</th>
                        <th>Error</th>
                        <th>Status</th>
                        <th>Review</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches as $batch): ?>
                        <tr>
                            <td><?= (int)$batch['batch_id'] ?></td>
                            <td><?= history_escape($batch['file_name']) ?></td>
                            <td><?= history_escape($batch['contributing_student']) ?></td>
                            <td><?= history_escape($batch['uploaded_at']) ?></td>
                            <td><?= (int)$batch['total_rows'] ?></td>
                            <td><?= (int)$batch['ready_rows'] ?></td>
                            <td><?= (int)$batch['warning_rows'] ?></td>
                            <td><?= (int)$batch['duplicate_rows'] ?></td>
                            <td><?= (int)$batch['error_rows'] ?></td>
                            <td><?= history_escape($batch['status']) ?></td>
                            <td><a href="validate.php?batch_id=<?= (int)$batch['batch_id'] ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <a class="btn btn-outline-primary" href="import.php">Upload scholarships</a>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
