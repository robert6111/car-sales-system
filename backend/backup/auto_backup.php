<?php
try {
    require_once __DIR__ . '/backup_lib.php';
    if (isset($conn) && $conn instanceof mysqli) {
        $flag = car_sales_backup_dir() . '/.last_auto_backup';
        $today = date('Y-m-d');
        $last = is_file($flag) ? trim((string)@file_get_contents($flag)) : '';
        if ($last !== $today) {
            create_database_backup($conn);
            @file_put_contents($flag, $today);
        }
    }
} catch (Throwable $e) {
    // Backup should never block login/dashboard.
}
?>
