<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/backup_lib.php';

if(isset($_POST['backup_now'])){
    if (!car_sales_check_hourly_limit('backup_create_' . (int)$_SESSION['user_id'], CAR_SALES_BACKUP_MAX_PER_HOUR)) {
        set_flash('error','Backup limit reached. Please try again later.');
        redirect_to('backend/backup/index.php');
    }
    $backup=create_database_backup($conn);
    set_flash('success','Backup created successfully.');
    redirect_to('backend/backup/index.php');
}
$files=glob(car_sales_backup_dir().'/*.sql');
rsort($files);
$page_title='Backups';
include __DIR__.'/../includes/header.php';
include __DIR__.'/../includes/leftsidebar.php';
?>
<div class="content-page"><div class="report-title"><h1 class="page-title">Database Backups</h1><form method="post"><button class="btn btn-green" name="backup_now" value="1">Backup Now</button></form></div><div class="card"><p class="muted">Automatic daily backups are created when the dashboard is opened. You can also create and download backups here.</p><div class="table-responsive"><table class="table"><thead><tr><th>File</th><th>Size</th><th>Date</th><th>Action</th></tr></thead><tbody><?php if($files): foreach($files as $f): $name=basename($f);$downloadUrl=car_sales_action_url('download.php?file='.urlencode($name),'download_backup',$name);?><tr><td><?=e($name)?></td><td><?=money(filesize($f)/1024)?> KB</td><td><?=date('Y-m-d H:i:s',filemtime($f))?></td><td><a class="btn" href="<?=e($downloadUrl)?>">Download</a></td></tr><?php endforeach; else: ?><tr><td colspan="4">No backups yet.</td></tr><?php endif;?></tbody></table></div></div></div><?php include __DIR__.'/../includes/footer.php'; ?>
