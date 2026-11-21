<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/parent_helpers.php';
$parentId=parent_require_user();
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_token_is_valid($_POST['csrf_token']??null)) { $error='Invalid session token.'; }
    else {
        $id=filter_var($_POST['child_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id || !parent_child($pdo,$parentId,(int)$id)) { http_response_code(404); exit('Child profile not found.'); }
        try {
            $q=$pdo->prepare('DELETE FROM parent_children WHERE child_id=? AND parent_user_id=?');
            $q->execute([$id,$parentId]);
            header('Location: children.php?deleted=1'); exit;
        } catch (PDOException $e) { error_log($e->getMessage()); $error='Unable to delete profile.'; }
    }
}
$children=parent_children($pdo,$parentId);
require __DIR__.'/../includes/header.php';
require __DIR__.'/../includes/nav.php';
?>
<main class="container my-5">
 <div class="d-flex justify-content-between align-items-center"><h1>Manage Children</h1><a class="btn btn-primary" href="child_form.php">Add Child</a></div>
 <?php if (isset($_GET['deleted'])): ?><div class="alert alert-success">Child profile deleted.</div><?php endif; ?>
 <?php if ($error): ?><div class="alert alert-danger"><?=h($error)?></div><?php endif; ?>
 <?php if (!$children): ?><p>No children added yet.</p><?php endif; ?>
 <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Child</th><th>Grade</th><th>State</th><th>Saved</th><th>Actions</th></tr></thead><tbody>
 <?php foreach($children as $c): ?><tr>
 <td><?=h($c['child_name'])?></td><td><?=h($c['grade_number'])?></td><td><?=h($c['state_name'])?></td><td><?=(int)$c['saved_count']?></td>
 <td>
 <a class="btn btn-sm btn-outline-primary" href="child_scholarships.php?child_id=<?=(int)$c['child_id']?>">Scholarships</a>
 <a class="btn btn-sm btn-outline-secondary" href="child_form.php?child_id=<?=(int)$c['child_id']?>">Edit</a>
 <form class="d-inline" method="post" onsubmit="return confirm('Delete this child profile and its saved scholarships?');">
 <?=csrf_field()?><input type="hidden" name="child_id" value="<?=(int)$c['child_id']?>">
 <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form>
 </td></tr><?php endforeach; ?>
 </tbody></table></div>
 <a href="dashboard.php">Back to Parent Dashboard</a>
</main>
<?php require __DIR__.'/../includes/footer.php'; ?>
