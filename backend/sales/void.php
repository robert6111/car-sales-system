<?php
require_once __DIR__.'/../../includes/db.php';require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/business_helpers.php';require_login();
if(!is_admin()){http_response_code(403);exit('Admin access required.');}
$id=(int)($_GET['id']??0);$st=$conn->prepare('SELECT s.*,c.car_name,c.plate_number,c.chassis_number FROM car_sales s JOIN cars c ON c.id=s.car_id WHERE s.id=?');$st->bind_param('i',$id);$st->execute();$sale=$st->get_result()->fetch_assoc();if(!$sale){set_flash('error','Sale not found.');redirect_to('backend/sales/index.php');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $reason=trim($_POST['void_reason']??'');
    if(strlen($reason)<5){$error='Enter a clear reason for voiding this sale.';}else{
        $p=$conn->prepare('SELECT * FROM sale_payments WHERE sale_id=? ORDER BY id');$p->bind_param('i',$id);$p->execute();$payments=$p->get_result()->fetch_all(MYSQLI_ASSOC);
        $c=$conn->prepare('SELECT * FROM dalali_commissions WHERE sale_id=? LIMIT 1');$c->bind_param('i',$id);$c->execute();$commission=$c->get_result()->fetch_assoc();
        $uid=(int)$_SESSION['user_id'];$conn->begin_transaction();
        try{
            $saleJson=json_encode($sale,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$payJson=json_encode($payments,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$commJson=$commission?json_encode($commission,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;$saleCarId=(int)$sale['car_id'];$saleDate=(string)$sale['sale_date'];$saleBuyer=(string)$sale['buyer_name'];$salePrice=(float)$sale['sale_price'];
            $ar=$conn->prepare('INSERT INTO voided_sales(original_sale_id,car_id,sale_date,buyer_name,sale_price,void_reason,voided_by,sale_payload,payments_payload,commission_payload) VALUES(?,?,?,?,?,?,?,?,?,?)');
            $ar->bind_param('iissdsisss',$id,$saleCarId,$saleDate,$saleBuyer,$salePrice,$reason,$uid,$saleJson,$payJson,$commJson);if(!$ar->execute() || $ar->insert_id<=0){throw new RuntimeException('Could not archive original sale.');}$archiveId=(int)$ar->insert_id;
            if($commission && !empty($commission['cost_id'])){$costId=(int)$commission['cost_id'];$delCost=$conn->prepare('DELETE FROM car_costs WHERE id=? AND car_id=?');$delCost->bind_param('ii',$costId,$saleCarId);if(!$delCost->execute()){throw new RuntimeException('Could not reverse dalali commission cost.');}}
            $del=$conn->prepare('DELETE FROM car_sales WHERE id=?');$del->bind_param('i',$id);if(!$del->execute() || $del->affected_rows!==1){throw new RuntimeException('Could not remove active sale after archiving.');}
            $car=$conn->prepare("UPDATE cars SET status='IN_STOCK' WHERE id=?");$car->bind_param('i',$saleCarId);if(!$car->execute()){throw new RuntimeException('Could not return vehicle to stock.');}
            car_sales_audit($conn,'VOID','sale',(string)$id,'Voided sale and returned vehicle to stock. Reason: '.$reason,$sale,['void_archive_id'=>$archiveId,'car_status'=>'IN_STOCK']);
            $conn->commit();set_flash('success','Sale voided safely. The original transaction was archived and the car returned to stock.');redirect_to('backend/sales/index.php');
        }catch(Throwable $e){$conn->rollback();$error='Could not void the sale. No changes were saved.';}
    }
}
$page_title='Void Sale';include __DIR__.'/../includes/header.php';include __DIR__.'/../includes/leftsidebar.php';?>
<div class="content-page"><h1 class="page-title">Void Sale #<?=e($id)?></h1><div class="card form-card"><div class="flash-message error" style="position:static;margin-bottom:16px"><strong>Administrative correction only.</strong> This does not erase history. The sale, payments and commission snapshot will be archived and the vehicle will return to stock.</div><?php if($error):?><div class="flash-message error" style="position:static;margin-bottom:16px"><?=e($error)?></div><?php endif;?><p><strong>Vehicle:</strong> <?=e($sale['car_name'].' / '.car_sales_plate_label($sale['plate_number']))?><br><strong>Chassis:</strong> <?=e($sale['chassis_number'])?><br><strong>Buyer:</strong> <?=e($sale['buyer_name'])?><br><strong>Sale price:</strong> <?=money($sale['sale_price'])?></p><form method="post"><div class="form-group"><label>Reason for voiding *</label><textarea name="void_reason" rows="4" required placeholder="Example: Sale price entered incorrectly; transaction was never completed."></textarea></div><button class="btn btn-red" type="submit" onclick="return confirm('Void this sale and return the vehicle to stock?')">Void Sale</button> <a class="btn btn-dark" href="index.php">Cancel</a></form></div></div>
<?php include __DIR__.'/../includes/footer.php';?>
