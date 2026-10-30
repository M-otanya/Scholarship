<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';
start_app_session();
require_role(['Administrator', 'Super-admin']);

$errors = [];
$notice = '';
$selectedId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
$selected = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Refresh the page and try again.';
    } elseif (!in_array($action, ['edit', 'merge'], true)) {
        $errors[] = 'Invalid action.';
    } else {
        $providerId = filter_var($_POST['provider_id'] ?? null, FILTER_VALIDATE_INT);
        $targetId = filter_var($_POST['target_id'] ?? null, FILTER_VALIDATE_INT);
        $providerName = trim((string)($_POST['provider_name'] ?? ''));
        $providerType = trim((string)($_POST['provider_type'] ?? ''));
        if (!$providerId || $providerId < 1) $errors[] = 'Choose a valid provider.';
        if ($action === 'edit') {
            if ($providerName === '' || mb_strlen($providerName) > 150) $errors[] = 'Provider name must be 1–150 characters.';
            if (mb_strlen($providerType) > 100) $errors[] = 'Provider type must be 100 characters or fewer.';
        } elseif (!$targetId || $targetId < 1 || $targetId === $providerId) {
            $errors[] = 'Select a different provider to keep.';
        }
        if (!$errors) {
            try {
                $pdo->beginTransaction();
                $q = $pdo->prepare('SELECT provider_id FROM providers WHERE provider_id = ? FOR UPDATE');
                $q->execute([$providerId]);
                if (!$q->fetchColumn()) throw new RuntimeException('The selected provider no longer exists.');
                if ($action === 'edit') {
                    $dup = $pdo->prepare('SELECT provider_id FROM providers WHERE LOWER(provider_name) = LOWER(?) AND provider_id <> ? LIMIT 1');
                    $dup->execute([$providerName, $providerId]);
                    if ($dup->fetchColumn()) throw new RuntimeException('Another provider already has that name. Use Merge instead.');
                    $q = $pdo->prepare('UPDATE providers SET provider_name = ?, provider_type = ? WHERE provider_id = ?');
                    $q->execute([$providerName, $providerType !== '' ? $providerType : null, $providerId]);
                    log_admin_action($pdo, 'edit', 'providers', $providerId, current_user_id());
                } else {
                    $q->execute([$targetId]);
                    if (!$q->fetchColumn()) throw new RuntimeException('The provider to keep no longer exists.');
                    $q = $pdo->prepare('UPDATE scholarships SET provider_id = ? WHERE provider_id = ?');
                    $q->execute([$targetId, $providerId]);
                    // Log before removing the duplicate; the audit entry preserves its original ID.
                    log_admin_action($pdo, 'provider_merge', 'providers', $providerId, current_user_id());
                    $q = $pdo->prepare('DELETE FROM providers WHERE provider_id = ?');
                    $q->execute([$providerId]);
                }
                $pdo->commit();
                header('Location: providers.php?success=' . ($action === 'edit' ? 'edited' : 'merged'));
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = $e instanceof PDOException ? 'Database error. Check whether the provider is referenced by other records.' : $e->getMessage();
            }
        }
        if ($action === 'edit' && $providerId) $selectedId = (int)$providerId;
    }
}

if (isset($_GET['success'])) {
    $notice = $_GET['success'] === 'edited' ? 'Provider updated.' : ($_GET['success'] === 'merged' ? 'Providers merged successfully.' : '');
}
$providers = $pdo->query('SELECT p.provider_id, p.provider_name, p.provider_type, COUNT(s.provider_id) AS scholarship_count FROM providers p LEFT JOIN scholarships s ON s.provider_id = p.provider_id GROUP BY p.provider_id, p.provider_name, p.provider_type ORDER BY p.provider_name')->fetchAll(PDO::FETCH_ASSOC);
foreach ($providers as $provider) {
    if ((int)$provider['provider_id'] === $selectedId) $selected = $provider;
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Manage Providers</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><main class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3">Manage Providers</h1><a class="btn btn-outline-secondary" href="scholarships.php">Back to scholarships</a></div>
<?php if ($notice): ?><div class="alert alert-success"><?= h($notice) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>ID</th><th>Provider</th><th>Type</th><th>Scholarships</th><th>Action</th></tr></thead><tbody>
<?php foreach ($providers as $p): ?><tr><td><?= (int)$p['provider_id'] ?></td><td><?= h($p['provider_name']) ?></td><td><?= h($p['provider_type'] ?? '—') ?></td><td><?= (int)$p['scholarship_count'] ?></td><td><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int)$p['provider_id'] ?>">Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($selected): ?><section class="card p-3 my-4"><h2 class="h5">Edit provider #<?= (int)$selected['provider_id'] ?></h2><form method="post" class="row g-3">
<input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="edit"><input type="hidden" name="provider_id" value="<?= (int)$selected['provider_id'] ?>">
<div class="col-md-6"><label class="form-label" for="provider_name">Provider name</label><input class="form-control" id="provider_name" name="provider_name" maxlength="150" required value="<?= h($selected['provider_name']) ?>"></div>
<div class="col-md-6"><label class="form-label" for="provider_type">Provider type (optional)</label><input class="form-control" id="provider_type" name="provider_type" maxlength="100" value="<?= h($selected['provider_type'] ?? '') ?>"></div>
<div class="col-12"><button class="btn btn-primary" type="submit">Save changes</button> <a class="btn btn-outline-secondary" href="providers.php">Cancel</a></div></form></section><?php endif; ?>
<section class="card p-3 my-4"><h2 class="h5">Merge duplicate providers</h2><p class="text-muted">All scholarships belonging to the duplicate will be assigned to the provider you keep. The duplicate provider will be deleted. This cannot be undone from this page.</p>
<form method="post" id="mergeForm" class="row g-3"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="merge">
<div class="col-md-6"><label class="form-label" for="provider_id">Duplicate provider to remove</label><select class="form-select" name="provider_id" id="provider_id" required><option value="">Select duplicate</option><?php foreach ($providers as $p): ?><option value="<?= (int)$p['provider_id'] ?>"><?= h($p['provider_name']) ?> (#<?= (int)$p['provider_id'] ?>)</option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label" for="target_id">Correct provider to keep</label><select class="form-select" name="target_id" id="target_id" required><option value="">Select provider to keep</option><?php foreach ($providers as $p): ?><option value="<?= (int)$p['provider_id'] ?>"><?= h($p['provider_name']) ?> (#<?= (int)$p['provider_id'] ?>)</option><?php endforeach; ?></select></div>
<div class="col-12"><button class="btn btn-danger" type="submit">Merge providers</button></div></form></section>
</main><script>document.getElementById('mergeForm').addEventListener('submit',function(e){const from=document.getElementById('provider_id').value,to=document.getElementById('target_id').value;if(from===to){e.preventDefault();alert('Choose two different providers.');return;}if(!confirm('Merge the duplicate into the provider to keep? This will delete the duplicate provider.'))e.preventDefault();});</script></body></html>
