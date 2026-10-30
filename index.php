<?php
require_once __DIR__ . '/backend/includes/functions.php';
if (isset($_SESSION['user_id'])) {
    header('Location: backend/dashboard/index.php');
    exit;
}
header('Location: backend/authentication/login.php');
exit;
?>
