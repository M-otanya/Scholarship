<?php
declare(strict_types=1);

/** Shared profile queries; caller must authorize the user before using these. */
function profile_get_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT u.user_id, u.email, u.created_at, r.role_name
                            FROM users u JOIN roles r ON r.role_id=u.role_id
                            WHERE u.user_id=:id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function profile_get_student(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT profile_id, user_id, state_id, grade_id
                           FROM student_profiles WHERE user_id=:id
                           ORDER BY profile_id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function profile_validate_email(string $email): bool
{
    return $email !== '' && strlen($email) <= 255
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function profile_valid_reference(PDO $pdo, string $type, int $id): bool
{
    $allowed = [
        'state' => ['states', 'state_id'],
        'grade' => ['grade_levels', 'grade_id'],
    ];
    if ($id < 1 || !isset($allowed[$type])) return false;
    [$table, $column] = $allowed[$type];
    $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$column}=:id LIMIT 1");
    $stmt->execute([':id' => $id]);
    return (bool)$stmt->fetchColumn();
}

/** Save student data within the transaction owned by the calling page. */
function profile_save_student(PDO $pdo, int $userId, int $stateId, int $gradeId): bool
{
    $user = profile_get_user($pdo, $userId);
    if (!$user || strcasecmp($user['role_name'], 'Student') !== 0) return false;
    if (!profile_valid_reference($pdo, 'state', $stateId)
        || !profile_valid_reference($pdo, 'grade', $gradeId)) return false;

    $current = profile_get_student($pdo, $userId);
    if ($current) {
        $stmt = $pdo->prepare('UPDATE student_profiles
             SET state_id=:state, grade_id=:grade
             WHERE profile_id=:profile AND user_id=:user');
        return $stmt->execute([
            ':state'=>$stateId, ':grade'=>$gradeId,
            ':profile'=>$current['profile_id'], ':user'=>$userId,
        ]);
    }
    $stmt = $pdo->prepare('INSERT INTO student_profiles(user_id,state_id,grade_id)
                            VALUES(:user,:state,:grade)');
    return $stmt->execute([':user'=>$userId, ':state'=>$stateId, ':grade'=>$gradeId]);
}
