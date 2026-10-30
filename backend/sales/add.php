<?php
require_once __DIR__.'/../../includes/db.php';require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/business_helpers.php';require_login();
if(!isset($_GET['car_id'])||!is_numeric($_GET['car_id'])){set_flash('error','Car ID missing.');redirect_to('backend/cars/list.php');}$car_id=(int)$_GET['car_id'];
$car_q=$conn->prepare('SELECT id,car_name,plate_number,purchase_price,asking_price,status FROM cars WHERE id=?');$car_q->bind_param('i',$car_id);$car_q->execute();$car=$car_q->get_result()->fetch_assoc();if(!$car){set_flash('error','Car not found.');redirect_to('backend/cars/list.php');}if($car['status']==='SOLD'){set_flash('error','This car is already sold.');redirect_to('backend/cars/view.php?id='.$car_id);}
$total_stmt=$conn->prepare('SELECT IFNULL(SUM(amount),0) total_cost FROM car_costs WHERE car_id=?');$total_stmt->bind_param('i',$car_id);$total_stmt->execute();$total=$total_stmt->get_result()->fetch_assoc();
$reservation=null;$reservationId=(int)($_GET['reservation_id']??0);if($reservationId){$q=$conn->prepare("SELECT * FROM reservations WHERE id=? AND car_id=? AND status='ACTIVE'");$q->bind_param('ii',$reservationId,$car_id);$q->execute();$reservation=$q->get_result()->fetch_assoc();}else{$q=$conn->prepare("SELECT * FROM reservations WHERE car_id=? AND status='ACTIVE' AND (expiry_date IS NULL OR expiry_date>=CURDATE()) ORDER BY id DESC LIMIT 1");$q->bind_param('i',$car_id);$q->execute();$reservation=$q->get_result()->fetch_assoc();}
$dalalis=$conn->query("SELECT id,name,phone,default_commission_type,default_commission_value FROM dalalis WHERE status='ACTIVE' ORDER BY name");$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $sale_price=(float)($_POST['sale_price']??0);
    $sale_date=$_POST['sale_date']??'';
    $buyer_name=trim($_POST['buyer_name']??'');
    $buyer_phone=trim($_POST['buyer_phone']??'');
    $buyer_email=trim($_POST['buyer_email']??'');
    $notes=trim($_POST['notes']??'');
    $dalali_id=(int)($_POST['dalali_id']??0);
    $commission_type=strtoupper(trim($_POST['commission_type']??'FIXED'));
    if(!in_array($commission_type,['FIXED','PERCENT'],true))$commission_type='FIXED';
    $commission_value=(float)($_POST['commission_value']??0);
    $initialRaw=trim((string)($_POST['initial_payment']??''));
    $payment_method=trim($_POST['payment_method']??'');
    $payment_ref=trim($_POST['payment_reference']??'');
    $uid=(int)$_SESSION['user_id'];
    $postedReservation=(int)($_POST['reservation_id']??0);
    $deposit=0;
    $postedReservationRow=null;
    if($postedReservation){
        $q=$conn->prepare("SELECT * FROM reservations WHERE id=? AND car_id=? AND status='ACTIVE'");
        $q->bind_param('ii',$postedReservation,$car_id);$q->execute();$postedReservationRow=$q->get_result()->fetch_assoc();
        if($postedReservationRow){$deposit=(float)$postedReservationRow['deposit_amount'];}
    }
    $additional_payment=$initialRaw===''?max(0,$sale_price-$deposit):(float)$initialRaw;
    if($sale_price<=0||$sale_date===''||$buyer_name===''){
        $error='Please fill all required fields correctly.';
    }elseif($postedReservation && !$postedReservationRow){
        $error='The selected reservation is no longer active for this vehicle. Refresh the page before recording the sale.';
    }elseif($additional_payment<0){
        $error='Payment received now cannot be negative.';
    }elseif($commission_value<0){
        $error='Dalali commission cannot be negative.';
    }elseif($additional_payment+$deposit>$sale_price+0.01){
        $error='Total deposit and payment cannot be greater than the sale price.';
    }else{
        $conn->begin_transaction();
        try{
            $customer_id=find_or_create_customer($conn,$buyer_name,$buyer_phone,$buyer_email,$uid);
            if($customer_id<=0)throw new RuntimeException('Could not create or match buyer.');
            $nullDalali=$dalali_id?:null;
            $nullReservation=$postedReservation?:null;
            $paidNow=$additional_payment+$deposit;
            $paymentStatus=$paidNow>=$sale_price?'PAID':($paidNow>0?'PARTIAL':'UNPAID');

            $stmt=$conn->prepare('INSERT INTO car_sales (car_id,customer_id,dalali_id,reservation_id,sale_price,sale_date,payment_status,payment_method,buyer_name,buyer_phone,buyer_email,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
            if(!$stmt)throw new RuntimeException('Could not prepare sale record.');
            $stmt->bind_param('iiiidsssssss',$car_id,$customer_id,$nullDalali,$nullReservation,$sale_price,$sale_date,$paymentStatus,$payment_method,$buyer_name,$buyer_phone,$buyer_email,$notes);
            if(!$stmt->execute() || $stmt->insert_id<=0)throw new RuntimeException('Could not create sale record.');
            $saleId=(int)$stmt->insert_id;

            $stmt2=$conn->prepare("UPDATE cars SET status='SOLD' WHERE id=? AND status='IN_STOCK'");
            if(!$stmt2)throw new RuntimeException('Could not prepare stock update.');
            $stmt2->bind_param('i',$car_id);
            if(!$stmt2->execute() || $stmt2->affected_rows!==1)throw new RuntimeException('Vehicle is no longer available for sale.');

            if($deposit>0){
                $p=$conn->prepare("INSERT INTO sale_payments(sale_id,amount,payment_date,method,notes,received_by) VALUES(?,?,?,'Reservation Deposit','Converted from reservation',?)");
                if(!$p)throw new RuntimeException('Could not prepare reservation deposit payment.');
                $p->bind_param('idsi',$saleId,$deposit,$sale_date,$uid);
                if(!$p->execute())throw new RuntimeException('Could not carry reservation deposit into sale.');
            }
            if($additional_payment>0){
                $p=$conn->prepare("INSERT INTO sale_payments(sale_id,amount,payment_date,method,reference_number,notes,received_by) VALUES(?,?,?,?,?,'Initial sale payment',?)");
                if(!$p)throw new RuntimeException('Could not prepare initial payment.');
                $p->bind_param('idsssi',$saleId,$additional_payment,$sale_date,$payment_method,$payment_ref,$uid);
                if(!$p->execute())throw new RuntimeException('Could not record initial payment.');
            }
            if($postedReservation){
                $r=$conn->prepare("UPDATE reservations SET status='CONVERTED' WHERE id=? AND car_id=? AND status='ACTIVE'");
                if(!$r)throw new RuntimeException('Could not prepare reservation conversion.');
                $r->bind_param('ii',$postedReservation,$car_id);
                if(!$r->execute() || $r->affected_rows!==1)throw new RuntimeException('Reservation changed before the sale could be completed.');
            }

            $leadWin=$conn->prepare("UPDATE leads SET lead_stage='WON' WHERE customer_id=? AND (car_id=? OR car_id IS NULL) AND lead_stage NOT IN ('WON','LOST')");
            if($leadWin){$leadWin->bind_param('ii',$customer_id,$car_id);$leadWin->execute();}

            if($dalali_id){
                $checkRef=$conn->prepare("SELECT id FROM dalali_referrals WHERE dalali_id=? AND customer_id=? AND (car_id=? OR car_id IS NULL) ORDER BY id DESC LIMIT 1");
                if(!$checkRef)throw new RuntimeException('Could not prepare dalali referral lookup.');
                $checkRef->bind_param('iii',$dalali_id,$customer_id,$car_id);$checkRef->execute();$existingRef=$checkRef->get_result()->fetch_assoc();
                if($existingRef){
                    $rid=(int)$existingRef['id'];$rr=$conn->prepare("UPDATE dalali_referrals SET status='WON',car_id=? WHERE id=?");
                    if(!$rr)throw new RuntimeException('Could not prepare dalali referral update.');$rr->bind_param('ii',$car_id,$rid);if(!$rr->execute())throw new RuntimeException('Could not update dalali referral.');
                }else{
                    $rr=$conn->prepare("INSERT INTO dalali_referrals(dalali_id,customer_id,car_id,status,notes) VALUES(?,?,?,'WON','Direct sale referral')");
                    if(!$rr)throw new RuntimeException('Could not prepare dalali referral.');$rr->bind_param('iii',$dalali_id,$customer_id,$car_id);if(!$rr->execute())throw new RuntimeException('Could not record dalali referral.');
                }
            }

            if($dalali_id && $commission_value>0){
                $commissionAmount=$commission_type==='PERCENT'?($sale_price*$commission_value/100):$commission_value;
                $desc='Dalali commission: sale #'.$saleId;
                $cost=$conn->prepare("INSERT INTO car_costs(car_id,cost_type,description,amount,cost_date) VALUES(?,'Dalali Commission',?,?,?)");
                if(!$cost)throw new RuntimeException('Could not prepare dalali commission cost.');
                $cost->bind_param('isds',$car_id,$desc,$commissionAmount,$sale_date);
                if(!$cost->execute() || $cost->insert_id<=0)throw new RuntimeException('Could not record dalali commission cost.');
                $costId=(int)$cost->insert_id;
                $dc=$conn->prepare('INSERT INTO dalali_commissions(dalali_id,sale_id,car_id,cost_id,commission_type,commission_value,amount,due_date,notes) VALUES(?,?,?,?,?,?,?,?,?)');
                if(!$dc)throw new RuntimeException('Could not prepare dalali commission.');
                $due=$sale_date;$dc->bind_param('iiiisddss',$dalali_id,$saleId,$car_id,$costId,$commission_type,$commission_value,$commissionAmount,$due,$notes);
                if(!$dc->execute())throw new RuntimeException('Could not record dalali commission.');
                $refWin=$conn->prepare("UPDATE dalali_referrals SET status='WON' WHERE dalali_id=? AND car_id=? AND status='OPEN'");
                if($refWin){$refWin->bind_param('ii',$dalali_id,$car_id);$refWin->execute();}
            }

            sync_sale_payment_status($conn,$saleId);
            car_sales_audit($conn,'CREATE','sale',$saleId,'Recorded vehicle sale for '.$buyer_name.'.',null,['car_id'=>$car_id,'sale_price'=>$sale_price,'sale_date'=>$sale_date,'buyer_name'=>$buyer_name,'dalali_id'=>$dalali_id?:null,'initial_paid'=>$paidNow]);
            if(!$conn->commit())throw new RuntimeException('Could not commit sale transaction.');
            set_flash('success','Sale recorded successfully.');redirect_to('backend/sales/receipt.php?id='.$saleId);
        }catch(Throwable $e){
            $conn->rollback();
            $error='Could not record sale. The vehicle may have changed, or one of the related records could not be saved.';
        }
    }
}
$page_title='Add Sale';include __DIR__.'/../includes/header.php';include __DIR__.'/../includes/leftsidebar.php';?>
<div class="content-page"><h1 class="page-title">Add Sale</h1><div class="card form-card"><p><strong>Car:</strong> <?=e($car['car_name'].' / '.car_sales_plate_label($car['plate_number']))?></p><p><strong>Purchase Price:</strong> <?=money($car['purchase_price'])?></p><p><strong>Total Costs:</strong> <?=money($total['total_cost'])?></p><?php if($reservation):?><div class="card" style="background:#fff7ed"><strong>Active reservation:</strong> <?=e($reservation['customer_name'])?>, deposit <?=money($reservation['deposit_amount'])?></div><?php endif;?><div class="card" id="profit-preview" style="background:#eef2ff;"><strong>Expected Profit (including dalali commission):</strong> <span id="profit-value">0.00</span></div><?php if($error):?><div class="flash-message error" style="position:static;margin-bottom:16px;"> <?=e($error)?></div><?php endif;?><form method="post"><input type="hidden" name="reservation_id" value="<?=$reservation?(int)$reservation['id']:0?>"><div class="form-row"><div class="form-group"><label>Sale Price *</label><input type="number" name="sale_price" id="sale_price" step="0.01" value="<?=e($car['asking_price']?:'')?>" required></div><div class="form-group"><label>Sale Date *</label><input type="date" name="sale_date" value="<?=date('Y-m-d')?>" required></div></div><h3>Buyer</h3><div class="form-group"><label>Buyer Name *</label><input type="text" name="buyer_name" value="<?=e($reservation['customer_name']??'')?>" required></div><div class="form-row"><div class="form-group"><label>Buyer Phone</label><input type="text" name="buyer_phone" value="<?=e($reservation['customer_phone']??'')?>"></div><div class="form-group"><label>Buyer Email</label><input type="email" name="buyer_email"></div></div><h3>Payment</h3><?php if($reservation):?><p class="muted">Reservation deposit already collected: <strong><?=money($reservation['deposit_amount'])?></strong>. Enter only any additional amount being paid now.</p><?php endif;?><div class="form-row"><div class="form-group"><label>Payment Received Now</label><input type="number" name="initial_payment" step="0.01" min="0" placeholder="Leave blank for full payment"><small class="muted">Leave blank to treat the sale as fully paid. Enter 0 for no additional payment.</small></div><div class="form-group"><label>Payment Method</label><select name="payment_method"><option>Cash</option><option>Bank Transfer</option><option>Mobile Money</option><option>Cheque</option><option>Other</option></select></div></div><div class="form-group"><label>Payment Reference</label><input name="payment_reference"></div><h3>Dalali / Agent (optional)</h3><div class="form-row"><div class="form-group"><label>Dalali</label><select name="dalali_id" id="dalali_id"><option value="0" data-type="FIXED" data-value="0">Direct sale / None</option><?php while($d=$dalalis->fetch_assoc()):?><option value="<?=$d['id']?>" data-type="<?=e($d['default_commission_type'])?>" data-value="<?=e($d['default_commission_value'])?>"><?=e($d['name'].' - '.$d['phone'])?></option><?php endwhile;?></select></div><div class="form-group"><label>Commission Type</label><select name="commission_type" id="commission_type"><option value="FIXED">Fixed Amount</option><option value="PERCENT">Percentage of Sale</option></select></div></div><div class="form-group"><label>Commission Value</label><input type="number" name="commission_value" id="commission_value" step="0.01" min="0" value="0"><small class="muted">For percentage, enter e.g. 2 for 2%.</small></div><div class="form-group"><label>Notes</label><textarea name="notes" rows="3"></textarea></div><button class="btn btn-green" type="submit">Confirm Sale</button><a class="btn btn-dark" href="../cars/view.php?id=<?=$car_id?>">Cancel</a></form></div></div><script>const saleInput=document.getElementById('sale_price'),profitValue=document.getElementById('profit-value'),purchasePrice=<?=(float)$car['purchase_price']?>,totalCost=<?=(float)$total['total_cost']?>,dalali=document.getElementById('dalali_id'),ctype=document.getElementById('commission_type'),cvalue=document.getElementById('commission_value');function fmt(x){return x.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}function update(){const sp=parseFloat(saleInput.value)||0;let cv=parseFloat(cvalue.value)||0;let comm=ctype.value==='PERCENT'?sp*cv/100:cv;profitValue.textContent=fmt(sp-(purchasePrice+totalCost+comm));}dalali.addEventListener('change',()=>{const o=dalali.options[dalali.selectedIndex];ctype.value=o.dataset.type||'FIXED';cvalue.value=o.dataset.value||0;update();});[saleInput,ctype,cvalue].forEach(x=>x.addEventListener('input',update));update();</script><?php include __DIR__.'/../includes/footer.php';?>
