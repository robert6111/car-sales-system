<?php
// Customer-ready database connection for XAMPP
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Keep mysqli quiet so a database/query issue does not become a blank HTTP 500 page.
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$host = "localhost";
$user = "root";
$pass = "";
$db   = "car_sales_system";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed. Please create/import the database named car_sales_system using backend/database/database.sql.");
}

$conn->set_charset("utf8mb4");

// Non-destructive extensions for customers, dalali, reservations, payments and showroom workflow.
require_once __DIR__ . "/../backend/includes/business_schema.php";
?>
