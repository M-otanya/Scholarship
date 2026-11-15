<?php
declare(strict_types=1);require __DIR__.'/includes/auth.php';auth_session();
if ($_SERVER['REQUEST_METHOD']!=='POST'||!csrf_token_is_valid($_POST['csrf_token']??null)){http_response_code(405);exit('Invalid logout request.');}
$_SESSION=[];session_regenerate_id(true);header('Location: '.auth_base().'/login.php');exit;