<?php

declare(strict_types=1);

/** Start a hardened session once, before the page sends output. */
function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
        'path' => '/',
    ]);

    if (!session_start()) {
        throw new RuntimeException('Could not start the PHP session.');
    }
}

/** Escape text before placing it in HTML. This is not SQL escaping. */
function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Get or create this browser session's CSRF token. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Create the hidden form field containing the session's CSRF token. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

/** Check a submitted token without exposing details about the expected token. */
function csrf_token_is_valid(mixed $submittedToken): bool
{
    if (!is_string($submittedToken) || !isset($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals((string) $_SESSION['csrf_token'], $submittedToken);
}
