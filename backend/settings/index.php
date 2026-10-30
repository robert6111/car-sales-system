<?php
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/business_helpers.php';
require_login();
if(!is_admin()){http_response_code(403);exit('Admin access required.');}
$error='';
$settings=car_sales_company_settings($conn);
if($_SERVER['REQUEST_METHOD']==='POST'){
    $name=trim($_POST['company_name']??'');
    $tagline=trim($_POST['tagline']??'');
    $phone=trim($_POST['phone']??'');
    $email=trim($_POST['email']??'');
    $address=trim($_POST['address']??'');
    $tin=trim($_POST['tin_number']??'');
    $reg=trim($_POST['registration_number']??'');
    $currency=strtoupper(trim($_POST['currency']??'TZS'));
    $terms=trim($_POST['sale_terms']??'');
    $logoPath=$settings['logo_path']??'backend/includes/images/logo.png';
    if($name===''){$error='Company / showroom name is required.';}
    if(!$error && !empty($_FILES['logo']['name'])){
        if(($_FILES['logo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){$error='Logo upload failed.';}
        else{
            $tmp=$_FILES['logo']['tmp_name'];
            $size=(int)$_FILES['logo']['size'];
            $mime='';if(class_exists('finfo')){$finfo=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($tmp);}elseif(function_exists('mime_content_type')){$mime=(string)mime_content_type($tmp);}else{$mime=(string)($_FILES['logo']['type']??'');}
            $ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime]??null;
            if(!$ext){$error='Logo must be PNG, JPG or WEBP.';}
            elseif($size>2*1024*1024){$error='Logo must be 2 MB or smaller.';}
            else{
                $filename='company_logo_'.time().'.'.$ext;
                $dest=__DIR__.'/../uploads/company/'.$filename;
                if(!move_uploaded_file($tmp,$dest)){$error='Could not save uploaded logo.';}
                else{$logoPath='backend/uploads/company/'.$filename;}
            }
        }
    }
    if(!$error){
        $before=$settings;
        $uid=(int)$_SESSION['user_id'];
        $st=$conn->prepare('UPDATE company_settings SET company_name=?,tagline=?,phone=?,email=?,address=?,tin_number=?,registration_number=?,currency=?,logo_path=?,sale_terms=?,updated_by=? WHERE id=1');
        $st->bind_param('ssssssssssi',$name,$tagline,$phone,$email,$address,$tin,$reg,$currency,$logoPath,$terms,$uid);
        if($st->execute()){
            car_sales_audit($conn,'UPDATE','company_settings','1','Updated company/showroom settings.',$before,['company_name'=>$name,'tagline'=>$tagline,'phone'=>$phone,'email'=>$email,'address'=>$address,'tin_number'=>$tin,'registration_number'=>$reg,'currency'=>$currency,'logo_path'=>$logoPath,'sale_terms'=>$terms]);
            set_flash('success','Company settings updated. Receipts and agreements will use the new details.');
            redirect_to('backend/settings/index.php');
        }else{$error='Could not save settings.';}
    }
}
$r=$conn->query('SELECT * FROM company_settings WHERE id=1');$settings=$r?$r->fetch_assoc():$settings;
$page_title='Company Settings';include __DIR__.'/../includes/header.php';include __DIR__.'/../includes/leftsidebar.php';
?>
<div class="content-page"><div class="report-title"><div><h1 class="page-title">Company Settings & Branding</h1><p class="muted">These details appear on receipts, sale agreements and the system header.</p></div></div>
<div class="card form-card"><?php if($error):?><div class="flash-message error" style="position:static;margin-bottom:16px"><?=e($error)?></div><?php endif;?>
<form method="post" enctype="multipart/form-data">
<h3>Business Identity</h3><div class="form-row"><div class="form-group"><label>Company / Showroom Name *</label><input name="company_name" value="<?=e($settings['company_name']??'')?>" required></div><div class="form-group"><label>Tagline</label><input name="tagline" value="<?=e($settings['tagline']??'')?>"></div></div>
<div class="form-row"><div class="form-group"><label>Phone</label><input name="phone" value="<?=e($settings['phone']??'')?>"></div><div class="form-group"><label>Email</label><input type="email" name="email" value="<?=e($settings['email']??'')?>"></div></div>
<div class="form-group"><label>Address</label><input name="address" value="<?=e($settings['address']??'')?>"></div>
<div class="form-row"><div class="form-group"><label>TIN Number</label><input name="tin_number" value="<?=e($settings['tin_number']??'')?>"></div><div class="form-group"><label>Business Registration Number</label><input name="registration_number" value="<?=e($settings['registration_number']??'')?>"></div></div>
<div class="form-row"><div class="form-group"><label>Currency</label><input name="currency" maxlength="12" value="<?=e($settings['currency']??'TZS')?>" placeholder="TZS"></div><div class="form-group"><label>Logo (PNG/JPG/WEBP, max 2 MB)</label><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></div></div>
<?php if(!empty($settings['logo_path'])):?><div class="form-group"><label>Current Logo</label><div><img src="<?=e(app_url($settings['logo_path']))?>" alt="Company logo" style="max-height:80px;max-width:220px;object-fit:contain"></div></div><?php endif;?>
<h3>Sale Agreement</h3><div class="form-group"><label>Standard Terms</label><textarea name="sale_terms" rows="7"><?=e($settings['sale_terms']??'')?></textarea><small class="muted">Keep this to the showroom's agreed commercial terms. Have your final legal wording reviewed for your business.</small></div>
<button class="btn btn-green" type="submit">Save Settings</button></form></div></div>
<?php include __DIR__.'/../includes/footer.php';?>
