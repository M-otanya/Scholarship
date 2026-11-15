<?php
declare(strict_types=1);
require __DIR__.'/includes/db.php';require __DIR__.'/includes/auth.php';
auth_session();$errors=[];$email='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $email=trim((string)($_POST['email']??''));$password=(string)($_POST['password']??'');$role=(string)($_POST['role']??'');
 if (!csrf_token_is_valid($_POST['csrf_token']??null)) $errors[]='Invalid form token. Refresh and try again.';
 if (!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>255) $errors[]='Enter a valid email.';
 if (strlen($password)<12||strlen($password)>128) $errors[]='Password must be 12–128 characters.';
 if ($password!==($_POST['confirm_password']??null)) $errors[]='Passwords do not match.';
 if (!in_array($role,['Student','Parent'],true)) $errors[]='Choose Student or Parent.';
 if (!$errors) {
  $r=$pdo->prepare('SELECT role_id FROM roles WHERE LOWER(role_name)=LOWER(?)');$r->execute([$role]);$roleId=$r->fetchColumn();
  if (!$roleId) $errors[]='Registration role is not configured in the database.';
  else {try {$q=$pdo->prepare('INSERT INTO users (role_id,email,password_hash) VALUES (?,?,?)');$q->execute([$roleId,$email,password_hash($password,PASSWORD_DEFAULT)]);header('Location: login.php?registered=1');exit;}catch(PDOException $e){if ($e->getCode()==='23000')$errors[]='Email is already registered.';else throw $e;}}
 }
}
require __DIR__.'/includes/header.php';require __DIR__.'/includes/nav.php';?>
<main class="container py-5" style="max-width:560px"><h1>Create an account</h1>
<?php foreach($errors as $e):?><div class="alert alert-danger"><?=h($e)?></div><?php endforeach;?>
<form method="post" id="registerForm"><?=csrf_field()?>
<label class="form-label">Email<input class="form-control" type="email" name="email" value="<?=h($email)?>" required maxlength="255"></label><br>
<label class="form-label">Account type<select class="form-select" name="role" required><option value="">Choose</option><option value="Student" <?=($_POST['role']??'')==='Student'?'selected':''?>>Student</option><option value="Parent" <?=($_POST['role']??'')==='Parent'?'selected':''?>>Parent</option></select></label><br>
<label class="form-label">Password (12+ characters)<input class="form-control" type="password" name="password" minlength="12" maxlength="128" required></label><br>
<label class="form-label">Confirm password<input class="form-control" type="password" name="confirm_password" minlength="12" required></label><br>
<button class="btn btn-primary">Register</button></form><p class="mt-3">Already registered? <a href="login.php">Log in</a></p></main>
<script>document.getElementById('registerForm').addEventListener('submit',function(e){const a=this.password.value,b=this.confirm_password.value;if(a.length<12||a!==b){e.preventDefault();alert('Use a password of at least 12 characters and make sure both passwords match.');}});</script>
<?php require __DIR__.'/includes/footer.php';?>