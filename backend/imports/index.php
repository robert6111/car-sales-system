<?php
require_once __DIR__.'/../../includes/db.php';require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/business_helpers.php';require_once __DIR__.'/../includes/simple_xlsx.php';require_login();
if(!is_admin()){http_response_code(403);exit('Admin access required.');}
$types=[
 'cars'=>['label'=>'Cars / Vehicle Stock','template'=>'cars_import_template.xlsx','required'=>'car_name, chassis_number, purchase_price, purchase_date'],
 'customers'=>['label'=>'Customers','template'=>'customers_import_template.xlsx','required'=>'name'],
 'dalali'=>['label'=>'Dalali / Agents','template'=>'dalali_import_template.xlsx','required'=>'name, phone'],
 'leads'=>['label'=>'Sales Leads','template'=>'leads_import_template.xlsx','required'=>'customer_name'],
 'documents'=>['label'=>'Vehicle Document Register','template'=>'vehicle_documents_import_template.xlsx','required'=>'chassis_number, document_type'],
 'costs'=>['label'=>'Vehicle Costs','template'=>'vehicle_costs_import_template.xlsx','required'=>'chassis_number, cost_type, amount, cost_date'],
 'sales'=>['label'=>'Historical Sales','template'=>'historical_sales_import_template.xlsx','required'=>'chassis_number, sale_price, sale_date, buyer_name, amount_paid'],
];
function imp_car(mysqli $conn,string $chassis): ?array{$chassis=strtoupper(trim($chassis));$st=$conn->prepare('SELECT * FROM cars WHERE chassis_number=? LIMIT 1');$st->bind_param('s',$chassis);$st->execute();$r=$st->get_result()->fetch_assoc();return $r?:null;}
function imp_dalali(mysqli $conn,string $phone): ?array{$phone=trim($phone);if($phone==='')return null;$st=$conn->prepare('SELECT * FROM dalalis WHERE phone=? LIMIT 1');$st->bind_param('s',$phone);$st->execute();$r=$st->get_result()->fetch_assoc();return $r?:null;}
function imp_num($r,$k,float $default=0): float{$v=trim((string)($r[$k]??''));return $v===''?$default:(float)str_replace([',',' '],'',$v);}
function imp_int($r,$k,int $default=0): int{$v=trim((string)($r[$k]??''));return $v===''?$default:(int)str_replace([',',' '],'',$v);}
function imp_text($r,$k,string $default=''): string{return trim((string)($r[$k]??$default));}
function imp_insert_record(mysqli $conn,string $type,array $r,int $uid): string{
    if($type==='cars'){
        $name=imp_text($r,'car_name');$ch=strtoupper(imp_text($r,'chassis_number'));$plate=strtoupper(imp_text($r,'plate_number'));$plate=$plate===''?null:$plate;$price=imp_num($r,'purchase_price');$date=car_sales_normalize_date($r['purchase_date']??'');
        if($name===''||$ch===''||$price<=0||!$date)throw new RuntimeException('Missing/invalid required vehicle fields.');if(imp_car($conn,$ch))throw new RuntimeException('Chassis already exists.');
        $st=$conn->prepare('INSERT INTO cars(user_id,car_name,plate_number,chassis_number,purchase_price,purchase_date) VALUES(?,?,?,?,?,?)');$st->bind_param('isssds',$uid,$name,$plate,$ch,$price,$date);if(!$st->execute())throw new RuntimeException('Could not insert vehicle; supplied plate may already exist.');$id=(int)$st->insert_id;
        $stock=strtoupper(imp_text($r,'stock_type','OWNED'));if(!in_array($stock,['OWNED','CONSIGNMENT','TRADE_IN'],true))$stock='OWNED';$make=imp_text($r,'make');$model=imp_text($r,'model');$year=imp_int($r,'model_year');$color=imp_text($r,'color');$mileage=imp_int($r,'mileage');$trans=imp_text($r,'transmission');$fuel=imp_text($r,'fuel_type');$engine=imp_text($r,'engine_number');$asking=imp_num($r,'asking_price');$supplier=imp_text($r,'supplier_name');$supplierPhone=imp_text($r,'supplier_phone');$owner=imp_text($r,'owner_name');$ownerPhone=imp_text($r,'owner_phone');$yard=imp_text($r,'yard_location');$notes=imp_text($r,'vehicle_notes');
        $up=$conn->prepare('UPDATE cars SET stock_type=?,make=?,model=?,model_year=NULLIF(?,0),color=?,mileage=NULLIF(?,0),transmission=?,fuel_type=?,engine_number=?,asking_price=NULLIF(?,0),supplier_name=?,supplier_phone=?,owner_name=?,owner_phone=?,yard_location=?,vehicle_notes=? WHERE id=?');$up->bind_param('sssisisssdssssssi',$stock,$make,$model,$year,$color,$mileage,$trans,$fuel,$engine,$asking,$supplier,$supplierPhone,$owner,$ownerPhone,$yard,$notes,$id);if(!$up->execute())throw new RuntimeException('Could not save extended vehicle details.');return 'Car #'.$id;
    }
    if($type==='customers'){
        $name=imp_text($r,'name');if($name==='')throw new RuntimeException('Customer name is required.');$phone=imp_text($r,'phone');$email=imp_text($r,'email');
        if($phone!==''){$x=$conn->prepare('SELECT id FROM customers WHERE phone=? LIMIT 1');$x->bind_param('s',$phone);$x->execute();if($x->get_result()->fetch_assoc())throw new RuntimeException('Customer phone already exists.');}
        $nid=imp_text($r,'national_id');$address=imp_text($r,'address');$ct=strtoupper(imp_text($r,'customer_type','BUYER'));if(!in_array($ct,['BUYER','SELLER','BOTH'],true))$ct='BUYER';$source=imp_text($r,'source');$notes=imp_text($r,'notes');$st=$conn->prepare('INSERT INTO customers(name,phone,email,national_id,address,customer_type,source,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?)');$st->bind_param('ssssssssi',$name,$phone,$email,$nid,$address,$ct,$source,$notes,$uid);if(!$st->execute())throw new RuntimeException('Could not insert customer.');return 'Customer #'.$st->insert_id;
    }
    if($type==='dalali'){
        $name=imp_text($r,'name');$phone=imp_text($r,'phone');if($name===''||$phone==='')throw new RuntimeException('Dalali name and phone are required.');if(imp_dalali($conn,$phone))throw new RuntimeException('Dalali phone already exists.');$email=imp_text($r,'email');$nid=imp_text($r,'national_id');$address=imp_text($r,'address');$ct=strtoupper(imp_text($r,'default_commission_type','FIXED'));if(!in_array($ct,['FIXED','PERCENT'],true))$ct='FIXED';$cv=imp_num($r,'default_commission_value');$status=strtoupper(imp_text($r,'status','ACTIVE'));if(!in_array($status,['ACTIVE','INACTIVE'],true))$status='ACTIVE';$notes=imp_text($r,'notes');$st=$conn->prepare('INSERT INTO dalalis(name,phone,email,national_id,address,default_commission_type,default_commission_value,status,notes) VALUES(?,?,?,?,?,?,?,?,?)');$st->bind_param('ssssssdss',$name,$phone,$email,$nid,$address,$ct,$cv,$status,$notes);if(!$st->execute())throw new RuntimeException('Could not insert dalali.');return 'Dalali #'.$st->insert_id;
    }
    if($type==='leads'){
        $name=imp_text($r,'customer_name');if($name==='')throw new RuntimeException('Customer name is required.');$phone=imp_text($r,'phone');$email=imp_text($r,'email');$customerId=find_or_create_customer($conn,$name,$phone,$email,$uid);$carId=null;$ch=imp_text($r,'chassis_number');if($ch!==''){$car=imp_car($conn,$ch);if(!$car)throw new RuntimeException('Vehicle chassis not found. Import the car first.');$carId=(int)$car['id'];}$dalaliId=null;$dp=imp_text($r,'dalali_phone');if($dp!==''){$d=imp_dalali($conn,$dp);if(!$d)throw new RuntimeException('Dalali phone not found. Import the dalali first.');$dalaliId=(int)$d['id'];}$source=imp_text($r,'source');$bmin=imp_num($r,'budget_min');$bmax=imp_num($r,'budget_max');$stage=strtoupper(imp_text($r,'lead_stage','NEW'));if(!in_array($stage,['NEW','CONTACTED','FOLLOW_UP','NEGOTIATING','WON','LOST'],true))$stage='NEW';$oldStatus=in_array($stage,['WON','LOST'],true)?'CLOSED':($stage==='NEW'?'NEW':'FOLLOW_UP');$follow=car_sales_normalize_date($r['next_follow_up']??'');$notes=imp_text($r,'notes');$lost=imp_text($r,'lost_reason');
        $st=$conn->prepare('INSERT INTO leads(car_id,customer_id,dalali_id,customer_name,phone,email,source,budget_min,budget_max,status,lead_stage,next_follow_up,notes,lost_reason,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$st->bind_param('iiissssddsssssi',$carId,$customerId,$dalaliId,$name,$phone,$email,$source,$bmin,$bmax,$oldStatus,$stage,$follow,$notes,$lost,$uid);if(!$st->execute())throw new RuntimeException('Could not insert lead.');$leadId=(int)$st->insert_id;if($dalaliId){$rf=$conn->prepare("INSERT INTO dalali_referrals(dalali_id,lead_id,customer_id,car_id,status,notes) VALUES(?,?,?,?, 'OPEN','Imported lead referral')");$rf->bind_param('iiii',$dalaliId,$leadId,$customerId,$carId);if(!$rf->execute())throw new RuntimeException('Could not link imported dalali referral.');}return 'Lead #'.$leadId;
    }
    if($type==='documents'){
        $ch=imp_text($r,'chassis_number');$doc=imp_text($r,'document_type');if($ch===''||$doc==='')throw new RuntimeException('Chassis number and document type are required.');$car=imp_car($conn,$ch);if(!$car)throw new RuntimeException('Vehicle chassis not found.');$carId=(int)$car['id'];$num=imp_text($r,'document_number');$status=strtoupper(imp_text($r,'status','AVAILABLE'));if(!in_array($status,['AVAILABLE','PENDING','MISSING'],true))$status='AVAILABLE';$expiry=car_sales_normalize_date($r['expiry_date']??'');$notes=imp_text($r,'notes');$st=$conn->prepare('INSERT INTO car_documents(car_id,document_type,document_number,status,expiry_date,notes) VALUES(?,?,?,?,?,?)');$st->bind_param('isssss',$carId,$doc,$num,$status,$expiry,$notes);if(!$st->execute())throw new RuntimeException('Could not insert vehicle document.');return 'Document #'.$st->insert_id;
    }
    if($type==='costs'){
        $ch=imp_text($r,'chassis_number');$costType=imp_text($r,'cost_type');$amount=imp_num($r,'amount');$date=car_sales_normalize_date($r['cost_date']??'');if($ch===''||$costType===''||$amount<=0||!$date)throw new RuntimeException('Missing/invalid cost fields.');$car=imp_car($conn,$ch);if(!$car)throw new RuntimeException('Vehicle chassis not found.');$carId=(int)$car['id'];$desc=imp_text($r,'description');$st=$conn->prepare('INSERT INTO car_costs(car_id,cost_type,description,amount,cost_date) VALUES(?,?,?,?,?)');$st->bind_param('issds',$carId,$costType,$desc,$amount,$date);if(!$st->execute())throw new RuntimeException('Could not insert cost.');return 'Cost #'.$st->insert_id;
    }
    if($type==='sales'){
        $ch=imp_text($r,'chassis_number');
        $salePrice=imp_num($r,'sale_price');
        $date=car_sales_normalize_date($r['sale_date']??'');
        $buyer=imp_text($r,'buyer_name');
        $amountPaid=imp_num($r,'amount_paid',-1);
        if($ch===''||$salePrice<=0||!$date||$buyer===''||$amountPaid<0)throw new RuntimeException('Missing/invalid historical sale fields.');
        if($amountPaid>$salePrice+0.01)throw new RuntimeException('Amount paid cannot exceed sale price.');
        $car=imp_car($conn,$ch);if(!$car)throw new RuntimeException('Vehicle chassis not found. Import the car first.');
        if($car['status']==='SOLD')throw new RuntimeException('Vehicle is already sold.');
        $carId=(int)$car['id'];
        $check=$conn->prepare('SELECT id FROM car_sales WHERE car_id=? LIMIT 1');$check->bind_param('i',$carId);$check->execute();
        if($check->get_result()->fetch_assoc())throw new RuntimeException('Vehicle already has a sale.');
        $phone=imp_text($r,'buyer_phone');$email=imp_text($r,'buyer_email');
        $customerId=find_or_create_customer($conn,$buyer,$phone,$email,$uid);
        if($customerId<=0)throw new RuntimeException('Could not create or match buyer.');
        $dalaliId=null;$dp=imp_text($r,'dalali_phone');
        if($dp!==''){$d=imp_dalali($conn,$dp);if(!$d)throw new RuntimeException('Dalali phone not found. Import the dalali first.');$dalaliId=(int)$d['id'];}
        $payMethod=imp_text($r,'payment_method','Imported Record');$payRef=imp_text($r,'payment_reference');$notes=imp_text($r,'notes');
        $payStatus=$amountPaid>=$salePrice?'PAID':($amountPaid>0?'PARTIAL':'UNPAID');
        $conn->begin_transaction();
        try{
            $st=$conn->prepare('INSERT INTO car_sales(car_id,customer_id,dalali_id,sale_price,sale_date,payment_status,payment_method,buyer_name,buyer_phone,buyer_email,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
            if(!$st)throw new RuntimeException('Could not prepare historical sale import.');
            $st->bind_param('iiidsssssss',$carId,$customerId,$dalaliId,$salePrice,$date,$payStatus,$payMethod,$buyer,$phone,$email,$notes);
            if(!$st->execute() || $st->insert_id<=0)throw new RuntimeException('Could not insert historical sale.');
            $saleId=(int)$st->insert_id;
            if($amountPaid>0){
                $p=$conn->prepare("INSERT INTO sale_payments(sale_id,amount,payment_date,method,reference_number,notes,received_by) VALUES(?,?,?,?,?,'Imported historical payment',?)");
                if(!$p)throw new RuntimeException('Could not prepare historical payment import.');
                $p->bind_param('idsssi',$saleId,$amountPaid,$date,$payMethod,$payRef,$uid);
                if(!$p->execute())throw new RuntimeException('Could not insert historical payment.');
            }
            $u=$conn->prepare("UPDATE cars SET status='SOLD' WHERE id=?");
            if(!$u)throw new RuntimeException('Could not prepare vehicle status update.');
            $u->bind_param('i',$carId);if(!$u->execute())throw new RuntimeException('Could not mark imported vehicle as sold.');
            $cv=imp_num($r,'commission_value');
            if($dalaliId&&$cv>0){
                $ct=strtoupper(imp_text($r,'commission_type','FIXED'));if(!in_array($ct,['FIXED','PERCENT'],true))$ct='FIXED';
                $comm=$ct==='PERCENT'?$salePrice*$cv/100:$cv;$desc='Dalali commission: imported sale #'.$saleId;
                $cost=$conn->prepare("INSERT INTO car_costs(car_id,cost_type,description,amount,cost_date) VALUES(?,'Dalali Commission',?,?,?)");
                if(!$cost)throw new RuntimeException('Could not prepare imported dalali commission cost.');
                $cost->bind_param('isds',$carId,$desc,$comm,$date);if(!$cost->execute() || $cost->insert_id<=0)throw new RuntimeException('Could not insert imported dalali commission cost.');
                $costId=(int)$cost->insert_id;
                $dc=$conn->prepare('INSERT INTO dalali_commissions(dalali_id,sale_id,car_id,cost_id,commission_type,commission_value,amount,due_date,notes) VALUES(?,?,?,?,?,?,?,?,?)');
                if(!$dc)throw new RuntimeException('Could not prepare imported dalali commission.');
                $dc->bind_param('iiiisddss',$dalaliId,$saleId,$carId,$costId,$ct,$cv,$comm,$date,$notes);if(!$dc->execute())throw new RuntimeException('Could not insert imported dalali commission.');
            }
            $conn->commit();
            return 'Sale #'.$saleId;
        }catch(Throwable $e){
            $conn->rollback();
            throw $e;
        }
    }
    throw new RuntimeException('Unsupported import type.');
}
$error='';$result=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    $type=$_POST['import_type']??'';
    if(!isset($types[$type])){$error='Choose a valid import type.';}elseif(empty($_FILES['import_file']['name'])){$error='Choose an Excel .xlsx or CSV file.';}elseif(($_FILES['import_file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){$error='File upload failed.';}elseif((int)$_FILES['import_file']['size']>10*1024*1024){$error='Import file must be 10 MB or smaller.';}else{
        try{$rows=car_sales_read_tabular_file($_FILES['import_file']['tmp_name'],$_FILES['import_file']['name']);$records=car_sales_rows_to_records($rows);if(!$records)throw new RuntimeException('No data rows were found. Make sure the first row contains the template headers.');$imported=0;$skipped=0;$errors=[];$uid=(int)$_SESSION['user_id'];foreach($records as $i=>$record){try{if($type!=='sales'){$conn->begin_transaction();}$label=imp_insert_record($conn,$type,$record,$uid);if($type!=='sales'){$conn->commit();}$imported++;}catch(Throwable $e){if($type!=='sales'){$conn->rollback();}$skipped++;$errors[]='Row '.($i+2).': '.$e->getMessage();}}$summary=$errors?json_encode(array_slice($errors,0,100),JSON_UNESCAPED_UNICODE):null;$st=$conn->prepare('INSERT INTO import_batches(import_type,original_filename,rows_total,rows_imported,rows_skipped,error_summary,created_by) VALUES(?,?,?,?,?,?,?)');$total=count($records);$filename=basename($_FILES['import_file']['name']);$st->bind_param('ssiiisi',$type,$filename,$total,$imported,$skipped,$summary,$uid);$st->execute();$batchId=(int)$st->insert_id;car_sales_audit($conn,'IMPORT','import_batch',$batchId,'Imported '.$imported.' '.$types[$type]['label'].' row(s) from '.$filename.'; skipped '.$skipped.'.',null,['type'=>$type,'rows_total'=>$total,'rows_imported'=>$imported,'rows_skipped'=>$skipped]);$result=['total'=>$total,'imported'=>$imported,'skipped'=>$skipped,'errors'=>$errors,'batch_id'=>$batchId];}catch(Throwable $e){$error=$e->getMessage();}
    }
}
$history=$conn->query('SELECT i.*,u.name user_name FROM import_batches i LEFT JOIN users u ON u.id=i.created_by ORDER BY i.created_at DESC LIMIT 25');
$page_title='Import from Excel';include __DIR__.'/../includes/header.php';include __DIR__.'/../includes/leftsidebar.php';?>
<div class="content-page"><div class="report-title"><div><h1 class="page-title">Import from Excel</h1><p class="muted">Migrate existing showroom records into the live system. Imported rows become normal searchable/editable system records.</p></div></div>
<?php if($error):?><div class="flash-message error" style="position:static;margin-bottom:16px"><?=e($error)?></div><?php endif;?><?php if($result):?><div class="card" style="background:#ecfdf5"><h2>Import Complete</h2><p><strong>Total rows:</strong> <?=$result['total']?> &nbsp; <strong>Imported:</strong> <?=$result['imported']?> &nbsp; <strong>Skipped:</strong> <?=$result['skipped']?></p><?php if($result['errors']):?><details><summary>Show skipped-row reasons</summary><ul><?php foreach(array_slice($result['errors'],0,30) as $er):?><li><?=e($er)?></li><?php endforeach;?></ul></details><?php endif;?></div><?php endif;?>
<div class="grid" style="grid-template-columns:minmax(300px,1fr) minmax(320px,1fr)"><div class="card form-card"><h2>Upload Spreadsheet</h2><form method="post" enctype="multipart/form-data"><div class="form-group"><label>Record Type *</label><select name="import_type" id="import_type" required><?php foreach($types as $key=>$t):?><option value="<?=e($key)?>" <?=($_POST['import_type']??'cars')===$key?'selected':''?>><?=e($t['label'])?></option><?php endforeach;?></select></div><div class="form-group"><label>Excel / CSV File *</label><input type="file" name="import_file" accept=".xlsx,.csv" required><small class="muted">Supported: .xlsx and .csv. For old .xls files, open in Excel and Save As .xlsx.</small></div><button class="btn btn-green">Import Records</button></form></div>
<div class="card"><h2>Excel Templates</h2><p class="muted">Enter records on the first sheet named “Import Data”. The separate Example sheet is for reference only and is not imported. Do not rename the header row.</p><?php foreach($types as $key=>$t):?><div style="padding:10px 0;border-bottom:1px solid #e5e7eb"><strong><?=e($t['label'])?></strong><br><small>Required: <?=e($t['required'])?></small><br><a href="templates/<?=e($t['template'])?>" download>Download Excel template</a></div><?php endforeach;?><p class="muted" style="margin-top:12px"><strong>Import order for an existing business:</strong> Cars → Customers/Dalali → Costs/Documents/Leads → Historical Sales. Chassis number is used to link vehicle-related rows, so plate number can remain blank for unregistered imports.</p></div></div>
<div class="card"><h2>Recent Imports</h2><div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Type</th><th>File</th><th>Total</th><th>Imported</th><th>Skipped</th><th>User</th></tr></thead><tbody><?php while($h=$history->fetch_assoc()):?><tr><td><?=e($h['created_at'])?></td><td><?=e($h['import_type'])?></td><td><?=e($h['original_filename'])?></td><td><?=e($h['rows_total'])?></td><td><?=e($h['rows_imported'])?></td><td><?=e($h['rows_skipped'])?></td><td><?=e($h['user_name']?:'System')?></td></tr><?php endwhile;?></tbody></table></div></div></div>
<?php include __DIR__.'/../includes/footer.php';?>
