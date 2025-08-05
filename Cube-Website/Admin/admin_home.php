<?php
session_start();
require_once 'dataconnection.php';

// Check and set role if not set
if (isset($_SESSION['Staff_ID']) && !isset($_SESSION['role'])) {
    $staff_id = $_SESSION['Staff_ID'];
    $sql = "SELECT Staff_Role FROM Staff WHERE Staff_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $staff_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $_SESSION['role'] = $row['Staff_Role'];
        }
        mysqli_stmt_close($stmt);
    }
}

// Detect AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$is_ajax && (!isset($_SESSION['Staff_ID']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'))) {
    header("Location: admin_login.php");
    exit();
}

$page = isset($_GET['page']) ? $_GET['page'] : 'admin_dashboard.php';
$valid_pages = ['admin_dashboard.php', 'admin_manage_staff.php', 'admin_cust_page.php', 'admin_cate_page.php', 'admin_prod_page.php', 'admin_color_page.php', 'admin_report_page.php', 'admin_edit_staff.php', 'admin_add_staff.php', 'admin_profile.php'];
$page = in_array($page, $valid_pages) ? $page : 'admin_dashboard.php';

// Only include the page for non-AJAX requests
if (!$is_ajax) {
    include 'admin_header.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin Home</title>
        <style>
            html, body {
                margin: 0;
                padding: 0;
                height: 100%;
                overflow: hidden;
            }
            .admin-home-container {
                display: flex;
                min-height: 100vh;
                width: 100vw;
                position: relative;
            }
            .admin-sidebar {
                width: 250px;
                background-color: #f4f4f4;
                padding: 10px;
                position: absolute;
                top: 60px;
                bottom: 40px;
                left: 0;
                z-index: 1;
                box-sizing: border-box;
                overflow-y: auto;
            }
            .admin-content {
                flex-grow: 1;
                margin-left: 250px;
                padding: 20px;
                box-sizing: border-box;
                height: calc(100vh - 60px - 40px);
                margin-top: 60px;
                overflow-y: hidden;
            }
            .admin-content * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
        </style>
    </head>
    <body>
        <div class="admin-home-container">
            <div class="admin-sidebar">
                <?php include 'admin_menu.php'; ?>
            </div>
            <div class="admin-content">
                <?php include $page; ?>
            </div>
        </div>
        <?php include 'admin_footer.php'; ?>
    </body>
    </html>
    <?php
}
mysqli_close($conn);
?>
