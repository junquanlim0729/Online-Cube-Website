<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['Cust_ID'])) {
    header('Location: cust_login.php');
    exit();
}
?>
<div>
    <h2 style="margin-top:0;">Address</h2>
    <!-- Address content placeholder -->
</div>