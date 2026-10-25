<?php
declare(strict_types=1);

/** Iteration 2 development stub; replace with real authorization when login is implemented. */
function require_role(array $allowedRoles): void
{
    // Local development only: role checks are not enforced yet.
}

/** Return the configured placeholder user ID for audit columns. */
function current_user_id(): int
{
    $configFile = __DIR__ . '/../config/config.php';
    $config = is_file($configFile) ? require $configFile : [];
    $userId = is_array($config) ? ($config['dev_user_id'] ?? 1) : 1;
    return max(1, (int) $userId);
}
