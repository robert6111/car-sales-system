<?php
function find_or_create_customer(mysqli $conn, string $name, string $phone = '', string $email = '', ?int $createdBy = null): int {
    if ($phone !== '') {
        $q=$conn->prepare('SELECT id FROM customers WHERE phone=? LIMIT 1');
        $q->bind_param('s',$phone);$q->execute();$r=$q->get_result()->fetch_assoc();
        if ($r) return (int)$r['id'];
    }
    if ($email !== '') {
        $q=$conn->prepare('SELECT id FROM customers WHERE email=? LIMIT 1');
        $q->bind_param('s',$email);$q->execute();$r=$q->get_result()->fetch_assoc();
        if ($r) return (int)$r['id'];
    }
    $q=$conn->prepare('INSERT INTO customers (name,phone,email,created_by) VALUES (?,?,?,?)');
    $q->bind_param('sssi',$name,$phone,$email,$createdBy);$q->execute();
    return (int)$q->insert_id;
}
function sale_paid_total(mysqli $conn, int $saleId): float {
    $q=$conn->prepare('SELECT IFNULL(SUM(amount),0) total FROM sale_payments WHERE sale_id=?');$q->bind_param('i',$saleId);$q->execute();
    return (float)($q->get_result()->fetch_assoc()['total']??0);
}
function sync_sale_payment_status(mysqli $conn, int $saleId): void {
    $q=$conn->prepare('SELECT sale_price FROM car_sales WHERE id=?');$q->bind_param('i',$saleId);$q->execute();$s=$q->get_result()->fetch_assoc();if(!$s)return;
    $paid=sale_paid_total($conn,$saleId);$status=$paid >= (float)$s['sale_price'] ? 'PAID' : ($paid>0?'PARTIAL':'UNPAID');
    $q=$conn->prepare('UPDATE car_sales SET payment_status=? WHERE id=?');$q->bind_param('si',$status,$saleId);$q->execute();
}
function car_sales_company_settings(mysqli $conn): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $defaults = [
        'company_name'=>'Car Sales System','tagline'=>'','phone'=>'','email'=>'','address'=>'',
        'tin_number'=>'','registration_number'=>'','currency'=>'TZS',
        'logo_path'=>'backend/includes/images/logo.png','sale_terms'=>''
    ];
    $r=$conn->query('SELECT * FROM company_settings WHERE id=1 LIMIT 1');
    $cache=$r && ($row=$r->fetch_assoc()) ? array_merge($defaults,$row) : $defaults;
    return $cache;
}
function car_sales_reset_company_settings_cache(): void {
    // Kept as a semantic hook; each request reloads PHP state anyway.
}
function car_sales_plate_label($plate): string {
    $plate=trim((string)$plate);
    return $plate !== '' ? $plate : 'Not registered yet';
}
function car_sales_vehicle_label(array $car): string {
    return trim((string)($car['car_name']??'Vehicle')) . ' / ' . car_sales_plate_label($car['plate_number']??'');
}
function car_sales_audit(mysqli $conn, string $action, string $entityType, $entityId, string $description, $before=null, $after=null): void {
    $uid = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $entityId = $entityId === null ? null : (string)$entityId;
    $beforeJson = $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $afterJson = $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $ip = substr((string)($_SERVER['REMOTE_ADDR']??''),0,64);
    $st=$conn->prepare('INSERT INTO audit_logs(user_id,action,entity_type,entity_id,description,before_data,after_data,ip_address) VALUES(?,?,?,?,?,?,?,?)');
    if(!$st) return;
    $st->bind_param('isssssss',$uid,$action,$entityType,$entityId,$description,$beforeJson,$afterJson,$ip);
    $st->execute();
}
function car_sales_currency_money(mysqli $conn, $value): string {
    $settings=car_sales_company_settings($conn);
    return e($settings['currency'] ?: 'TZS').' '.money($value);
}
function car_sales_normalize_date($value): ?string {
    $value=trim((string)$value);
    if($value==='') return null;
    // Excel serial date support.
    if(is_numeric($value) && (float)$value > 20000 && (float)$value < 80000){
        $ts=(int)round(((float)$value - 25569) * 86400);
        return gmdate('Y-m-d',$ts);
    }
    $ts=strtotime($value);
    return $ts ? date('Y-m-d',$ts) : null;
}
?>
