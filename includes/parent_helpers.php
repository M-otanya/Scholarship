<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function parent_require_user(): int {
    require_role(['Parent']);
    $id = current_user_id();
    if ($id === null) { http_response_code(403); exit('Access denied.'); }
    return $id;
}
function parent_child(PDO $pdo, int $parentId, int $childId): ?array {
    $q=$pdo->prepare('SELECT pc.*,g.grade_number,st.name AS state_name FROM parent_children pc
       JOIN grade_levels g ON g.grade_id=pc.grade_id
       JOIN states st ON st.state_id=pc.state_id
       WHERE pc.child_id=? AND pc.parent_user_id=? LIMIT 1');
    $q->execute([$childId,$parentId]);
    return $q->fetch(PDO::FETCH_ASSOC) ?: null;
}
function parent_children(PDO $pdo, int $parentId): array {
    $q=$pdo->prepare("SELECT pc.child_id,pc.child_name,g.grade_number,st.name AS state_name,
      (SELECT COUNT(*) FROM parent_child_saved_scholarships ss WHERE ss.child_id=pc.child_id) AS saved_count,
      (SELECT COUNT(*) FROM parent_child_saved_scholarships ss JOIN scholarships s
        ON s.scholarship_id=ss.scholarship_id WHERE ss.child_id=pc.child_id
        AND s.status='active' AND s.rolling_deadline=0
        AND s.application_deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)) AS due_soon
      FROM parent_children pc JOIN grade_levels g ON g.grade_id=pc.grade_id
      JOIN states st ON st.state_id=pc.state_id
      WHERE pc.parent_user_id=? ORDER BY pc.child_name,pc.child_id");
    $q->execute([$parentId]); return $q->fetchAll(PDO::FETCH_ASSOC);
}
function parent_child_interests(PDO $pdo,int $parentId,int $childId): array {
    if (!parent_child($pdo,$parentId,$childId)) return [];
    $q=$pdo->prepare('SELECT field_id FROM parent_child_interests WHERE child_id=?');
    $q->execute([$childId]); return array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
}
function parent_save_child(PDO $pdo,int $parentId,?int $childId,string $name,int $gradeId,int $stateId,array $fieldIds): int {
    $name=trim($name);
    if ($name==='' || strlen($name)>120 || $gradeId<1 || $stateId<1) {
        throw new InvalidArgumentException('Enter a valid child name, grade, and state.');
    }
    foreach (['grade_levels'=>['grade_id',$gradeId],'states'=>['state_id',$stateId]] as $table=>$item) {
        $q=$pdo->prepare('SELECT 1 FROM '.$table.' WHERE '.$item[0].'=?');
        $q->execute([$item[1]]);
        if (!$q->fetchColumn()) throw new InvalidArgumentException('Invalid grade or state.');
    }
    $fieldIds=array_values(array_unique(array_map('intval',$fieldIds)));
    foreach ($fieldIds as $fieldId) {
        $q=$pdo->prepare('SELECT 1 FROM fields_of_study WHERE field_id=?');
        $q->execute([$fieldId]);
        if ($fieldId<1 || !$q->fetchColumn()) throw new InvalidArgumentException('Invalid interest.');
    }
    if ($childId!==null && !parent_child($pdo,$parentId,$childId)) throw new RuntimeException('Child profile not found.');
    $pdo->beginTransaction();
    try {
        if ($childId===null) {
            $q=$pdo->prepare('INSERT INTO parent_children(parent_user_id,child_name,grade_id,state_id) VALUES(?,?,?,?)');
            $q->execute([$parentId,$name,$gradeId,$stateId]);
            $childId=(int)$pdo->lastInsertId();
        } else {
            $q=$pdo->prepare('UPDATE parent_children SET child_name=?,grade_id=?,state_id=? WHERE child_id=? AND parent_user_id=?');
            $q->execute([$name,$gradeId,$stateId,$childId,$parentId]);
        }
        $pdo->prepare('DELETE FROM parent_child_interests WHERE child_id=?')->execute([$childId]);
        $q=$pdo->prepare('INSERT INTO parent_child_interests(child_id,field_id) VALUES(?,?)');
        foreach ($fieldIds as $fieldId) $q->execute([$childId,$fieldId]);
        $pdo->commit(); return $childId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
function parent_child_saved(PDO $pdo,int $parentId,int $childId): array {
    $q=$pdo->prepare('SELECT ss.saved_id,ss.scholarship_id,ss.application_status,ss.saved_at,
      s.scholarship_name,s.application_deadline,s.rolling_deadline,s.status
      FROM parent_child_saved_scholarships ss
      JOIN parent_children pc ON pc.child_id=ss.child_id
      JOIN scholarships s ON s.scholarship_id=ss.scholarship_id
      WHERE pc.child_id=? AND pc.parent_user_id=?
      ORDER BY s.application_deadline IS NULL,s.application_deadline,s.scholarship_name');
    $q->execute([$childId,$parentId]); return $q->fetchAll(PDO::FETCH_ASSOC);
}
