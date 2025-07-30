<?php
session_start(); // Only called here
require_once 'dataconnection.php'; // Use local dataconnection.php in Admin folder

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

$page = isset($_GET['page']) ? $_GET['page'] : 'admin_dashboard.php';
$valid_pages = ['admin_dashboard.php', 'admin_manage_staff.php', 'admin_cust_page.php', 'admin_cate_page.php', 'admin_prod_page.php', 'admin_color_page.php', 'admin_report_page.php', 'admin_edit_staff.php', 'admin_add_staff.php'];
$page = in_array($page, $valid_pages) ? $page : 'admin_dashboard.php';
?>

<?php include 'admin_header.php'; ?>

<div style="display: flex; min-height: 100vh;">
    <!-- Left Sidebar -->
    <div style="width: 200px; background-color: #f4f4f4; padding: 10px; min-height: 100vh;">
        <?php include 'admin_menu.php'; ?>
    </div>

    <!-- Main Content -->
    <div style="flex-grow: 1; padding: 20px;">
        <?php include $page; ?>
    </div>
</div>

<?php include 'admin_footer.php'; ?>