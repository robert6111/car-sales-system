<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect_to('backend/dashboard/index.php');
}

$error = '';
if (isset($_GET['timeout'])) {
    $error = 'Your session expired because of inactivity. Please login again.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$allowed, $waitMinutes] = car_sales_check_rate_limit('login', CAR_SALES_LOGIN_MAX_ATTEMPTS, CAR_SALES_LOGIN_LOCK_SECONDS);
    if (!$allowed) {
        $error = 'Too many failed login attempts. Please try again in about ' . $waitMinutes . ' minute(s).';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Please enter email and password.';
        } else {
            $stmt = $conn->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1");

            if (!$stmt) {
                $error = 'Database is not installed correctly. Please import backend/database/database.sql into the car_sales_system database.';
            } else {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result ? $result->fetch_assoc() : null;

                if ($user && (int)$user['status'] === 1 && password_verify($password, $user['password'])) {
                    car_sales_clear_rate_limit('login');
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['LAST_ACTIVITY'] = time();
                    redirect_to('backend/dashboard/index.php');
                } else {
                    car_sales_record_failed_attempt('login', CAR_SALES_LOGIN_MAX_ATTEMPTS, CAR_SALES_LOGIN_LOCK_SECONDS);
                    $error = 'Invalid login details or inactive account.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Car Sales System</title>
    <link rel="icon" type="image/png" href="<?= app_url('backend/includes/images/logo.png'); ?>?v=2">
    <link rel="shortcut icon" type="image/png" href="<?= app_url('backend/includes/images/logo.png'); ?>?v=2">
    <link rel="stylesheet" href="<?= app_url('backend/includes/css/app.css'); ?>?v=4">
</head>
<body class="auth-page">
    <div class="auth-card">
        <img class="logo-center" src="<?= app_url('backend/includes/images/logo.png'); ?>" alt="Logo">
        <h1>Welcome Back</h1>
        <p>Login to manage car sales.</p>

        <?php if ($error): ?>
            <div class="flash-message error" style="position:static;margin:14px 0;">
                <?= e($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button class="btn" style="width:100%;" type="submit">Login</button>
        </form>

        <p><a href="forgot_password.php">Forgot password?</a></p>
    </div>
</body>
</html>
