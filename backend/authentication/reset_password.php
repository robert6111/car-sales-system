<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$token = $_GET['token'] ?? '';
$error='';
$success='';
$valid=false;
$user=null;
if ($token !== '') {
    $stmt=$conn->prepare('SELECT id,email FROM users WHERE reset_token=? AND reset_expires >= NOW() AND status=1 LIMIT 1');
    $stmt->bind_param('s',$token);
    $stmt->execute();
    $user=$stmt->get_result()->fetch_assoc();
    $valid=(bool)$user;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && $valid) {
    [$allowed, $waitMinutes] = car_sales_check_rate_limit('reset_password', CAR_SALES_PASSWORD_MAX_ATTEMPTS, CAR_SALES_PASSWORD_LOCK_SECONDS);
    if (!$allowed) {
        $error = 'Too many password reset attempts. Please try again in about ' . $waitMinutes . ' minute(s).';
    } else {
        $p=$_POST['password']??'';
        $c=$_POST['confirm_password']??'';
        if (strlen($p)<6) {
            car_sales_record_failed_attempt('reset_password', CAR_SALES_PASSWORD_MAX_ATTEMPTS, CAR_SALES_PASSWORD_LOCK_SECONDS);
            $error='Password must be at least 6 characters.';
        } elseif ($p!==$c) {
            car_sales_record_failed_attempt('reset_password', CAR_SALES_PASSWORD_MAX_ATTEMPTS, CAR_SALES_PASSWORD_LOCK_SECONDS);
            $error='Passwords do not match.';
        } else {
            car_sales_clear_rate_limit('reset_password');
            $hash=password_hash($p,PASSWORD_DEFAULT);
            $up=$conn->prepare('UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?');
            $up->bind_param('si',$hash,$user['id']);
            $up->execute();
            $success='Password reset successfully. You can login now.';
            $valid=false;
        }
    }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset Password</title><link rel="icon" type="image/png" href="<?= app_url('backend/includes/images/logo.png'); ?>"><link rel="stylesheet" href="<?= app_url('backend/includes/css/app.css'); ?>?v=4"></head><body class="auth-page"><div class="auth-card"><h1>Reset Password</h1><?php if(!$valid && !$success):?><div class="flash-message error" style="position:static;margin:14px 0;">Invalid or expired reset link.</div><?php endif;?><?php if($error):?><div class="flash-message error" style="position:static;margin:14px 0;"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="flash-message success" style="position:static;margin:14px 0;"><?=e($success)?></div><p><a href="login.php">Go to Login</a></p><?php endif;?><?php if($valid):?><form method="post"><div class="form-group"><label>New Password</label><input type="password" name="password" required></div><div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" required></div><button class="btn" style="width:100%;">Reset Password</button></form><?php endif;?></div></body></html>
