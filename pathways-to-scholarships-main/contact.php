
<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/security.php';
start_app_session();

$success = '';
$error = '';

$name = '';
$email = '';
$subject = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid or expired form token. Refresh the page and try again.';
    } elseif ($name === '' || $email === '' ||
        $subject === '' || $message === '') {
        $error = 'Please fill out all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (
        strlen($name) > 100 ||
        strlen($email) > 255 ||
        strlen($subject) > 255
    ) {
        $error = 'One or more fields are too long.';
    } else {
        try {
            $sql = "INSERT INTO contact_messages
                    (name, email, subject, message)
                    VALUES (:name, :email, :subject, :message)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':subject' => $subject,
                ':message' => $message
            ]);

            $success = 'Your message was sent successfully!';

            $name = '';
            $email = '';
            $subject = '';
            $message = '';

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Unable to save your message. Please try again.';
        }
    }
}

function escapeContact($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/nav.php';
require __DIR__ . '/includes/dev_banner.php';
?>

<main class="container my-5">

    <h1>Contact Us</h1>

    <p>
        Have a question or need help finding scholarships?
        Fill out the form below to get in touch with us.
    </p>

    <?php if ($success): ?>
        <div class="alert alert-success" role="status">
            <?= escapeContact($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <?= escapeContact($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="contact.php">
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" class="form-control"
                   id="name" name="name" maxlength="100"
                   value="<?= escapeContact($name) ?>" required>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control"
                   id="email" name="email" maxlength="255"
                   value="<?= escapeContact($email) ?>" required>
        </div>

        <div class="mb-3">
            <label for="subject" class="form-label">Subject</label>
            <input type="text" class="form-control"
                   id="subject" name="subject" maxlength="255"
                   value="<?= escapeContact($subject) ?>" required>
        </div>

        <div class="mb-3">
            <label for="message" class="form-label">Message</label>
            <textarea class="form-control"
                      id="message" name="message"
                      rows="5" required><?= escapeContact($message) ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">
            Send Message
        </button>

    </form>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
