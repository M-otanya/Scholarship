<?php
declare(strict_types=1);

/**
 * Iteration 7 - Super-admin audit log viewer.
 * Read-only listing of the existing audit_log table, with filters and pagination.
 */
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

/** Validate an HTML date string before using it in a database query. */
function audit_valid_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

/** Build an escaped pagination URL preserving active search filters. */
function audit_page_url(int $page, array $filters): string
{
    return 'audit_logs.php?' . http_build_query(array_merge($filters, ['page' => $page]));
}

$errors = [];
$logs = [];
$actions = [];
$tables = [];
$total = 0;
$perPage = 20;
$page = 1;
$pages = 1;
$loadError = false;

// Read GET filters safely. All filters are optional.
$rawUserId = $_GET['user_id'] ?? '';
$rawAction = $_GET['action'] ?? '';
$rawTable = $_GET['table_name'] ?? '';
$rawFrom = $_GET['date_from'] ?? '';
$rawTo = $_GET['date_to'] ?? '';
$rawPage = $_GET['page'] ?? '1';

foreach ([$rawUserId, $rawAction, $rawTable, $rawFrom, $rawTo, $rawPage] as $value) {
    if (!is_string($value)) {
        http_response_code(400);
        exit('Invalid audit log filter.');
    }
}

$filterUserId = trim($rawUserId);
$filterAction = trim($rawAction);
$filterTable = trim($rawTable);
$dateFrom = trim($rawFrom);
$dateTo = trim($rawTo);

