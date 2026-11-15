<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
auth_session();

if (current_user_id() !== null) {
    header('Location: ' . auth_base() . '/index.php');
    exit;
}

$error = '';
$email = '';
$next = (string) ($_POST['next'] ?? $_GET['next'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        $stmt = $pdo->prepare('SELECT user_id, password_hash FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            $error = 'Incorrect email or password.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $base = auth_base();
            $safeNext = $next !== ''
                && str_starts_with($next, '/')
                && !str_starts_with($next, '//')
                && !str_contains($next, '\\')
                && !preg_match('/[\r\n]/', $next)
                && ($base === '' || $next === $base || str_starts_with($next, $base . '/'));
            header('Location: ' . ($safeNext ? $next : $base . '/index.php'));
            exit;
        }
    }
}
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<main class="container py-5" style="max-width:560px; min-height:65vh">
  <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <img src="<?= $nsBaseUrl ?>/images/pathways-logo.svg" alt="Pathways to Scholarships logo" width="66" height="66" class="mb-3">
        <h1 class="h2 fw-bold mb-2">Welcome back</h1>
        <p class="text-secondary mb-0">Sign in to your Pathways to Scholarships account.</p>
      </div>
      <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success" role="status">Your account was created. You can now sign in.</div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="post" action="" autocomplete="on">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= h($next) ?>">
        <div class="mb-3">
          <label for="email" class="form-label fw-semibold">Email address</label>
          <input id="email" name="email" type="email" class="form-control form-control-lg" value="<?= h($email) ?>" autocomplete="username" maxlength="255" required autofocus>
        </div>
        <div class="mb-4">
          <label for="password" class="form-label fw-semibold">Password</label>
          <input id="password" name="password" type="password" class="form-control form-control-lg" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-100">Log in</button>
      </form>
      <p class="text-center text-secondary mt-4 mb-0">Don't have an account? <a href="<?= $nsBaseUrl ?>/register.php" class="fw-semibold">Create an account</a></p>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>