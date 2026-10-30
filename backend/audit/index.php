<?php
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
require_login();
if(!is_admin()){http_response_code(403);exit('Admin access required.');}
$action=trim($_GET['action']??'');$entity=trim($_GET['entity']??'');
$where=[];$types='';$vals=[];
if($action!==''){$where[]='a.action=?';$types.='s';$vals[]=$action;}
if($entity!==''){$where[]='a.entity_type=?';$types.='s';$vals[]=$entity;}
$sql='SELECT a.*,u.name user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY a.created_at DESC,a.id DESC LIMIT 500';
if($where){$st=$conn->prepare($sql);$st->bind_param($types,...$vals);$st->execute();$logs=$st->get_result();}else{$logs=$conn->query($sql);}
$voided=$conn->query('SELECT v.*,u.name voided_by_name,c.car_name,c.plate_number,c.chassis_number FROM voided_sales v LEFT JOIN users u ON u.id=v.voided_by JOIN cars c ON c.id=v.car_id ORDER BY v.voided_at DESC LIMIT 100');
$page_title='Activity Log';include __DIR__.'/../includes/header.php';include __DIR__.'/../includes/leftsidebar.php';
?>
<div class="content-page"><div class="report-title"><div><h1 class="page-title">Activity & Audit Log</h1><p class="muted">Administrative history of important changes, imports, payments and voided sales.</p></div></div>
<div class="card"><form method="get"><div class="form-row"><div class="form-group"><label>Action</label><input name="action" value="<?=e($action)?>" placeholder="CREATE, UPDATE, PAYMENT, VOID..."></div><div class="form-group"><label>Entity</label><input name="entity" value="<?=e($entity)?>" placeholder="sale, car, import..."></div></div><button class="btn">Filter</button> <a class="btn btn-dark" href="index.php">Clear</a></form></div>
<div class="card"><h2>Recent Activity</h2><div class="table-responsive"><table class="table"><thead><tr><th>Date/Time</th><th>User</th><th>Action</th><th>Area</th><th>Record</th><th>Description</th></tr></thead><tbody><?php while($r=$logs->fetch_assoc()):?><tr><td><?=e($r['created_at'])?></td><td><?=e($r['user_name']?:'System')?></td><td><strong><?=e($r['action'])?></strong></td><td><?=e($r['entity_type'])?></td><td><?=e($r['entity_id']?:'—')?></td><td><?=e($r['description'])?></td></tr><?php endwhile;?></tbody></table></div></div>
<div class="card"><h2>Voided Sales Archive</h2><p class="muted">Voided sales are archived here before the vehicle is returned to stock. The original transaction snapshot is retained for accountability.</p><div class="table-responsive"><table class="table"><thead><tr><th>Voided</th><th>Original Sale</th><th>Vehicle</th><th>Buyer</th><th>Price</th><th>Reason</th><th>Voided By</th></tr></thead><tbody><?php while($v=$voided->fetch_assoc()):?><tr><td><?=e($v['voided_at'])?></td><td>#<?=e($v['original_sale_id'])?></td><td><?=e($v['car_name'].' / '.car_sales_plate_label($v['plate_number']))?><br><small><?=e($v['chassis_number'])?></small></td><td><?=e($v['buyer_name']?:'—')?></td><td><?=money($v['sale_price'])?></td><td><?=e($v['void_reason'])?></td><td><?=e($v['voided_by_name']?:'System')?></td></tr><?php endwhile;?></tbody></table></div></div></div>
<?php include __DIR__.'/../includes/footer.php';?>
