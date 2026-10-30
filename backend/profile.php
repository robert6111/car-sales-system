<?php
require_once __DIR__ . '/../includes/db.php';require_once __DIR__ . '/includes/auth.php';require_login();$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){$name=trim($_POST['name']??'');if($name===''){$error='Name is required.';}else{$stmt=$conn->prepare('UPDATE users SET name=? WHERE id=?');$stmt->bind_param('si',$name,$_SESSION['user_id']);$stmt->execute();$_SESSION['name']=$name;set_flash('success','Profile updated.');redirect_to('backend/profile.php');}}
$page_title='Profile';include __DIR__.'/includes/header.php';include __DIR__.'/includes/leftsidebar.php';
?>
<div class="content-page"><h1 class="page-title">Profile</h1><div class="card form-card"><?php if($error):?><div class="flash-message error" style="position:static;margin-bottom:16px;"><?=e($error)?></div><?php endif;?><form method="post"><div class="form-group"><label>Name</label><input name="name" value="<?=e($_SESSION['name'])?>" required></div><div class="form-group"><label>Email</label><input value="<?=e($_SESSION['email'])?>" readonly></div><button class="btn">Save Profile</button></form></div></div><?php include __DIR__.'/includes/footer.php'; ?>
