<?php
declare(strict_types=1);

// Scholarship Validation Report
// Local development version

$config = require __DIR__ . '/../config/config.php';

// Restrict this development page to localhost.
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

if (
    empty($config['dev_mode']) ||
    !in_array($clientIp, ['127.0.0.1', '::1'], true)
) {
    http_response_code(403);
    exit('Access denied.');
}

// Load database and security helpers
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();

// Get batch ID from the URL
$batchId = filter_input(
    INPUT_GET,
    'batch_id',
    FILTER_VALIDATE_INT
);

$batch = null;
$rows = [];
$error = '';

$statuses = [
    'Ready' => 0,
    'Warning' => 0,
    'Duplicate' => 0,
    'Error' => 0
];

$colors = [
    'Ready' => 'success',
    'Warning' => 'warning',
    'Duplicate' => 'secondary',
    'Error' => 'danger'
];

try {
    if ($batchId !== null && $batchId !== false && $batchId > 0) {

        // Retrieve import batch
        $stmt = $pdo->prepare(
            "SELECT *
             FROM import_batches
             WHERE batch_id = ?"
        );

        $stmt->execute([$batchId]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($batch) {

            // Retrieve staged scholarship records
            $stmt = $pdo->prepare(
                "SELECT *
                 FROM import_staging_rows
                 WHERE batch_id = ?
                 ORDER BY row_number, staging_id"
            );

            $stmt->execute([$batchId]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate validation status totals
            foreach ($rows as $row) {
                $status = $row['row_status'];

                if (array_key_exists($status, $statuses)) {
                    $statuses[$status]++;
                }
            }

        } else {
            $error = 'Import batch not found.';
        }
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Unable to load the validation report.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Scholarship Validation Report</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <span class="navbar-brand">
            Pathways to Scholarships
        </span>
    </div>
</nav>

<main class="container my-5">

    <h1>Scholarship Validation Report</h1>

    <p class="text-muted">
        Review scholarship records before importing them.
    </p>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger">
            <?= h($error) ?>
        </div>

    <?php elseif ($batch === null): ?>

        <div class="alert alert-info">
            No import batch selected.
            Upload a scholarship file first.
        </div>

    <?php else: ?>

        <div class="card mb-4">
            <div class="card-body">

                <strong>File:</strong>
                <?= h($batch['file_name']) ?>

                <br>

                <strong>Batch ID:</strong>
                <?= (int) $batch['batch_id'] ?>

                <br>

                <strong>Total Records:</strong>
                <?= count($rows) ?>

            </div>
        </div>

        <!-- Validation Summary -->

        <div class="row g-3 mb-4">

            <?php foreach ($statuses as $status => $count): ?>

                <div class="col-md-3">

                    <div class="card border-<?= $colors[$status] ?>">

                        <div class="card-body">

                            <h5><?= h($status) ?></h5>

                            <h2><?= (int) $count ?></h2>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

        <!-- Validation Details -->

        <div class="card">

            <div class="card-header">
                Validation Results
            </div>

            <div class="table-responsive">

                <table class="table table-striped mb-0">

                    <thead>
                        <tr>
                            <th>Row</th>
                            <th>Scholarship Data</th>
                            <th>Status</th>
                            <th>Explanation</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($rows as $row): ?>

                            <?php
                            $status = $row['row_status'];
                            $color = $colors[$status] ?? 'secondary';

                            $data = json_decode(
                                $row['raw_data'],
                                true
                            );

                            $displayData = is_array($data)
                                ? json_encode(
                                    $data,
                                    JSON_UNESCAPED_UNICODE |
                                    JSON_INVALID_UTF8_SUBSTITUTE
                                )
                                : $row['raw_data'];
                            ?>

                            <tr>

                                <td>
                                    <?= (int) $row['row_number'] ?>
                                </td>

                                <td>
                                    <small>
                                        <?= h($displayData ?? '') ?>
                                    </small>
                                </td>

                                <td>
                                    <span class="badge bg-<?= $color ?>">
                                        <?= h($status) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= h(
                                        $row['error_message']
                                        ?: 'No issues found.'
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($rows)): ?>

                            <tr>
                                <td colspan="4"
                                    class="text-center">
                                    No validation records found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    <?php endif; ?>

</main>

</body>
</html>
