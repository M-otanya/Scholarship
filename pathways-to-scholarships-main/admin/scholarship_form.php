<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/scholarship_validator.php';
require_once __DIR__ . '/../includes/audit.php';
start_app_session();
require_role(['Administrator', 'Super-admin']);

$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
if (isset($_GET['id']) && (!$id || $id < 1)) { http_response_code(400); exit('Invalid scholarship ID.'); }
$editing = $id !== null;
$errors = [];
$fields = [
 'scholarship_name'=>'Scholarship name', 'official_url'=>'Official URL', 'provider_id'=>'Provider',
 'state_id'=>'State', 'national_theme_id'=>'National theme', 'scope'=>'Scope',
 'description'=>'Description', 'discovery_source'=>'Discovery source',
 'award_min'=>'Minimum award ($)', 'award_max'=>'Maximum award ($)', 'number_of_awards'=>'Number of awards',
 'renewable'=>'Renewable', 'max_potential_value'=>'Maximum potential value ($)',
 'application_open_date'=>'Application open date', 'application_deadline'=>'Application deadline',
 'rolling_deadline'=>'Rolling deadline', 'residency_requirement'=>'Residency requirement',
 'minimum_gpa'=>'Minimum GPA', 'financial_need_required'=>'Financial need required',
 'essay_required'=>'Essay required', 'recommendation_required'=>'Recommendation required',
 'transcript_required'=>'Transcript required', 'community_service_required'=>'Community service required',
 'leadership_required'=>'Leadership required', 'eligibility_requirements'=>'Eligibility requirements',
 'application_method'=>'Application method', 'notes'=>'Notes', 'contributing_student'=>'Contributing student'
];
$relations = [
 'grades'=>['scholarship_grades','grade_levels','grade_id','grade_number'],
 'categories'=>['scholarship_categories','categories','category_id','category_name'],
 'study_fields'=>['scholarship_fields','fields_of_study','field_id','field_name']
];
$selectLists = [
 'provider_id'=>['providers','provider_id','provider_name'],
 'state_id'=>['states','state_id','name'],
 'national_theme_id'=>['national_themes','national_theme_id','theme_name']
];
try {
 $options=[];
 foreach ($selectLists as $key=>[$table,$pk,$label]) $options[$key]=$pdo->query("SELECT $pk AS id, $label AS label FROM $table ORDER BY $label")->fetchAll(PDO::FETCH_ASSOC);
 foreach ($relations as $key=>[$bridge,$table,$pk,$label]) $options[$key]=$pdo->query("SELECT $pk AS id, $label AS label FROM $table ORDER BY $label")->fetchAll(PDO::FETCH_ASSOC);
 $data=array_fill_keys(array_keys($fields),'');
 $selected=array_fill_keys(array_keys($relations),[]);
 if ($editing) {
  $q=$pdo->prepare('SELECT * FROM scholarships WHERE scholarship_id=?'); $q->execute([$id]); $row=$q->fetch(PDO::FETCH_ASSOC);
  if (!$row) { http_response_code(404); exit('Scholarship not found.'); }
  foreach ($fields as $key=>$label) $data[$key]=$row[$key] ?? '';
  foreach ($relations as $key=>[$bridge,$table,$pk,$label]) {
   $q=$pdo->prepare("SELECT $pk FROM $bridge WHERE scholarship_id=?"); $q->execute([$id]);
   $selected[$key]=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
  }
 }
} catch (PDOException $e) { error_log($e->getMessage()); http_response_code(500); exit('Unable to load scholarship form.'); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) $errors[]='Session expired. Refresh and try again.';
 foreach ($fields as $key=>$label) $data[$key]=trim((string)($_POST[$key] ?? ''));
 foreach ($relations as $key=>$spec) {
  $input=$_POST[$key] ?? [];
  if (!is_array($input)) { $errors[]='Invalid selection for '.$key; $input=[]; }
  $selected[$key]=array_values(array_unique(array_filter(array_map('intval',$input),fn($n)=>$n>0)));
  $valid=array_map(fn($o)=>(int)$o['id'],$options[$key]);
  if (array_diff($selected[$key],$valid)) $errors[]='Invalid selection for '.$key;
 }
 $errors=array_merge($errors,validate_scholarship($data + $selected));
 if ($data['scholarship_name']==='') $errors[]='Scholarship name is required.';
 if (mb_strlen($data['scholarship_name'])>255) $errors[]='Scholarship name is too long.';
 if (!filter_var($data['official_url'],FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($data['official_url'],PHP_URL_SCHEME)),['http','https'],true)) $errors[]='Enter a valid http or https official URL.';
 foreach ($selectLists as $key=>$spec) {
  $valid=array_map(fn($o)=>(string)$o['id'],$options[$key]);
  if ($data[$key]!=='' && !in_array($data[$key],$valid,true)) $errors[]='Invalid '.$fields[$key].'.';
 }
 if (!in_array($data['scope'],['','national','state/local'],true)) $errors[]='Invalid scope.';
 foreach (['renewable','financial_need_required','essay_required','recommendation_required','transcript_required','community_service_required','leadership_required'] as $key)
  if (!in_array($data[$key],['','Yes','No','Unknown'],true)) $errors[]='Invalid '.$fields[$key].'.';
 if (!in_array($data['rolling_deadline'],['','0','1'],true)) $errors[]='Invalid rolling deadline.';
 foreach (['award_min','award_max','max_potential_value','minimum_gpa'] as $key) {
  if ($data[$key]!=='' && (!is_numeric($data[$key]) || (float)$data[$key]<0)) $errors[]='Invalid '.$fields[$key].'.';
 }
 if ($data['minimum_gpa']!=='' && (float)$data['minimum_gpa']>4) $errors[]='Minimum GPA must be between 0 and 4.';
 if ($data['award_min']!=='' && $data['award_max']!=='' && (float)$data['award_min']>(float)$data['award_max']) $errors[]='Minimum award cannot exceed maximum award.';
 if ($data['number_of_awards']!=='' && (filter_var($data['number_of_awards'],FILTER_VALIDATE_INT)===false || (int)$data['number_of_awards']<0)) $errors[]='Number of awards must be a nonnegative whole number.';
 foreach (['application_open_date','application_deadline'] as $key) if ($data[$key]!=='') {
  $date=DateTimeImmutable::createFromFormat('!Y-m-d',$data[$key]);
  if (!$date || $date->format('Y-m-d')!==$data[$key]) $errors[]='Invalid '.$fields[$key].'.';
 }
 // Match the URL-normalization behavior used by the import pipeline before relying on the unique index.
 $url=trim($data['official_url']);
 $normalized=rtrim(strtolower($url),'/');
 if (!$errors) {
  $columns=array_keys($fields);
  $values=[];
  foreach ($columns as $key) {
   $value=$data[$key];
   $values[$key]=$value===''?null:$value;
  }
  $values['rolling_deadline']=$data['rolling_deadline']==='1'?1:0;
  try {
   $pdo->beginTransaction();
   if ($editing) {
    $assignments=implode(', ',array_map(fn($c)=>"$c = :$c",$columns));
    $q=$pdo->prepare("UPDATE scholarships SET $assignments, normalized_url=:normalized_url, updated_by=:updated_by WHERE scholarship_id=:id");
    $q->execute($values+['normalized_url'=>$normalized,'updated_by'=>current_user_id(),'id'=>$id]);
   } else {
    $insertColumns=array_merge($columns,['normalized_url','created_by','updated_by']);
    $placeholders=array_map(fn($c)=>':'.$c,$insertColumns);
    $q=$pdo->prepare('INSERT INTO scholarships ('.implode(',',$insertColumns).') VALUES ('.implode(',',$placeholders).')');
    $q->execute($values+['normalized_url'=>$normalized,'created_by'=>current_user_id(),'updated_by'=>current_user_id()]);
    $id=(int)$pdo->lastInsertId();
   }
   foreach ($relations as $key=>[$bridge,$table,$pk,$label]) {
    $q=$pdo->prepare("DELETE FROM $bridge WHERE scholarship_id=?"); $q->execute([$id]);
    $q=$pdo->prepare("INSERT INTO $bridge (scholarship_id,$pk) VALUES (?,?)");
    foreach ($selected[$key] as $value) $q->execute([$id,$value]);
   }
   log_admin_action($pdo, $editing ? 'edit' : 'create', 'scholarships', (int)$id, current_user_id());
   $pdo->commit();
   header('Location: scholarship_view.php?id='.$id); exit;
  } catch (Throwable $e) {
   if ($pdo->inTransaction()) $pdo->rollBack();
   error_log($e->getMessage());
   $errors[]=(string)$e->getCode()==='23000'?'Unable to save: the official URL may already belong to another scholarship, or a related record is invalid.':'Unable to save scholarship. Check the server log.';
  }
 }
}
function formValue(array $data,string $key): string { return h((string)($data[$key]??'')); }
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $editing?'Edit':'Create' ?> Scholarship</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-4" style="max-width:1000px">
<div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3"><?= $editing?'Edit':'Create' ?> Scholarship</h1><a href="scholarships.php" class="btn btn-outline-secondary">Back to list</a></div>
<?php if ($errors): ?><div class="alert alert-danger"><strong>Please fix:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" id="scholarshipForm" novalidate class="card p-4 shadow-sm"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
<div class="row g-3">
<?php foreach ($fields as $key=>$label): ?>
<div class="col-md-6"><label for="<?= h($key) ?>" class="form-label"><?= h($label) ?><?= in_array($key,['scholarship_name','official_url'],true)?' *':'' ?></label>
<?php if (isset($selectLists[$key])): ?>
<select class="form-select" name="<?= h($key) ?>" id="<?= h($key) ?>"><option value="">Not specified</option><?php foreach ($options[$key] as $opt): ?><option value="<?= (int)$opt['id'] ?>" <?= (string)$data[$key]===(string)$opt['id']?'selected':'' ?>><?= h($opt['label']) ?></option><?php endforeach; ?></select>
<?php elseif ($key==='scope'): ?><select class="form-select" name="scope" id="scope"><option value="">Not specified</option><?php foreach (['national','state/local'] as $value): ?><option value="<?= h($value) ?>" <?= $data[$key]===$value?'selected':'' ?>><?= h($value) ?></option><?php endforeach; ?></select>
<?php elseif (in_array($key,['renewable','financial_need_required','essay_required','recommendation_required','transcript_required','community_service_required','leadership_required'],true)): ?>
<select class="form-select" name="<?= h($key) ?>" id="<?= h($key) ?>"><option value="">Not specified</option><?php foreach (['Yes','No','Unknown'] as $value): ?><option value="<?= $value ?>" <?= $data[$key]===$value?'selected':'' ?>><?= $value ?></option><?php endforeach; ?></select>
<?php elseif ($key==='rolling_deadline'): ?><select class="form-select" name="rolling_deadline" id="rolling_deadline"><option value="0" <?= !$data[$key]?'selected':'' ?>>No</option><option value="1" <?= $data[$key]?'selected':'' ?>>Yes</option></select>
<?php elseif (in_array($key,['description','notes','eligibility_requirements','residency_requirement'],true)): ?><textarea class="form-control" rows="3" name="<?= h($key) ?>" id="<?= h($key) ?>"><?= formValue($data,$key) ?></textarea>
<?php else: ?><?php $type=in_array($key,['application_open_date','application_deadline'],true)?'date':(in_array($key,['award_min','award_max','number_of_awards','max_potential_value','minimum_gpa'],true)?'number':($key==='official_url'?'url':'text')); ?>
<input class="form-control" type="<?= $type ?>" <?= $type==='number'?'min="0" step="'.($key==='number_of_awards'?'1':'0.01').'"':'' ?> name="<?= h($key) ?>" id="<?= h($key) ?>" value="<?= formValue($data,$key) ?>" <?= in_array($key,['scholarship_name','official_url'],true)?'required':'' ?>></input>
<?php endif; ?></div>
<?php endforeach; ?>
<?php foreach ($relations as $key=>$spec): ?><div class="col-md-4"><label for="<?= h($key) ?>" class="form-label"><?= h(ucwords(str_replace('_',' ',$key))) ?> (multiple)</label><select class="form-select" name="<?= h($key) ?>[]" id="<?= h($key) ?>" multiple size="7"><?php foreach ($options[$key] as $opt): ?><option value="<?= (int)$opt['id'] ?>" <?= in_array((int)$opt['id'],$selected[$key],true)?'selected':'' ?>><?= h($opt['label']) ?></option><?php endforeach; ?></select><div class="form-text">Hold Command (Mac) or Ctrl to select multiple.</div></div><?php endforeach; ?>
</div><div class="mt-4 d-flex gap-2"><button class="btn btn-primary" type="submit"><?= $editing?'Save changes':'Create scholarship' ?></button><a class="btn btn-outline-secondary" href="scholarships.php">Cancel</a></div>
</form></main><script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="../js/scholarship-validation.js"></script></body></html>
