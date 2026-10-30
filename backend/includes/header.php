<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/business_helpers.php';
$company = (isset($conn) && $conn instanceof mysqli) ? car_sales_company_settings($conn) : ['company_name'=>'Car Sales System','logo_path'=>'backend/includes/images/logo.png'];
$page_title = $page_title ?? 'Car Sales System';
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title); ?> | <?= e($company['company_name'] ?: 'Car Sales System'); ?></title>
    <link rel="icon" href="<?= e(app_url($company['logo_path'] ?: 'backend/includes/images/logo.png')); ?>?v=5">
    <link rel="shortcut icon" href="<?= e(app_url($company['logo_path'] ?: 'backend/includes/images/logo.png')); ?>?v=5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= app_url('backend/includes/css/app.css'); ?>?v=4">
</head>
<body>
<header class="topbar">
    <button type="button" class="hamburger" id="mobileMenuToggle" aria-label="Open menu">☰</button>
    <a class="brand" href="<?= app_url('backend/dashboard/index.php'); ?>">
        <img src="<?= e(app_url($company['logo_path'] ?: 'backend/includes/images/logo.png')); ?>" alt="Logo">
        <span><?= e($company['company_name'] ?: 'Car Sales System'); ?></span>
    </a>
    <div class="topbar-right">
        <div class="profile-menu" id="profileMenu">
            <button type="button" class="profile-btn" id="profileToggle">
                <?= e($_SESSION['name'] ?? 'User'); ?> <span>▾</span>
            </button>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="<?= app_url('backend/profile.php'); ?>">Profile</a>
                <a href="<?= app_url('backend/change_password.php'); ?>">Change Password</a>
                <a href="<?= app_url('backend/authentication/logout.php'); ?>">Logout</a>
            </div>
        </div>
    </div>
</header>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php if ($flash): ?>
    <div class="flash-message flash-autohide <?= e($flash['type']); ?>" role="alert"><?= e($flash['message']); ?></div>
<?php endif; ?>
