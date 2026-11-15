<?php
declare(strict_types=1);
/** Iteration 7: authenticated password update. */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Auth helper checks inactivity timeout, account activation, and session user.
$authenticated = auth_user();
if (!$authenticated) {
    header('Location: ' . auth_base() . '/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/change_password.php'));
    exit;
}
$userId = (int)$authenticated['user_id'];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST['current_password'] ?? null;
    $new = $_POST['new_password'] ?? null;
    $confirm = $_POST['confirm_password'] ?? null;

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) $errors[] = 'Session token invalid. Reload the form.';
    if (!is_string($old) || $old === '' || !is_string($new) || $new === '' || !is_string($confirm) || $confirm === '') {
        $errors[] = 'Complete all password fields.';
    } else {
        if (strlen($new) < 12) $errors[] = 'Use at least 12 characters.';
        // PHP's PASSWORD_DEFAULT is currently bcrypt (72-byte password input limit).
        if (strlen($new) > 72) $errors[] = 'Use no more than 72 password bytes.';
        if (!hash_equals($new, $confirm)) $errors[] = 'New passwords do not match.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id=:id FOR UPDATE');
            $stmt->execute([':id' => $userId]);
            $stored = $stmt->fetchColumn();
            if (!is_string($stored) || !password_verify($old, $stored)) {
                $errors[] = 'Current password is incorrect.';
            } elseif (password_verify($new, $stored)) {
                $errors[] = 'The new password must differ from your current password.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password_hash=:hash WHERE user_id=:id');
                $stmt->execute([':hash'=>$hash, ':id'=>$userId]);
                $stmt = $pdo->prepare("INSERT INTO audit_log(user_id,action,table_name,record_id)
                                       VALUES(:id,'password_change','users',:record)");
                $stmt->execute([':id'=>$userId, ':record'=>$userId]);
            }
            if ($errors) {
                $pdo->rollBack();
            } else {
                $pdo->commit();
                session_regenerate_id(true);
                $_SESSION['last_activity'] = time();
                unset($_SESSION['csrf_token']);
                $success = 'Password updated successfully.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Password update: ' . $e->getMessage());
            $errors[] = 'Unable to change password. Please try again.';
        }
    }
    unset($old, $new, $confirm);
}
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<main class="container my-5" style="max-width:700px">
    <h1>Change Password</h1>
    <p class="text-muted">Update your account password securely.</p>
    <?php if ($success): ?><div class="alert alert-success" role="status"><?= h($success) ?></div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger" role="alert"><ul class="mb-0">
      <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
      </ul></div>
    <?php endif; ?>
    <section class="card shadow-sm"><div class="card-body">
      <form method="post" action="change_password.php">
        <?= csrf_field() ?>
        <div class="mb-3"><label for="current_password" class="form-label">Current password</label>
          <input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
        </div>
        <div class="mb-3"><label for="new_password" class="form-label">New password</label>
          <input class="form-control" type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="12" required>
          <div class="form-text">At least 12 characters. Use a unique password.</div>
        </div>
        <div class="mb-4"><label for="confirm_password" class="form-label">Confirm new password</label>
          <input class="form-control" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="12" required>
        </div>
        <button type="submit" class="btn btn-primary">Change Password</button>
        <a class="btn btn-outline-secondary ms-2" href="profile.php">Back to Profile</a>
      </form>
    </div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
