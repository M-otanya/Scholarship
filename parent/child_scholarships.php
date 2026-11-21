<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/parent_helpers.php';
$parentId=parent_require_user();
$childId=filter_var($_GET['child_id']??$_POST['child_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$childId || !($child=parent_child($pdo,$parentId,(int)$childId))) {
    http_response_code(404); exit('Child profile not found.');
}
$error='';
$allowed=['Not Started','In Progress','Submitted','Awarded','Rejected'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_token_is_valid($_POST['csrf_token']??null)) $error='Invalid session token.';
    else {
        $action=(string)($_POST['action']??'');
        $scholarshipId=filter_var($_POST['scholarship_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$scholarshipId) $error='Invalid scholarship.';
        else {
            try {
                if ($action==='save') {
                    $q=$pdo->prepare("INSERT INTO parent_child_saved_scholarships(child_id,scholarship_id)
                       SELECT ?,s.scholarship_id FROM scholarships s WHERE s.scholarship_id=? AND s.status='active'
                       ON DUPLICATE KEY UPDATE saved_id=saved_id");
                    $q->execute([$childId,$scholarshipId]);
                } elseif ($action==='remove') {
                    $q=$pdo->prepare('DELETE FROM parent_child_saved_scholarships WHERE child_id=? AND scholarship_id=?');
                    $q->execute([$childId,$scholarshipId]);
                } elseif ($action==='status' && in_array((string)($_POST['application_status']??''),$allowed,true)) {
                    $q=$pdo->prepare('UPDATE parent_child_saved_scholarships SET application_status=? WHERE child_id=? AND scholarship_id=?');
                    $q->execute([$_POST['application_status'],$childId,$scholarshipId]);
                } else { $error='Invalid action.'; }
                if ($error==='') {
                    header('Location: child_scholarships.php?child_id='.(int)$childId);exit;
                }
            } catch(PDOException $e) { error_log($e->getMessage()); $error='Unable to update scholarships.'; }
        }
    }
}
$saved=parent_child_saved($pdo,$parentId,(int)$childId);
$q=$pdo->prepare("SELECT scholarship_id,scholarship_name FROM scholarships
 WHERE status='active' AND scholarship_id NOT IN
 (SELECT scholarship_id FROM parent_child_saved_scholarships WHERE child_id=?)
 ORDER BY scholarship_name LIMIT 500");
$q->execute([$childId]);$available=$q->fetchAll(PDO::FETCH_ASSOC);
require __DIR__.'/../includes/header.php';
require __DIR__.'/../includes/nav.php';
?>
<main class="container my-5">
 <a href="dashboard.php">Parent Dashboard</a>
 <h1><?=h($child['child_name'])?> — Saved Scholarships</h1>
 <p class="text-muted">Grade <?=h($child['grade_number'])?> · <?=h($child['state_name'])?></p>
 <?php if($error): ?><div class="alert alert-danger"><?=h($error)?></div><?php endif; ?>
 <section class="card mb-4"><div class="card-body">
 <h2 class="h5">Save a Scholarship for this Child</h2>
 <form method="post" class="row g-2">
 <?=csrf_field()?><input type="hidden" name="child_id" value="<?=(int)$childId?>">
 <input type="hidden" name="action" value="save">
 <div class="col-md-9"><select class="form-select" name="scholarship_id" required>
 <option value="">Select an active scholarship</option>
 <?php foreach($available as $a): ?><option value="<?=(int)$a['scholarship_id']?>"><?=h($a['scholarship_name'])?></option><?php endforeach;?>
 </select></div><div class="col-md-3"><button class="btn btn-primary w-100">Save Scholarship</button></div>
 </form>
 <p class="text-muted small mb-0 mt-2">The first 500 eligible scholarships are listed. You can also use the public scholarship search to explore opportunities.</p>
 </div></section>
 <h2 class="h4">Saved Scholarships and Upcoming Deadlines</h2>
 <?php if(!$saved): ?><p>No scholarships saved for this child yet.</p><?php endif; ?>
 <div class="table-responsive"><table class="table table-striped align-middle">
 <thead><tr><th>Scholarship</th><th>Deadline</th><th>Application status</th><th>Actions</th></tr></thead><tbody>
 <?php foreach($saved as $s):
 $deadline=$s['application_deadline'];
 $days=$deadline ? (int)floor((strtotime((string)$deadline)-strtotime(date('Y-m-d')))/86400) : null;
 $soon=$s['status']==='active' && empty($s['rolling_deadline']) && $days!==null && $days>=0 && $days<=30;
 ?>
 <tr>
 <td><a href="../scholarship_detail.php?id=<?=(int)$s['scholarship_id']?>"><?=h($s['scholarship_name'])?></a></td>
 <td><?= $deadline ? h($deadline) : 'Not specified' ?>
 <?php if($soon): ?><span class="badge bg-warning text-dark">Due soon</span><?php endif; ?></td>
 <td><form method="post" class="d-flex gap-1">
 <?=csrf_field()?><input type="hidden" name="child_id" value="<?=(int)$childId?>">
 <input type="hidden" name="action" value="status">
 <input type="hidden" name="scholarship_id" value="<?=(int)$s['scholarship_id']?>">
 <select class="form-select form-select-sm" name="application_status" aria-label="Application status">
 <?php foreach($allowed as $status): ?><option value="<?=h($status)?>" <?= $s['application_status']===$status?'selected':''?>><?=h($status)?></option><?php endforeach;?>
 </select><button class="btn btn-sm btn-outline-primary">Update</button></form></td>
 <td><form method="post">
 <?=csrf_field()?><input type="hidden" name="child_id" value="<?=(int)$childId?>">
 <input type="hidden" name="action" value="remove">
 <input type="hidden" name="scholarship_id" value="<?=(int)$s['scholarship_id']?>">
 <button class="btn btn-sm btn-outline-danger">Unsave</button>
 </form></td>
 </tr><?php endforeach;?></tbody></table></div>
</main>
<?php require __DIR__.'/../includes/footer.php'; ?>
