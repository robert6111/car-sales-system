<?php
require_once __DIR__ . '/../includes/db.php';require_once __DIR__ . '/includes/auth.php';require_login();$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $rateKey='change_password_' . (int)$_SESSION['user_id'];
    [$allowed,$waitMinutes]=car_sales_check_rate_limit($rateKey, CAR_SALES_PASSWORD_MAX_ATTEMPTS, CAR_SALES_PASSWORD_LOCK_SECONDS);
    if(!$allowed){
        $error='Too many password change attempts. Please try again in about ' . $waitMinutes . ' minute(s).';
    } else {
        $current=$_POST['current_password']??'';$new=$_POST['new_password']??'';$confirm=$_POST['confirm_password']??'';$stmt=$conn->prepare('SELECT password FROM users WHERE id=?');$stmt->bind_param('i',$_SESSION['user_id']);$stmt->execute();$u=$stmt->get_result()->fetch_assoc();if(!$u||!password_verify($current,$u['password'])){car_sales_record_failed_attempt($rateKey, CAR_SALES_PASSWORD_MAX_ATTEMPTS, CAR_SALES_PASSWORD_LOCK_SECONDS);$error='Current password is incorrect.';}elseif(strlen($new)<6){car_sales_record_failed_attempt($rateKey, CAR_SALES_PASSWORD_MAX_ATTEMPTS, CAR_SALES_PASSWORD_LOCK_SECONDS);$error='New password must be at least 6 characters.';}elseif($new!==$confirm){car_sales_record_failed_attempt($rateKey, CAR_SALES_PASSWORD_MAX_ATTEMPTS, CAR_SALES_PASSWORD_LOCK_SECONDS);$error='New passwords do not match.';}else{car_sales_clear_rate_limit($rateKey);$hash=password_hash($new,PASSWORD_DEFAULT);$up=$conn->prepare('UPDATE users SET password=? WHERE id=?');$up->bind_param('si',$hash,$_SESSION['user_id']);$up->execute();set_flash('success','Password changed successfully.');redirect_to('backend/dashboard/index.php');}
    }
}
$page_title='Change Password';include __DIR__.'/includes/header.php';include __DIR__.'/includes/leftsidebar.php';
?>
<div class="content-page"><h1 class="page-title">Change Password</h1><div class="card form-card"><?php if($error):?><div class="flash-message error" style="position:static;margin-bottom:16px;"><?=e($error)?></div><?php endif;?><form method="post"><div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div><div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div><div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div><button class="btn">Change Password</button></form></div></div><?php include __DIR__.'/includes/footer.php'; ?>
