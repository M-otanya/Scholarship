<?php
declare(strict_types=1);

function log_admin_action(PDO $pdo, string $action, string $entityType, int $entityId, int $userId): void
{
    $allowed = ['create', 'edit', 'archive', 'verify', 'import', 'rollback', 'provider_merge'];
    if (!in_array($action, $allowed, true)) throw new InvalidArgumentException('Invalid audit action.');
    if (!in_array($entityType, ['scholarships', 'providers', 'import_batches'], true) || $entityId < 1 || $userId < 1) {
        throw new InvalidArgumentException('Invalid audit entity or user.');
    }
    $user = $pdo->prepare('SELECT user_id FROM users WHERE user_id = ?');
    $user->execute([$userId]);
    if (!$user->fetchColumn()) {
        throw new RuntimeException('Placeholder administrator user does not exist; configure dev_user_id with an existing users.user_id.');
    }
    $stmt = $pdo->prepare('INSERT INTO audit_log (user_id, action, table_name, record_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, $action, $entityType, $entityId]);
}
