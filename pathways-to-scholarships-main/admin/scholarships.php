<?php
declare(strict_types=1);
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/scholarship_validator.php';
require_once __DIR__ . '/../includes/audit.php';
start_app_session();
require_role(['Administrator', 'Super-admin']);

$actionMessage = null;
$actionError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $actionError = 'Invalid or expired form token. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) $ids = [];
        $idErrors = validate_scholarship_ids($ids);
        $ids = array_values(array_unique(array_filter(array_map(static function ($v) {
            return filter_var($v, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 0;
        }, $ids))));
        if (!in_array($action, ['archive', 'verify'], true) || $idErrors || !$ids || count($ids) > 500) {
            $actionError = 'Choose a valid action and at least one scholarship (maximum 500).';
        } else {
            try {
                $pdo->beginTransaction();
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = $action === 'archive'
                    ? "UPDATE scholarships SET status='archived', updated_by=? WHERE scholarship_id IN ($placeholders)"
                    : "UPDATE scholarships SET verification_date=CURRENT_DATE(), updated_by=? WHERE scholarship_id IN ($placeholders)";
                $condition = $action === 'archive' ? "status <> 'archived'" : '(verification_date IS NULL OR verification_date <> CURRENT_DATE())';
                $findChanged = $pdo->prepare("SELECT scholarship_id FROM scholarships WHERE scholarship_id IN ($placeholders) AND $condition FOR UPDATE");
                $findChanged->execute($ids);
                $changedIds = array_map('intval', $findChanged->fetchAll(PDO::FETCH_COLUMN));
                $changed = count($changedIds);
                if ($changedIds) {
                    $changedPlaceholders = implode(',', array_fill(0, $changed, '?'));
                    $sql = $action === 'archive'
                        ? "UPDATE scholarships SET status='archived', updated_by=? WHERE scholarship_id IN ($changedPlaceholders)"
                        : "UPDATE scholarships SET verification_date=CURRENT_DATE(), updated_by=? WHERE scholarship_id IN ($changedPlaceholders)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(array_merge([current_user_id()], $changedIds));
                    foreach ($changedIds as $recordId) {
                        log_admin_action($pdo, $action, 'scholarships', $recordId, current_user_id());
                    }
                }
                $pdo->commit();
                $_SESSION['scholarship_action_message'] = "$changed scholarship record(s) updated.";
                header('Location: scholarships.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Scholarship admin action: '.$e->getMessage());
                $actionError = 'Could not complete the action. Check the PHP error log.';
            }
        }
    }
}
if (isset($_SESSION['scholarship_action_message'])) {
    $actionMessage = (string)$_SESSION['scholarship_action_message'];
    unset($_SESSION['scholarship_action_message']);
}

