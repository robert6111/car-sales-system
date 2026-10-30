<?php
function sendEmail(string $to, string $subject, string $htmlBody) {
    // Local XAMPP customer version: email sending is intentionally not forced.
    // Configure real SMTP later if hosted online. Returning false shows reset link on screen.
    return false;
}
?>
