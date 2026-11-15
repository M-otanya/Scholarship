<?php
declare(strict_types=1);
/** Iteration 7: account details and student academic profile. */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/profile_helpers.php';

// auth_user checks session lifetime AND users.is_active.
$authenticated = auth_user();
if (!$authenticated) {
    header('Location: ' . auth_base() . '/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/profile.php'));
    exit;
}
$userId = (int)$authenticated['user_id'];
$errors = [];
$success = '';

try {
    $user = profile_get_user($pdo, $userId);
    if (!$user) { http_response_code(403); exit('Account not found.'); }
    $isStudent = strcasecmp($user['role_name'], 'Student') === 0;
    $student = $isStudent ? profile_get_student($pdo, $userId) : null;
    $states = $isStudent ? $pdo->query('SELECT state_id,name FROM states ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) : [];
    $grades = $isStudent ? $pdo->query('SELECT grade_id,grade_number FROM grade_levels ORDER BY grade_number')->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (PDOException $e) {
    error_log('Profile load: ' . $e->getMessage());
    http_response_code(500); exit('Unable to load profile.');
}
$email = (string)$user['email'];
$stateId = $student ? (string)$student['state_id'] : '';
$gradeId = $student ? (string)$student['grade_id'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session token invalid. Reload the form.';
    }
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    if (!profile_validate_email($email)) $errors[] = 'Enter a valid email address.';

    if ($isStudent) {
        $stateId = is_string($_POST['state_id'] ?? null) ? trim($_POST['state_id']) : '';
        $gradeId = is_string($_POST['grade_id'] ?? null) ? trim($_POST['grade_id']) : '';
        if (($stateId === '') !== ($gradeId === '')) {
            $errors[] = 'Choose both a state and a grade, or leave both unselected.';
        }
        if ($stateId !== '' && (!ctype_digit($stateId) || !profile_valid_reference($pdo, 'state', (int)$stateId))) {
            $errors[] = 'Invalid state.';
        }
        if ($gradeId !== '' && (!ctype_digit($gradeId) || !profile_valid_reference($pdo, 'grade', (int)$gradeId))) {
            $errors[] = 'Invalid grade.';
        }
    }

    if (!$errors) {
        $emailChanged = $email !== $user['email'];
        $studentChanged = $isStudent && $stateId !== '' && $gradeId !== '' &&
            (!$student || (int)$stateId !== (int)$student['state_id'] || (int)$gradeId !== (int)$student['grade_id']);
        if (!$emailChanged && !$studentChanged) {
            $success = 'No changes were necessary.';
        } else {
            try {
                $pdo->beginTransaction();
                if ($emailChanged) {
                    $stmt = $pdo->prepare('UPDATE users SET email=:email WHERE user_id=:id');
                    $stmt->execute([':email'=>$email, ':id'=>$userId]);
                }
                if ($studentChanged && !profile_save_student($pdo, $userId, (int)$stateId, (int)$gradeId)) {
                    throw new RuntimeException('Invalid student profile information.');
                }
                // Audit the event, not the personal information itself.
                $stmt = $pdo->prepare("INSERT INTO audit_log(user_id,action,table_name,record_id)
                                       VALUES(:id,'profile_update','users',:record)");
                $stmt->execute([':id'=>$userId, ':record'=>$userId]);
                $pdo->commit();
                $user['email'] = $email;
                if ($studentChanged) $student = profile_get_student($pdo, $userId);
                $success = 'Profile updated successfully.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Profile save: ' . $e->getMessage());
                $errors[] = $e instanceof PDOException && $e->getCode() === '23000'
                    ? 'That email may already be registered.' : 'Could not save your profile.';
            }
        }
    }
}
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
?>
<main class="container my-5" style="max-width:760px">
    <h1>User Profile</h1>
    <p class="text-muted">Manage your account information.</p>
    <?php if ($success): ?><div class="alert alert-success" role="status"><?= h($success) ?></div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger" role="alert"><ul class="mb-0">
      <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
      </ul></div>
    <?php endif; ?>
    <section class="card shadow-sm mb-4"><div class="card-body">
      <h2 class="h5">Account Information</h2>
      <p><strong>User ID:</strong> <?= (int)$user['user_id'] ?></p>
      <p><strong>Role:</strong> <?= h($user['role_name']) ?></p>
      <p><strong>Member since:</strong> <?= h($user['created_at']) ?></p>
    </div></section>
    <section class="card shadow-sm"><div class="card-body">
      <h2 class="h5 mb-3">Edit Profile</h2>
      <form method="post" action="profile.php">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="email">Email Address</label>
          <input class="form-control" id="email" type="email" name="email" maxlength="255" autocomplete="email" required value="<?= h($email) ?>">
        </div>
        <?php if ($isStudent): ?>
          <div class="mb-3"><label class="form-label" for="state_id">State</label>
            <select class="form-select" name="state_id" id="state_id">
              <option value="">Select State</option>
              <?php foreach ($states as $state): ?>
                <option value="<?= (int)$state['state_id'] ?>" <?= $stateId === (string)$state['state_id'] ? 'selected' : '' ?>><?= h($state['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3"><label class="form-label" for="grade_id">Grade Level</label>
            <select class="form-select" name="grade_id" id="grade_id">
              <option value="">Select Grade</option>
              <?php foreach ($grades as $grade): ?>
                <option value="<?= (int)$grade['grade_id'] ?>" <?= $gradeId === (string)$grade['grade_id'] ? 'selected' : '' ?>>Grade <?= h($grade['grade_number']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <p class="form-text">Select both state and grade for your student profile.</p>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">Save Changes</button>
        <a class="btn btn-outline-secondary ms-2" href="change_password.php">Change Password</a>
      </form>
    </div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
