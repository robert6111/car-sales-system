<?php require_once __DIR__ . '/functions.php'; ?>
<aside class="sidebar" id="sidebar">
    <button type="button" class="sidebar-close" id="sidebarClose">×</button>
    <div class="sidebar-title">Menu</div>
    <nav>
        <a href="<?= app_url('backend/dashboard/index.php'); ?>">Dashboard</a>
        <a href="<?= app_url('backend/cars/list.php'); ?>">Cars</a>
        <a href="<?= app_url('backend/cars/add.php'); ?>">Add Car</a>
        <a href="<?= app_url('backend/sales/index.php'); ?>">Sales</a>
        <a href="<?= app_url('backend/customers/index.php'); ?>">Customers</a>
        <a href="<?= app_url('backend/leads/index.php'); ?>">Sales Leads</a>
        <a href="<?= app_url('backend/reservations/index.php'); ?>">Reservations</a>
        <a href="<?= app_url('backend/testdrives/index.php'); ?>">Test Drives</a>
        <a href="<?= app_url('backend/dalali/index.php'); ?>">Dalali / Agents</a>
        <a href="<?= app_url('backend/documents/index.php'); ?>">Vehicle Documents</a>
        <?php if (car_sales_user_has_permission('imports_manage')): ?>
            <a href="<?= app_url('backend/imports/index.php'); ?>">Import from Excel</a>
        <?php endif; ?>
        <?php if (car_sales_user_has_permission('reports_view')): ?>
            <a href="<?= app_url('backend/reports/index.php'); ?>">Reports</a>
        <?php endif; ?>
        <?php if (car_sales_user_has_permission('backups_manage')): ?>
            <a href="<?= app_url('backend/backup/index.php'); ?>">Backups</a>
        <?php endif; ?>
        <?php if (is_admin()): ?>
            <a href="<?= app_url('backend/audit/index.php'); ?>">Activity Log</a>
            <a href="<?= app_url('backend/settings/index.php'); ?>">Company Settings</a>
            <a href="<?= app_url('backend/users/manage.php'); ?>">Manage Users</a>
        <?php endif; ?>
    </nav>
</aside>
