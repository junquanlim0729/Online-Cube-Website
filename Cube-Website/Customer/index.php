<?php
session_start();
require_once 'dataconnection.php'; 

// Debug: Check session status
if (!isset($_SESSION['Cust_ID'])) {
    error_log("Session not set, redirecting to login at " . date('Y-m-d H:i:s')); // Log timestamped debug
    header("Location: ../Customer/cust_login.php"); 
    exit();
} else {
    error_log("Session set with Cust_ID: " . $_SESSION['Cust_ID'] . " at " . date('Y-m-d H:i:s')); // Log timestamped debug
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
    <h1>Welcome, Customer!</h1>
    <p>This is your home page.</p>
    <?php if (isset($_SESSION['Cust_ID'])): ?>
        <p><a href="../Customer/cust_logout.php">Logout</a></p>
    <?php else: ?>
        <p><a href="../Customer/cust_login.php">Login</a></p>
    <?php endif; ?>
</body>
</html>