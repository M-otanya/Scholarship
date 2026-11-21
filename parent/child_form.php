<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/parent_helpers.php';
$parentId=parent_require_user();
$id=filter_var($_GET['child_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: null;
$child=$id ? parent_child($pdo,$parentId,(int)$id) : null;
if ($id && !$child) { http_response_code(404); exit('Child profile not found.'); }
$grades=$pdo->query('SELECT grade_id,grade_number FROM grade_levels ORDER BY grade_number')->fetchAll(PDO::FETCH_ASSOC);
$states=$pdo->query('SELECT state_id,name FROM states ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$fields=$pdo->query('SELECT field_id,field_name FROM fields_of_study ORDER BY field_name')->fetchAll(PDO::FETCH_ASSOC);
$name=(string)($child['child_name']??'');$grade=(int)($child['grade_id']??0);$state=(int)($child['state_id']??0);
$interests=$id ? parent_child_interests($pdo,$parentId,(int)$id) : [];
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name=trim((string)($_POST['child_name']??''));
    $grade=filter_var($_POST['grade_id']??0,FILTER_VALIDATE_INT) ?: 0;
    $state=filter_var($_POST['state_id']??0,FILTER_VALIDATE_INT) ?: 0;
    $raw=$_POST['interests']??[];
    $interests=is_array($raw) ? array_map('intval',$raw) : [];
    if (!csrf_token_is_valid($_POST['csrf_token']??null)) $error='Invalid session token.';
    else {
        try {
            parent_save_child($pdo,$parentId,$id,$name,(int)$grade,(int)$state,$interests);
            header('Location: children.php'); exit;
        } catch (InvalidArgumentException $e) { $error=$e->getMessage(); }
        catch (Throwable $e) { error_log($e->getMessage()); $error='Unable to save child profile.'; }
    }
}
require __DIR__.'/../includes/header.php';
require __DIR__.'/../includes/nav.php';
?>
<main class="container my-5" style="max-width:800px">
 <h1><?= $id ? 'Edit Child Profile' : 'Add Child Profile' ?></h1>
 <?php if($error): ?><div class="alert alert-danger"><?=h($error)?></div><?php endif; ?>
 <form method="post">
 <?=csrf_field()?>
 <div class="mb-3"><label class="form-label" for="child_name">Child's name</label>
 <input class="form-control" name="child_name" id="child_name" maxlength="120" required value="<?=h($name)?>"></div>
 <div class="mb-3"><label class="form-label" for="grade_id">Grade</label>
 <select class="form-select" name="grade_id" id="grade_id" required><option value="">Choose grade</option>
 <?php foreach($grades as $g): ?><option value="<?=(int)$g['grade_id']?>" <?= $grade===(int)$g['grade_id']?'selected':'' ?>>Grade <?=h($g['grade_number'])?></option><?php endforeach; ?>
 </select></div>
 <div class="mb-3"><label class="form-label" for="state_id">State</label>
 <select class="form-select" name="state_id" id="state_id" required><option value="">Choose state</option>
 <?php foreach($states as $s): ?><option value="<?=(int)$s['state_id']?>" <?= $state===(int)$s['state_id']?'selected':'' ?>><?=h($s['name'])?></option><?php endforeach; ?>
 </select></div>
 <fieldset class="mb-4"><legend class="h5">Interests</legend>
 <div class="row g-2"><?php foreach($fields as $f): ?><div class="col-sm-6">
 <label><input type="checkbox" name="interests[]" value="<?=(int)$f['field_id']?>" <?=in_array((int)$f['field_id'],$interests,true)?'checked':''?>>
 <?=h($f['field_name'])?></label></div><?php endforeach; ?></div></fieldset>
 <button class="btn btn-primary">Save Child</button><a class="btn btn-link" href="children.php">Cancel</a>
 </form>
</main>
<?php require __DIR__.'/../includes/footer.php'; ?>
