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

// Log request details
error_log("Request for " . $_SERVER['REQUEST_URI'] . " - AJAX: " . ($is_ajax ? 'Yes' : 'No') . ", Method: " . $_SERVER['REQUEST_METHOD'] . ", Session Staff_ID: " . (isset($_SESSION['Staff_ID']) ? $_SESSION['Staff_ID'] : 'Not set') . ", Role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'Not set'));

// Only redirect for non-AJAX requests if not authorized
if (!$is_ajax && $_SERVER['REQUEST_METHOD'] === 'GET' && (!isset($_SESSION['Staff_ID']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'))) {
    header("Location: admin_login.php");
    exit();
}

$page = isset($_GET['page']) ? $_GET['page'] : 'admin_dashboard.php';
$valid_pages = ['admin_dashboard.php', 'admin_manage_staff.php', 'admin_manage_customer.php', 'admin_manage_category.php', 'admin_manage_product.php', 'admin_manage_color.php', 'admin_manage_report.php', 'admin_profile.php', 'admin_logout.php'];
$page = in_array($page, $valid_pages) ? $page : 'admin_dashboard.php';

// Define flag to indicate inclusion
define('INCLUDED_FROM_ADMIN_HOME', true);

// Handle AJAX POST requests
if ($is_ajax && $_SERVER["REQUEST_METHOD"] == "POST") {
    error_log("Entering AJAX POST handler for page: " . $page);
    error_log("POST data received: " . print_r($_POST, true));
    
    if (in_array($page, $valid_pages)) {
        // Include the page file which will handle the AJAX request
        include $page;
    } else {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid page request']);
        ob_end_flush();
        exit();
    }
}

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
                margin-top: 20px;
            }
            .admin-content {
                flex-grow: 1;
                margin-left: 250px;
                padding: 20px;
                box-sizing: border-box;
                height: calc(100vh - 60px - 40px);
                margin-top: 60px;
                overflow-y: auto;
            }
            .admin-content * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            .admin-sidebar a {
                display: block;
                padding: 10px;
                color: #333;
                text-decoration: none;
            }
            .admin-sidebar a.active {
                background-color: #ddd;
            }
        </style>
    </head>
    <body>
        <div class="admin-home-container">
            <div class="admin-sidebar">
                <?php
                $menu_items = [
                    'admin_dashboard.php' => 'Dashboard',
                    'admin_profile.php' => 'My Profile',
                    'admin_manage_staff.php' => 'Manage Staffs',
                    'admin_manage_customer.php' => 'Manage Customers',
                    'admin_manage_category.php' => 'Manage Categories',
                    'admin_manage_product.php' => 'Manage Products',
                    'admin_manage_color.php' => 'Manage Colors',
                    'admin_manage_report.php' => 'Generate Report',
                    'admin_logout.php' => 'Logout'
                ];
                foreach ($menu_items as $link => $title) {
                    $active = ($page === $link) ? 'active' : '';
                    echo "<a href='?page=$link' class='$active'>$title</a>";
                }
                ?>
            </div>
            <div class="admin-content">
                <?php
                ob_start();
                include $page;
                $content = ob_get_clean();
                echo $content;
                ?>
            </div>
        </div>
        <?php include 'admin_footer.php'; ?>
    </body>
    </html>
    <?php
} else if ($is_ajax && $_SERVER["REQUEST_METHOD"] != "POST") {
    // Non-POST AJAX requests (e.g., GET) should not reach here; handle gracefully
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    ob_end_flush();
    exit();
}

// Close the connection only if it exists and is a valid object
// Don't close connection here as it might be used by included files
// if (isset($conn) && is_object($conn)) {
//     mysqli_close($conn);
// }
?>