<?php
require_once __DIR__ . '/../../includes/db.php';require_once __DIR__ . '/../includes/auth.php';require_admin();
$id=(int)($_GET['id']??0);
car_sales_validate_action_token('toggle_user', (string)$id);
if($id && $id!=(int)$_SESSION['user_id']){$stmt=$conn->prepare('UPDATE users SET status=IF(status=1,0,1) WHERE id=?');$stmt->bind_param('i',$id);$stmt->execute();set_flash('success','User status updated.');}redirect_to('backend/users/manage.php');
?>
