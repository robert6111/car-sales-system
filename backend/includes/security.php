<?php
/**
 * Central security layer for the Car Sales System.
 * This file is loaded by backend/includes/functions.php.
 * It keeps the existing pages working while adding: secure sessions,
 * idle timeout, CSRF protection, route permissions, GET action tokens,
 * login/password throttling, and browser security headers.
 */

if (!defined('CAR_SALES_IDLE_TIMEOUT')) {
    // Seconds. 1800 = 30 minutes.
    define('CAR_SALES_IDLE_TIMEOUT', 1800);
}
if (!defined('CAR_SALES_LOGIN_MAX_ATTEMPTS')) {
    define('CAR_SALES_LOGIN_MAX_ATTEMPTS', 5);
}
if (!defined('CAR_SALES_LOGIN_LOCK_SECONDS')) {
    // 900 = 15 minutes.
    define('CAR_SALES_LOGIN_LOCK_SECONDS', 900);
}
if (!defined('CAR_SALES_PASSWORD_MAX_ATTEMPTS')) {
    define('CAR_SALES_PASSWORD_MAX_ATTEMPTS', 5);
}
if (!defined('CAR_SALES_PASSWORD_LOCK_SECONDS')) {
    define('CAR_SALES_PASSWORD_LOCK_SECONDS', 900);
}
if (!defined('CAR_SALES_BACKUP_MAX_PER_HOUR')) {
    define('CAR_SALES_BACKUP_MAX_PER_HOUR', 3);
}

function car_sales_starts_with(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) === 0;
}

function car_sales_contains(string $haystack, string $needle): bool
{
    return $needle !== '' && strpos($haystack, $needle) !== false;
}

function car_sales_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function car_sales_start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    // Unique per-app cookie: sibling apps on this host must not share a session.
    session_name('car_sales_session');

    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $params['path'] ?: '/',
        'domain' => $params['domain'] ?? '',
        'secure' => car_sales_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

car_sales_start_secure_session();

function car_sales_send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

car_sales_send_security_headers();

function car_sales_base_url(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($scriptName, '/backend/');
    if ($pos !== false) {
        return substr($scriptName, 0, $pos);
    }
    return rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
}

function car_sales_url(string $path = ''): string
{
    return rtrim(car_sales_base_url(), '/') . '/' . ltrim($path, '/');
}

function car_sales_redirect(string $path): void
{
    header('Location: ' . car_sales_url($path));
    exit;
}

function car_sales_current_route(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($scriptName, '/backend/');
    if ($pos !== false) {
        return ltrim(substr($scriptName, $pos + 1), '/');
    }
    return ltrim($scriptName, '/');
}

function car_sales_is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function car_sales_current_role(): string
{
    return $_SESSION['role'] ?? 'staff';
}

function car_sales_end_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], car_sales_is_https(), true);
    }
    session_destroy();
}

function car_sales_touch_activity_or_logout(): void
{
    if (!car_sales_is_logged_in()) {
        return;
    }

    $lastActivity = (int)($_SESSION['LAST_ACTIVITY'] ?? time());
    if ((time() - $lastActivity) > CAR_SALES_IDLE_TIMEOUT) {
        car_sales_end_session();
        car_sales_redirect('backend/authentication/login.php?timeout=1');
    }

    $_SESSION['LAST_ACTIVITY'] = time();
}

function car_sales_require_login(): void
{
    if (!car_sales_is_logged_in()) {
        car_sales_redirect('backend/authentication/login.php');
    }
    car_sales_touch_activity_or_logout();
}

function car_sales_permissions(): array
{
    return [
        'dashboard_view' => ['admin', 'staff'],
        'cars_manage' => ['admin', 'staff'],
        'costs_manage' => ['admin', 'staff'],
        'sales_manage' => ['admin', 'staff'],
        'customers_manage' => ['admin', 'staff'],
        'leads_manage' => ['admin', 'staff'],
        'dalali_manage' => ['admin', 'staff'],
        'reservations_manage' => ['admin', 'staff'],
        'testdrives_manage' => ['admin', 'staff'],
        'documents_manage' => ['admin', 'staff'],
        'imports_manage' => ['admin'],
        'settings_manage' => ['admin'],
        'audit_view' => ['admin'],
        'profile_manage' => ['admin', 'staff'],
        'reports_view' => ['admin'],
        'backups_manage' => ['admin'],
        'users_manage' => ['admin'],
    ];
}

function car_sales_user_has_permission(string $permission): bool
{
    $permissions = car_sales_permissions();
    if (!isset($permissions[$permission])) {
        return false;
    }
    return in_array(car_sales_current_role(), $permissions[$permission], true);
}

function car_sales_require_permission(string $permission): void
{
    car_sales_require_login();
    if (!car_sales_user_has_permission($permission)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access Denied</title></head><body style="font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a;padding:40px;"><div style="max-width:650px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:24px;"><h1>Access Denied</h1><p>You do not have permission to open this page.</p><p><a href="' . htmlspecialchars(car_sales_url('backend/dashboard/index.php'), ENT_QUOTES, 'UTF-8') . '">Go back to dashboard</a></p></div></body></html>';
        exit;
    }
}

