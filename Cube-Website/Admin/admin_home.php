<?php
session_start(); // Only called here
require_once 'dataconnection.php'; // Use local dataconnection.php in Admin folder

if (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit();
}

$page = isset($_GET['page']) ? $_GET['page'] : 'admin_dashboard.php';
$valid_pages = ['admin_dashboard.php', 'admin_manage_staff.php', 'admin_cust_page.php', 'admin_cate_page.php', 'admin_prod_page.php', 'admin_color_page.php', 'admin_report_page.php', 'admin_edit_staff.php', 'admin_add_staff.php', 'admin_profile.php'];
$page = in_array($page, $valid_pages) ? $page : 'admin_dashboard.php';
?>

<?php include 'admin_header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Home</title>
    <style>
        /* Scoped styles for admin_home.php layout */
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden; /* Prevent body scroll */
        }
        .admin-home-container {
            display: flex;
            min-height: 100vh; /* Allow expansion with content */
            width: 100vw;
            position: relative;
        }
        .admin-sidebar {
            width: 250px;
            background-color: #f4f4f4;
            padding: 10px;
            position: absolute;
            top: 60px; /* Below header */
            bottom: 40px; /* Above footer */
            left: 0;
            z-index: 1;
            box-sizing: border-box;
            overflow-y: auto; /* Sidebar scrolling if needed */
        }
        .admin-content {
            flex-grow: 1;
            margin-left: 250px; /* Right of sidebar */
            padding: 20px;
            box-sizing: border-box;
            height: calc(100vh - 60px - 40px); /* Exact height from header to footer */
            margin-top: 60px; /* Start below header */
            overflow-y: hidden; /* Disable content area scrolling, handled by pages */
        }
        /* Ensure included pages don't inherit unwanted styles */
        .admin-content * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="admin-home-container">
        <!-- Left Sidebar -->
        <div class="admin-sidebar">
            <?php include 'admin_menu.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="admin-content">
            <?php include $page; ?>
        </div>
    </div>

    <?php include 'admin_footer.php'; ?>
</body>
</html>