<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/send_email.php';
$error = '';
$success = '';
$resetLink = '';

function buildResetLink(string $token): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\','/', dirname($_SERVER['PHP_SELF'] ?? '')), '/');
    return $scheme . '://' . $host . $dir . '/reset_password.php?token=' . urlencode($token);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$allowed, $waitMinutes] = car_sales_check_rate_limit('forgot_password', CAR_SALES_PASSWORD_MAX_ATTEMPTS, CAR_SALES_PASSWORD_LOCK_SECONDS);
    if (!$allowed) {
        $error = 'Too many password reset attempts. Please try again in about ' . $waitMinutes . ' minute(s).';
    } else {
        car_sales_record_failed_attempt('forgot_password', CAR_SALES_PASSWORD_MAX_ATTEMPTS, CAR_SALES_PASSWORD_LOCK_SECONDS);
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error='Please enter a valid email address.';
        } else {
            $success = 'If the email exists and is active, a reset link has been prepared.';
            $stmt = $conn->prepare('SELECT id, status FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s',$email);
            $stmt->execute();
            $user=$stmt->get_result()->fetch_assoc();
            if ($user && (int)$user['status'] === 1) {
                $token = bin2hex(random_bytes(32));
                $expires=date('Y-m-d H:i:s', time()+3600);
                $up=$conn->prepare('UPDATE users SET reset_token=?, reset_expires=? WHERE id=?');
                $up->bind_param('ssi',$token,$expires,$user['id']);
                $up->execute();
                $resetLink=buildResetLink($token);
                $body='Use this link to reset your password:<br><a href="'.e($resetLink).'">'.e($resetLink).'</a><br>This link expires in 1 hour.';
                if (sendEmail($email,'Password Reset Request',$body) === true) {
                    $success='A reset link has been sent to your email.';
                    $resetLink='';
                } else {
                    $success='Email is not configured, so use the reset link below. The link expires in 1 hour.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Forgot Password</title><link rel="icon" type="image/png" href="<?= app_url('backend/includes/images/logo.png'); ?>"><link rel="stylesheet" href="<?= app_url('backend/includes/css/app.css'); ?>?v=4"></head><body class="auth-page"><div class="auth-card"><img class="logo-center" src="<?= app_url('backend/includes/images/logo.png'); ?>"><h1>Forgot Password</h1><p>Enter your email to prepare a secure reset link.</p><?php if($error):?><div class="flash-message error" style="position:static;margin:14px 0;"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="flash-message success" style="position:static;margin:14px 0;"><?=e($success)?></div><?php endif;?><?php if($resetLink):?><div class="card" style="word-break:break-all;"><strong>Reset link:</strong><br><a href="<?=e($resetLink)?>"><?=e($resetLink)?></a></div><?php endif;?><form method="post"><div class="form-group"><label>Email</label><input type="email" name="email" required></div><button class="btn" style="width:100%;">Prepare Reset Link</button></form><p><a href="login.php">Back to Login</a></p></div></body></html>
