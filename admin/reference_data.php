<?php
declare(strict_types=1);

/** Iteration 7: Super-admin reference-data management. */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// The shared auth helper enforces session expiry and account activation.
// It redirects unauthenticated visitors and denies non-Super-admin roles.
require_role(['Super-admin']);
$userId = current_user_id();
if ($userId === null) {
    http_response_code(401);
    exit('Please log in.');
}

// Every table and column below is hardcoded and allowlisted.
// Never use raw GET or POST input as an SQL table/column name.
$tables = [
    'states' => [
        'title' => 'States',
        'id' => 'state_id',
        'fields' => [
            'name' => ['label' => 'State name', 'type' => 'text', 'max' => 100],
            'abbreviation' => ['label' => 'Abbreviation', 'type' => 'text', 'max' => 10],
        ],
    ],
    'grade_levels' => [
        'title' => 'Grade Levels',
        'id' => 'grade_id',
        'fields' => [
            'grade_number' => ['label' => 'Grade number (1–12)', 'type' => 'number', 'max' => 2],
        ],
    ],
    'categories' => [
        'title' => 'Scholarship Categories',
        'id' => 'category_id',
        'fields' => [
            'category_name' => ['label' => 'Category name', 'type' => 'text', 'max' => 100],
        ],
    ],
    'fields_of_study' => [
        'title' => 'Fields of Study',
        'id' => 'field_id',
        'fields' => [
            'field_name' => ['label' => 'Field name', 'type' => 'text', 'max' => 100],
        ],
    ],
    'national_themes' => [
        'title' => 'National Themes',
        'id' => 'national_theme_id',
        'fields' => [
            'theme_name' => ['label' => 'Theme name', 'type' => 'text', 'max' => 150],
        ],
    ],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestedTable = $method === 'POST'
    ? ($_POST['table'] ?? null)
    : ($_GET['table'] ?? 'states');
if (!is_string($requestedTable) || !isset($tables[$requestedTable])) {
    http_response_code(400);
    exit('Invalid reference-data section.');
}
$table = $requestedTable;
$config = $tables[$table];
$idColumn = $config['id'];
$fields = $config['fields'];
$errors = [];
$postedValues = [];
$postedAction = '';
$postedRecordId = null;

// Process create, edit, and delete as POST requests only.
if ($method === 'POST') {
    $postedAction = is_string($_POST['action'] ?? null)
        ? $_POST['action'] : '';
    $postedRecordId = filter_var(
        $_POST['record_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your form has expired. Refresh the page and try again.';
    }
    if (!in_array($postedAction, ['create', 'update', 'delete'], true)) {
        $errors[] = 'Invalid form action.';
    }
    if ($postedAction !== 'create' && (!$postedRecordId || $postedRecordId === false)) {
        $errors[] = 'Invalid record ID.';
    }

    if (in_array($postedAction, ['create', 'update'], true)) {
        foreach ($fields as $column => $field) {
            $raw = $_POST[$column] ?? null;
            if (!is_string($raw)) {
                $errors[] = $field['label'] . ' is required.';
                continue;
            }
            $value = trim($raw);
            $postedValues[$column] = $value;
            if ($value === '') {
                $errors[] = $field['label'] . ' cannot be empty.';
                continue;
            }
            if ($field['type'] === 'number') {
                $number = filter_var(
                    $value,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1, 'max_range' => 12]]
                );
                if ($number === false) {
                    $errors[] = 'Grade number must be between 1 and 12.';
                } else {
                    $postedValues[$column] = $number;
                }
            } elseif ((function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value)) > $field['max']) {
                $errors[] = $field['label'] . ' is too long.';
            }
        }
        if ($table === 'states' && isset($postedValues['abbreviation'])) {
            $postedValues['abbreviation'] = strtoupper((string) $postedValues['abbreviation']);
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Check that edited or deleted records actually exist.
            if ($postedAction !== 'create') {
                $exists = $pdo->prepare(
                    "SELECT `$idColumn` FROM `$table` WHERE `$idColumn` = :id FOR UPDATE"
                );
                $exists->execute([':id' => $postedRecordId]);
                if (!$exists->fetchColumn()) {
                    throw new RuntimeException('Record no longer exists.');
                }
            }

            if ($postedAction === 'create') {
                $columns = array_keys($fields);
                $columnSql = implode(', ', array_map(fn($c) => "`$c`", $columns));
                $paramSql = implode(', ', array_map(fn($c) => ":$c", $columns));
                $stmt = $pdo->prepare(
                    "INSERT INTO `$table` ($columnSql) VALUES ($paramSql)"
                );
                $stmt->execute($postedValues);
                $changedId = (int) $pdo->lastInsertId();
            } elseif ($postedAction === 'update') {
                $assignments = implode(', ', array_map(
                    fn($c) => "`$c` = :$c", array_keys($fields)
                ));
                $stmt = $pdo->prepare(
                    "UPDATE `$table` SET $assignments WHERE `$idColumn` = :record_id"
                );
                $stmt->execute($postedValues + ['record_id' => $postedRecordId]);
                $changedId = (int) $postedRecordId;
            } else {
                // Foreign-key constraints block deletion of values in use.
                $stmt = $pdo->prepare(
                    "DELETE FROM `$table` WHERE `$idColumn` = :record_id"
                );
                $stmt->execute([':record_id' => $postedRecordId]);
                $changedId = (int) $postedRecordId;
            }

            // This project has a generic audit_log table; log every change.
            $audit = $pdo->prepare(
                'INSERT INTO audit_log (user_id, action, table_name, record_id, details)
                 VALUES (:user_id, :action, :table_name, :record_id, :details)'
            );
            $audit->execute([
                ':user_id' => $userId,
                ':action' => 'reference_' . $postedAction,
                ':table_name' => $table,
                ':record_id' => $changedId,
                ':details' => 'Reference data ' . $postedAction . ' via admin management page',
            ]);

            $pdo->commit();
            $_SESSION['reference_data_message'] = 'Reference data saved successfully.';
            header('Location: reference_data.php?table=' . rawurlencode($table), true, 303);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Reference data change: ' . $e->getMessage());
            if ((string) $e->getCode() === '23000') {
                $errors[] = $postedAction === 'delete'
                    ? 'Cannot delete this item because it is used by other records.'
                    : 'This value already exists or violates a database constraint.';
            } else {
                $errors[] = 'Unable to save changes. Check the server log.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Reference data change: ' . $e->getMessage());
            $errors[] = 'Unable to complete the requested change.';
        }
    }
}