if ($filterUserId !== '' && (
    filter_var($filterUserId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
)) {
    $errors[] = 'User ID must be a positive whole number.';
}
if ($dateFrom !== '' && !audit_valid_date($dateFrom)) {
    $errors[] = 'Enter a valid start date.';
}
if ($dateTo !== '' && !audit_valid_date($dateTo)) {
    $errors[] = 'Enter a valid end date.';
}
if ($dateFrom !== '' && $dateTo !== '' &&
    audit_valid_date($dateFrom) && audit_valid_date($dateTo) && $dateFrom > $dateTo) {
    $errors[] = 'Start date cannot be after the end date.';
}
if (strlen($filterAction) > 100 || strlen($filterTable) > 100) {
    $errors[] = 'An audit log filter is too long.';
}
if (!ctype_digit($rawPage) || (int) $rawPage < 1) {
    $errors[] = 'Invalid page number.';
} else {
    $page = min((int) $rawPage, 1000000);
}

$filters = [
    'user_id' => $filterUserId,
    'action' => $filterAction,
    'table_name' => $filterTable,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
];

try {
    // Options come from the audit log itself, so new actions appear automatically.
    $actions = $pdo->query(
        "SELECT DISTINCT action FROM audit_log
         WHERE action IS NOT NULL AND action <> '' ORDER BY action"
    )->fetchAll(PDO::FETCH_COLUMN);
    $tables = $pdo->query(
        "SELECT DISTINCT table_name FROM audit_log
         WHERE table_name IS NOT NULL AND table_name <> '' ORDER BY table_name"
    )->fetchAll(PDO::FETCH_COLUMN);

    if ($filterAction !== '' && !in_array($filterAction, $actions, true)) {
        $errors[] = 'Invalid action filter.';
    }
    if ($filterTable !== '' && !in_array($filterTable, $tables, true)) {
        $errors[] = 'Invalid table filter.';
    }

    if (!$errors) {
        $where = [];
        $params = [];
        if ($filterUserId !== '') {
            $where[] = 'al.user_id = :user_id';
            $params[':user_id'] = (int) $filterUserId;
        }
        if ($filterAction !== '') {
            $where[] = 'al.action = :action';
            $params[':action'] = $filterAction;
        }
        if ($filterTable !== '') {
            $where[] = 'al.table_name = :table_name';
            $params[':table_name'] = $filterTable;
        }
        if ($dateFrom !== '') {
            $where[] = 'al.created_at >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = 'al.created_at < :date_to';
            $params[':date_to'] = (new DateTimeImmutable($dateTo))
                ->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM audit_log al' . $whereSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        $query = 'SELECT al.audit_id, al.user_id, al.action, al.table_name,
                         al.record_id, al.details, al.created_at, u.email
                  FROM audit_log al
                  LEFT JOIN users u ON u.user_id = al.user_id'
               . $whereSql .
                 ' ORDER BY al.created_at DESC, al.audit_id DESC
                   LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Audit log viewer: ' . $e->getMessage());
    $loadError = true;
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/nav.php';
?>

<main class="container my-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="mb-1">Audit Logs</h1>
            <p class="text-muted mb-0">Review recorded system activity. Super-admin access only.</p>
        </div>
        <a href="reference_data.php" class="btn btn-outline-secondary">Reference Data</a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($loadError): ?>
        <div class="alert alert-danger" role="alert">Unable to load audit records. Please try again.</div>
    <?php endif; ?>

    <section class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5">Filter Activity</h2>
            <form method="get" action="audit_logs.php" class="row g-3">
                <div class="col-md-2">
                    <label for="user_id" class="form-label">User ID</label>
                    <input class="form-control" type="number" min="1" step="1"
                           id="user_id" name="user_id" value="<?= h($filterUserId) ?>">
                </div>
                <div class="col-md-3">
                    <label for="action" class="form-label">Action</label>
                    <select class="form-select" id="action" name="action">
                        <option value="">All actions</option>
                        <?php foreach ($actions as $action): ?>
                            <option value="<?= h($action) ?>" <?= $filterAction === $action ? 'selected' : '' ?>>
                                <?= h($action) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="table_name" class="form-label">Table</label>
                    <select class="form-select" id="table_name" name="table_name">
                        <option value="">All tables</option>
                        <?php foreach ($tables as $tableName): ?>
                            <option value="<?= h($tableName) ?>" <?= $filterTable === $tableName ? 'selected' : '' ?>>
                                <?= h($tableName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">From</label>
                    <input class="form-control" type="date" id="date_from" name="date_from"
                           value="<?= h($dateFrom) ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">To</label>
                    <input class="form-control" type="date" id="date_to" name="date_to"
                           value="<?= h($dateTo) ?>">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                    <a class="btn btn-outline-secondary" href="audit_logs.php">Clear Filters</a>
                </div>
            </form>
        </div>
    </section>

    <?php if (!$errors && !$loadError): ?>
        <p class="text-muted">Showing <?= count($logs) ?> of <?= $total ?> matching audit records.</p>

        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead>
                    <tr>
                        <th scope="col">Log ID</th>
                        <th scope="col">Date / Time</th>
                        <th scope="col">User</th>
                        <th scope="col">Action</th>
                        <th scope="col">Table</th>
                        <th scope="col">Record ID</th>
                        <th scope="col">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= (int) $log['audit_id'] ?></td>
                            <td class="text-nowrap"><?= h($log['created_at']) ?></td>
                            <td>
                                <?php if ($log['user_id'] !== null): ?>
                                    <span class="text-nowrap">#<?= (int) $log['user_id'] ?></span>
                                    <?php if (!empty($log['email'])): ?>
                                        <div class="small text-muted"><?= h($log['email']) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    System / Unknown
                                <?php endif; ?>
                            </td>
                            <td><?= h($log['action']) ?></td>
                            <td><?= h($log['table_name'] ?? '—') ?></td>
                            <td><?= $log['record_id'] === null ? '—' : (int) $log['record_id'] ?></td>
                            <td>
                                <?php if (trim((string) ($log['details'] ?? '')) !== ''): ?>
                                    <details>
                                        <summary>View details</summary>
                                        <div class="small mt-2" style="white-space: pre-wrap; max-width: 350px;"><?= h($log['details']) ?></div>
                                    </details>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No matching audit records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <nav aria-label="Audit log pages">
                <ul class="pagination flex-wrap">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(audit_page_url(max(1, $page - 1), $filters)) ?>">Previous</a>
                    </li>
                    <li class="page-item disabled"><span class="page-link">Page <?= $page ?> of <?= $pages ?></span></li>
                    <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(audit_page_url(min($pages, $page + 1), $filters)) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
