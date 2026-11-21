<?php
declare(strict_types=1);

require_once __DIR__ . '/security.php';

function auth_base(): string
{
    $dir = str_replace(
        '\\',
        '/',
        dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')
    );

    if (basename($dir) === 'admin') {
        $dir = dirname($dir);
    }

    return rtrim($dir === '/' ? '' : $dir, '/');
}

function auth_session(): void
{
    start_app_session();

    if (isset($_SESSION['user_id'])) {

        $lastActivity = (int) (
            $_SESSION['last_activity'] ?? 0
        );

        if (
            $lastActivity <= 0 ||
            time() - $lastActivity > 1800
        ) {
            $_SESSION = [];
            session_regenerate_id(true);
        } else {
            $_SESSION['last_activity'] = time();
        }
    }
}

/**
 * Retrieve the currently authenticated user.
 * Inactive accounts are not permitted.
 */
function auth_user(): ?array
{
    global $pdo;

    auth_session();

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    if (!isset($pdo) || !$pdo instanceof PDO) {
        require __DIR__ . '/db.php';
    }

    $stmt = $pdo->prepare("
        SELECT
            u.user_id,
            u.email,
            r.role_name
        FROM users u
        INNER JOIN roles r
            ON r.role_id = u.role_id
        WHERE u.user_id = ?
          AND u.is_active = 1
        LIMIT 1
    ");

    $stmt->execute([
        (int) $_SESSION['user_id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        unset(
            $_SESSION['user_id'],
            $_SESSION['last_activity']
        );

        return null;
    }

    return $user;
}

/**
 * Return the authenticated user's ID,
 * or null when not logged in.
 */
function current_user_id(): ?int
{
    $user = auth_user();

    return $user
        ? (int) $user['user_id']
        : null;
}

/**
 * Restrict access to allowed roles.
 */
function require_role(array $allowedRoles): void
{
    $user = auth_user();

    if (!$user) {
        header(
            'Location: ' .
            auth_base() .
            '/login.php?next=' .
            rawurlencode($_SERVER['REQUEST_URI'] ?? '')
        );
        exit;
    }

    $role = strtolower(
        (string) $user['role_name']
    );

    $allowed = array_map(
        'strtolower',
        $allowedRoles
    );

    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}
