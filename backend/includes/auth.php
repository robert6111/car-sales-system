<?php
require_once __DIR__ . '/functions.php';

function require_login(): void {
    car_sales_require_login();
}

function require_admin(): void {
    car_sales_require_permission('users_manage');
}
?>