function car_sales_route_permissions(): array
{
    return [
        'backend/dashboard/' => 'dashboard_view',
        'backend/cars/' => 'cars_manage',
        'backend/costs/' => 'costs_manage',
        'backend/sales/' => 'sales_manage',
        'backend/customers/' => 'customers_manage',
        'backend/leads/' => 'leads_manage',
        'backend/dalali/' => 'dalali_manage',
        'backend/reservations/' => 'reservations_manage',
        'backend/testdrives/' => 'testdrives_manage',
        'backend/documents/' => 'documents_manage',
        'backend/imports/' => 'imports_manage',
        'backend/settings/' => 'settings_manage',
        'backend/audit/' => 'audit_view',
        'backend/profile.php' => 'profile_manage',
        'backend/change_password.php' => 'profile_manage',
        'backend/reports/' => 'reports_view',
        'backend/backup/' => 'backups_manage',
        'backend/users/' => 'users_manage',
    ];
}

function car_sales_enforce_route_access(): void
{
    $route = car_sales_current_route();

    // Public authentication routes must remain accessible.
    if (car_sales_starts_with($route, 'backend/authentication/')) {
        return;
    }

    foreach (car_sales_route_permissions() as $prefix => $permission) {
        if ($route === $prefix || car_sales_starts_with($route, $prefix)) {
            car_sales_require_permission($permission);
            return;
        }
    }
}

function car_sales_csrf_token(): string
{
    if (empty($_SESSION['car_sales_csrf_token'])) {
        $_SESSION['car_sales_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['car_sales_csrf_token'];
}

function car_sales_csrf_input(): string
{
    return '<input type="hidden" name="car_sales_csrf_token" value="' . htmlspecialchars(car_sales_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function car_sales_validate_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $sent = $_POST['car_sales_csrf_token'] ?? '';
    $expected = $_SESSION['car_sales_csrf_token'] ?? '';

    if (!is_string($sent) || !is_string($expected) || $expected === '' || !hash_equals($expected, $sent)) {
        http_response_code(419);
        exit('Security check failed. Please go back, refresh the page, and try again.');
    }
}

function car_sales_action_token(string $action, string $value = ''): string
{
    $secret = car_sales_csrf_token();
    return hash_hmac('sha256', $action . '|' . $value, $secret);
}

function car_sales_action_url(string $url, string $action, string $value = ''): string
{
    $separator = car_sales_contains($url, '?') ? '&' : '?';
    return $url . $separator . 'action_token=' . urlencode(car_sales_action_token($action, $value));
}

function car_sales_validate_action_token(string $action, string $value = ''): void
{
    $sent = $_GET['action_token'] ?? '';
    $expected = car_sales_action_token($action, $value);
    if (!is_string($sent) || !hash_equals($expected, $sent)) {
        http_response_code(419);
        exit('Security token missing or invalid. Please go back, refresh the page, and try again.');
    }
}

function car_sales_check_rate_limit(string $key, int $maxAttempts, int $lockSeconds): array
{
    $now = time();
    $bucket = $_SESSION['rate_limits'][$key] ?? ['count' => 0, 'lock_until' => 0];

    if (!empty($bucket['lock_until']) && $bucket['lock_until'] > $now) {
        return [false, (int)ceil(($bucket['lock_until'] - $now) / 60)];
    }

    if (!empty($bucket['lock_until']) && $bucket['lock_until'] <= $now) {
        $_SESSION['rate_limits'][$key] = ['count' => 0, 'lock_until' => 0];
    }

    return [true, 0];
}

function car_sales_record_failed_attempt(string $key, int $maxAttempts, int $lockSeconds): void
{
    $now = time();
    $bucket = $_SESSION['rate_limits'][$key] ?? ['count' => 0, 'lock_until' => 0];
    $bucket['count'] = (int)($bucket['count'] ?? 0) + 1;

    if ($bucket['count'] >= $maxAttempts) {
        $bucket['lock_until'] = $now + $lockSeconds;
        $bucket['count'] = 0;
    }

    $_SESSION['rate_limits'][$key] = $bucket;
}

function car_sales_clear_rate_limit(string $key): void
{
    unset($_SESSION['rate_limits'][$key]);
}

function car_sales_check_hourly_limit(string $key, int $maxPerHour): bool
{
    $now = time();
    $bucket = $_SESSION['hourly_limits'][$key] ?? ['start' => $now, 'count' => 0];

    if (($now - (int)$bucket['start']) >= 3600) {
        $bucket = ['start' => $now, 'count' => 0];
    }

    if ((int)$bucket['count'] >= $maxPerHour) {
        $_SESSION['hourly_limits'][$key] = $bucket;
        return false;
    }

    $bucket['count']++;
    $_SESSION['hourly_limits'][$key] = $bucket;
    return true;
}

function car_sales_start_csrf_output_injection(): void
{
    if (defined('CAR_SALES_CSRF_BUFFER_STARTED')) {
        return;
    }
    define('CAR_SALES_CSRF_BUFFER_STARTED', true);

    ob_start(function ($buffer) {
        if (stripos($buffer, '<form') === false || stripos($buffer, 'method="post"') === false && stripos($buffer, "method='post'") === false && stripos($buffer, 'method=post') === false) {
            return $buffer;
        }

        return preg_replace_callback('/<form\b([^>]*)>/i', function ($matches) {
            $formTag = $matches[0];
            $attrs = $matches[1] ?? '';
            if (!preg_match('/method\s*=\s*(["\']?)post\1/i', $attrs)) {
                return $formTag;
            }
            if (stripos($formTag, 'car_sales_csrf_token') !== false) {
                return $formTag;
            }
            return $formTag . car_sales_csrf_input();
        }, $buffer) ?: $buffer;
    });
}

car_sales_validate_csrf();
car_sales_enforce_route_access();
car_sales_start_csrf_output_injection();
?>
