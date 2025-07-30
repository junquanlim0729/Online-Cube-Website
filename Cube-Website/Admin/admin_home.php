<?php
session_start();
require_once 'dataconnection.php'; // Use local dataconnection.php in Admin folder

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}
?>

<?php include 'admin_header.php'; ?>

<div style="display: flex;">
    <!-- Left Sidebar -->
    <div style="width: 200px; background-color: #f4f4f4; padding: 10px; min-height: 100vh;">
        <?php include 'admin_menu.php'; ?>
    </div>

    <!-- Main Content -->
    <div style="flex-grow: 1; padding: 20px;">
        <h2>Welcome, Admin!</h2>
        <p>This is your admin dashboard. Add content or functionality here.</p>
        <?php
        if (isset($_GET['login']) && $_GET['login'] === 'success') {
            echo '<p style="color: green;">Login successful!</p>';
        }
        ?>
    </div>
</div>

<?php include 'admin_footer.php'; ?>