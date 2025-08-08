<?php
session_start();
require_once 'dataconnection.php';
?>

<?php include 'cust_header.php'; ?>

<main style="max-width:1200px; margin:20px auto; padding:0 20px; min-height:60vh;">
    <h1 style="color:#007bff; text-align:center;">Welcome to CubePro Hub</h1>
    <?php if (isset($_SESSION['Cust_ID'])): ?>
        <p style="text-align:center;">This is your home page.</p>
        <p style="text-align:center;">This is your home page.</p>
    <?php else: ?>
        <p style="text-align:center;">Please log in to access your account.</p>
        <p style="text-align:center;"><a href="cust_login.php" style="color:#007bff; text-decoration:none;">Login</a></p>
    <?php endif; ?>
</main>

<?php include 'cust_footer.php'; ?>