// Load table rows for display and determine which record to edit.
try {
    $rows = $pdo->query(
        "SELECT * FROM `$table` ORDER BY `$idColumn` ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Reference data list: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load reference data.');
}

$editId = filter_var(
    $_GET['edit'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
$editing = null;
if ($editId) {
    foreach ($rows as $row) {
        if ((int) $row[$idColumn] === (int) $editId) {
            $editing = $row;
            break;
        }
    }
    if ($editing === null) {
        $errors[] = 'The selected record was not found.';
    }
}
if ($method === 'POST' && $errors && in_array($postedAction, ['create', 'update'], true)) {
    $editing = $postedAction === 'update' && $postedRecordId
        ? array_merge([$idColumn => $postedRecordId], $postedValues)
        : null;
}

$message = $_SESSION['reference_data_message'] ?? '';
unset($_SESSION['reference_data_message']);

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>

<main class="container my-5">
    <h1>Reference Data Management</h1>
    <p class="text-muted">Super-admin access only. Maintain the lookup values used throughout the scholarship system.</p>

    <?php if ($message): ?>
        <div class="alert alert-success" role="status"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <nav aria-label="Reference data sections" class="d-flex flex-wrap gap-2 mb-4">
        <?php foreach ($tables as $key => $section): ?>
            <a class="btn btn-sm <?= $key === $table ? 'btn-primary' : 'btn-outline-primary' ?>"
               href="reference_data.php?table=<?= urlencode($key) ?>">
                <?= h($section['title']) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">
                <?= $editing ? 'Edit' : 'Add' ?> <?= h($config['title']) ?>
            </h2>
            <form method="post" action="reference_data.php?table=<?= urlencode($table) ?><?= $editing ? '&amp;edit=' . (int) $editing[$idColumn] : '' ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="table" value="<?= h($table) ?>">
                <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
                <?php if ($editing): ?>
                    <input type="hidden" name="record_id" value="<?= (int) $editing[$idColumn] ?>">
                <?php endif; ?>

                <?php foreach ($fields as $column => $field): ?>
                    <div class="mb-3">
                        <label class="form-label" for="field_<?= h($column) ?>">
                            <?= h($field['label']) ?>
                        </label>
                        <input
                            class="form-control"
                            type="<?= $field['type'] === 'number' ? 'number' : 'text' ?>"
                            id="field_<?= h($column) ?>"
                            name="<?= h($column) ?>"
                            value="<?= h($postedValues[$column] ?? ($editing[$column] ?? '')) ?>"
                            <?php if ($field['type'] === 'number'): ?>min="1" max="12"<?php else: ?>maxlength="<?= (int) $field['max'] ?>"<?php endif; ?>
                            required
                        >
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-primary">
                    <?= $editing ? 'Save Changes' : 'Add Record' ?>
                </button>
                <?php if ($editing): ?>
                    <a class="btn btn-outline-secondary ms-2" href="reference_data.php?table=<?= urlencode($table) ?>">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
    </section>

    <section class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Existing <?= h($config['title']) ?></h2>
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <?php foreach ($fields as $field): ?>
                                <th><?= h($field['label']) ?></th>
                            <?php endforeach; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= (int) $row[$idColumn] ?></td>
                                <?php foreach (array_keys($fields) as $column): ?>
                                    <td><?= h($row[$column]) ?></td>
                                <?php endforeach; ?>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="reference_data.php?table=<?= urlencode($table) ?>&amp;edit=<?= (int) $row[$idColumn] ?>">
                                            Edit
                                        </a>
                                        <form method="post" action="reference_data.php?table=<?= urlencode($table) ?>"
                                              onsubmit="return confirm('Delete this reference value? This cannot be undone.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="table" value="<?= h($table) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="record_id" value="<?= (int) $row[$idColumn] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?>
                            <tr><td colspan="<?= count($fields) + 2 ?>" class="text-muted">No records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
