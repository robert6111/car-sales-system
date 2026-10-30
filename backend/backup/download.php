<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/backup_lib.php';
$file=basename($_GET['file'] ?? '');
car_sales_validate_action_token('download_backup', $file);
if (!car_sales_check_hourly_limit('backup_download_' . (int)$_SESSION['user_id'], CAR_SALES_BACKUP_MAX_PER_HOUR)) {
    http_response_code(429);
    exit('Backup download limit reached. Please try again later.');
}
$path=car_sales_backup_dir().'/'.$file;
if($file===''||!is_file($path)){http_response_code(404);exit('Backup not found');}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.$file.'"');
header('Content-Length: '.filesize($path));
readfile($path);exit;
?>
