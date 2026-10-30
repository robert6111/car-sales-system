<?php
require_once __DIR__ . '/security.php';

function app_url(string $path = ''): string {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($scriptName, '/backend/');
    if ($pos !== false) {
        $base = substr($scriptName, 0, $pos);
    } else {
        $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money($value): string {
    return number_format((float)$value, 2, '.', ',');
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function redirect_to(string $path): void {
    header('Location: ' . app_url($path));
    exit;
}

function current_user_role(): string {
    return $_SESSION['role'] ?? 'staff';
}

function is_admin(): bool {
    return current_user_role() === 'admin';
}
?>
