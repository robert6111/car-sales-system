<?php
require_once __DIR__ . '/../includes/functions.php';
set_flash('error', 'Public account registration is disabled. Only an admin can add users.');
redirect_to('backend/authentication/login.php');
?>
