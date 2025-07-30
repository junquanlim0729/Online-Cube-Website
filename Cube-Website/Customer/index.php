<?php
session_start();
require_once 'dataconnection.php'; // Local to Customer folder

// Debug: Check session status
if (isset($_SESSION['Cust_ID'])) {
    error_log("Session set with Cust_ID: " . $_SESSION['Cust_ID'] . " at " . date('Y-m-d H:i:s')); // Log timestamped debug
} else {
    error_log("Session not set at " . date('Y-m-d H:i:s')); // Log timestamped debug
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Home - CubePro Hub</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #007bff; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h1>Welcome to CubePro Hub</h1>
    <?php if (isset($_SESSION['Cust_ID'])): ?>
        <p>This is your home page.</p>
        <p><a href="cust_logout.php">Logout</a></p>
    <?php else: ?>
        <p>Please log in to access your account.</p>
        <p><a href="cust_login.php">Login</a></p>
    <?php endif; ?>
</body>
</html>