$rows = [];
$error = null;
try {
    $stmt = $pdo->query("SELECT s.scholarship_id, s.scholarship_name, s.status, s.application_deadline,
        s.verification_date, s.contributing_student, s.import_batch_id,
        COALESCE(p.provider_name, '') AS provider_name,
        COALESCE(st.name, '') AS state_name,
        COALESCE(nt.theme_name, '') AS theme_name
        FROM scholarships s
        LEFT JOIN providers p ON p.provider_id = s.provider_id
        LEFT JOIN states st ON st.state_id = s.state_id
        LEFT JOIN national_themes nt ON nt.national_theme_id = s.national_theme_id
        ORDER BY s.scholarship_id DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Unable to load scholarships. Check the database connection and schema.';
}
$states = []; $students = []; $batches = []; $themes = [];
foreach ($rows as $r) {
    foreach ([['state_name', &$states], ['contributing_student', &$students], ['import_batch_id', &$batches], ['theme_name', &$themes]] as $pair) {
        $value = trim((string)($r[$pair[0]] ?? ''));
        if ($value !== '') $pair[1][$value] = $value;
    }
}
foreach ([&$states, &$students, &$batches, &$themes] as &$values) natcasesort($values);
unset($values);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Scholarships | Pathways to Scholarships</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/2.2.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-primary"><div class="container"><a class="navbar-brand" href="../index.php">Pathways to Scholarships</a><a class="btn btn-outline-light btn-sm" href="../scholarships.php">Public scholarship list</a></div></nav>
<main class="container py-4">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><h1 class="h3 m-0">Manage Scholarships</h1><a class="btn btn-primary" href="scholarship_form.php">Create Scholarship</a></div>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= h($error) ?></div><?php endif; ?>
<?php if ($actionMessage): ?><div class="alert alert-success"><?= h($actionMessage) ?></div><?php endif; ?>
<?php if ($actionError): ?><div class="alert alert-danger"><?= h($actionError) ?></div><?php endif; ?>
<div class="row g-3 mb-3">
<?php foreach ([['state','State',$states],['student','Contributing student',$students],['batch','Import batch',$batches],['theme','National theme',$themes]] as $filter): ?>
<div class="col-sm-6 col-lg-3"><label class="form-label" for="filter-<?= h($filter[0]) ?>"><?= h($filter[1]) ?></label><select class="form-select" id="filter-<?= h($filter[0]) ?>"><option value="">All</option><?php foreach ($filter[2] as $value): ?><option value="<?= h($value) ?>"><?= h($value) ?></option><?php endforeach; ?></select></div>
<?php endforeach; ?>
</div>
<form id="bulkForm" method="post" onsubmit="return confirm('Apply this action to the selected scholarships?');">
<?= csrf_field() ?>
<div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
<select class="form-select w-auto" name="action" required><option value="">Choose action</option><option value="archive">Archive selected</option><option value="verify">Verify selected</option></select>
<button class="btn btn-outline-primary" type="submit">Apply to selected</button>
<span class="text-muted small">Select one or more rows, including across pages.</span>
</div>
<div class="table-responsive"><table id="scholarshipTable" class="table table-striped table-bordered align-middle w-100"><thead><tr><th><input type="checkbox" id="selectAll" aria-label="Select all visible scholarships"></th><th>ID</th><th>Scholarship</th><th>Provider</th><th>State</th><th>Student</th><th>Batch</th><th>Theme</th><th>Deadline</th><th>Status</th><th>Verified</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
<td><input class="row-check" type="checkbox" name="ids[]" value="<?= (int)$r['scholarship_id'] ?>" aria-label="Select scholarship <?= (int)$r['scholarship_id'] ?>"></td><td><?= (int)$r['scholarship_id'] ?></td>
<td><?= h($r['scholarship_name']) ?></td>
<td><?= h($r['provider_name']) ?></td>
<td><?= h($r['state_name']) ?></td>
<td><?= h($r['contributing_student']) ?></td>
<td><?= h($r['import_batch_id']) ?></td>
<td><?= h($r['theme_name']) ?></td>
<td><?= h($r['application_deadline']) ?></td>
<td><?= h($r['status']) ?></td>
<td><?= $r['verification_date'] ? h($r['verification_date']) : 'Not verified' ?></td>
<td><div class="d-flex gap-1 flex-wrap">
<a class="btn btn-sm btn-outline-primary" href="scholarship_view.php?id=<?= (int)$r['scholarship_id'] ?>">View</a>
<a class="btn btn-sm btn-outline-secondary" href="scholarship_form.php?id=<?= (int)$r['scholarship_id'] ?>">Edit</a>
<?php if ($r['status'] !== 'archived'): ?>
<button type="submit" form="row-action-<?= (int)$r['scholarship_id'] ?>-archive" class="btn btn-sm btn-outline-danger" onclick="return confirm('Archive this scholarship?');">Archive</button>
<?php endif; ?>
<?php if (!$r['verification_date']): ?>
<button type="submit" form="row-action-<?= (int)$r['scholarship_id'] ?>-verify" class="btn btn-sm btn-outline-success" onclick="return confirm('Verify this scholarship?');">Verify</button>
<?php endif; ?>
</div></td>
</tr><?php endforeach; ?>
</tbody></table></div></form>
<?php // Independent POST forms reuse the existing CSRF-checked action handler. ?>
<?php foreach ($rows as $r): ?>
<?php foreach (['archive','verify'] as $rowAction): ?>
<form id="row-action-<?= (int)$r['scholarship_id'] ?>-<?= $rowAction ?>" method="post" class="d-none">
<?= csrf_field() ?>
<input type="hidden" name="action" value="<?= $rowAction ?>">
<input type="hidden" name="ids[]" value="<?= (int)$r['scholarship_id'] ?>">
</form>
<?php endforeach; ?>
<?php endforeach; ?>
</main>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.2.2/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.2.2/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function () {
 const table = $('#scholarshipTable').DataTable({pageLength:10, order:[[1,'desc']], scrollX:true});
 const filters = {state:4, student:5, batch:6, theme:7};
 for (const [name, column] of Object.entries(filters)) {
  $('#filter-' + name).on('change', function () {
   const selected = this.value;
   table.column(column).search(selected ? '^' + $.fn.dataTable.util.escapeRegex(selected) + '$' : '', true, false).draw();
  });
 }
 $('#selectAll').on('change', function () { $(table.rows({search:'applied',page:'current'}).nodes()).find('.row-check').prop('checked',this.checked); });
 table.on('draw',function() { $('#selectAll').prop('checked',false); });
 $('#bulkForm').on('submit', function() {
   // DataTables can detach nonvisible rows; explicitly attach their selected IDs.
   $(this).find('input.extra-selected').remove();
   const form=this;
   table.$('.row-check:checked').each(function() {
     if (!$.contains(form,this)) $('<input>',{type:'hidden',name:'ids[]',value:this.value,class:'extra-selected'}).appendTo(form);
   });
 });
});
</script>
</body></html>
