<?php
session_start();
require_once 'dataconnection.php';

// Always refresh role from database for accuracy
if (isset($_SESSION['Staff_ID'])) {
    $staff_id = $_SESSION['Staff_ID'];
    $sql = "SELECT Staff_Role FROM Staff WHERE Staff_ID = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $staff_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            // Normalize role value to avoid spacing/case issues
            $rawRole = trim((string)$row['Staff_Role']);
            $roleLower = strtolower($rawRole);
            if (strpos($roleLower, 'super') !== false && strpos($roleLower, 'admin') !== false) {
                $_SESSION['role'] = 'Super Admin';
            } else {
                $_SESSION['role'] = 'Admin';
            }
        }
        mysqli_stmt_close($stmt);
    }
}

// Detect AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Log request details
error_log("Request for " . $_SERVER['REQUEST_URI'] . " - AJAX: " . ($is_ajax ? 'Yes' : 'No') . ", Method: " . $_SERVER['REQUEST_METHOD'] . ", Session Staff_ID: " . (isset($_SESSION['Staff_ID']) ? $_SESSION['Staff_ID'] : 'Not set') . ", Role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'Not set'));

// Only redirect for non-AJAX requests if not authorized
// Allow both Admin and Super Admin roles
if (!$is_ajax && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $hasStaffId = isset($_SESSION['Staff_ID']);
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
    // Accept common variants and our normalized values
    $roleNorm = is_string($role) ? strtolower(trim($role)) : '';
    $isAllowedRole = in_array($roleNorm, ['admin','super admin','superadmin'], true);
    if (!$hasStaffId || !$isAllowedRole) {
        header("Location: admin_login.php");
        exit();
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'admin_dashboard.php';
$valid_pages = ['admin_dashboard.php', 'admin_manage_staff.php', 'admin_manage_customer.php', 'admin_manage_category.php', 'admin_manage_product.php', 'admin_manage_color.php', 'admin_manage_report.php', 'admin_manage_order.php', 'admin_profile.php', 'admin_logout.php'];
$page = in_array($page, $valid_pages) ? $page : 'admin_dashboard.php';
// Enforce authorization for staff management page
if (isset($_SESSION['role']) && $_SESSION['role'] !== 'Super Admin' && $page === 'admin_manage_staff.php') {
    $page = 'admin_dashboard.php';
}

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
            /* Sidebar menu structure */
            .admin-sidebar ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; height: 100%; }
            .admin-sidebar li { position: relative; }
            .admin-sidebar li a { display: block; padding: 10px; color: #333; text-decoration: none; transition: background-color 0.3s; }
            .admin-sidebar li a:hover { background-color: #ddd; }
            .submenu { display: none; padding-left: 0; }
            /* Arrow points up (closed) by default */
            .menu-dropdown-toggle::after { content: ' ▴'; }
            /* Arrow points down when opened */
            .menu-dropdown-toggle.open::after { content: ' ▾'; }
            /* Logout pinned above footer, style in red */
            /* Keep logout visibly above the footer */
            .admin-sidebar li.logout { position: absolute; bottom: 25px; left: 10px; right: 10px; margin: 0; }
            .admin-sidebar li.logout a { color: #dc3545; font-weight: 600; }
            .admin-sidebar li.logout a img { filter: invert(24%) sepia(83%) saturate(5346%) hue-rotate(346deg) brightness(94%) contrast(101%); }
            .admin-sidebar li.logout { margin-top: auto; }
            .logout-modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1000;
            }
            .logout-modal-content {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background-color: white;
                padding: 20px;
                border-radius: 5px;
                width: 400px;
                text-align: center;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            }
            .logout-modal-content button {
                padding: 10px 20px;
                margin: 5px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
            }
            .logout-modal-content .confirm-yes {
                background-color: #28a745;
                color: white;
            }
            .logout-modal-content .confirm-no {
                background-color: #dc3545;
                color: white;
            }
        </style>
    </head>
    <body>
        <div class="admin-home-container">
            <div class="admin-sidebar">
                <?php $isProductsSection = in_array($page, ['admin_manage_category.php','admin_manage_product.php','admin_manage_color.php']); ?>
                <ul>
                    <li><a href="?page=admin_dashboard.php" class="<?php echo $page==='admin_dashboard.php'?'active':''; ?>" style="margin-top: 10px;">Dashboard</a></li>
                    <li><a href="?page=admin_profile.php" class="<?php echo $page==='admin_profile.php'?'active':''; ?>">My Profile</a></li>
                    <?php if(isset($_SESSION['role']) && $_SESSION['role']==='Super Admin'): ?>
                    <?php if(isset($_SESSION['role']) && $_SESSION['role']==='Super Admin'): ?>
                    <li><a href="?page=admin_manage_staff.php" class="<?php echo $page==='admin_manage_staff.php'?'active':''; ?>">Manage Staffs</a></li>
                    <?php endif; ?>
                    <?php endif; ?>
                    <li><a href="?page=admin_manage_customer.php" class="<?php echo $page==='admin_manage_customer.php'?'active':''; ?>">Manage Customers</a></li>
                    <li class="menu-group">
                        <a href="#" class="menu-dropdown-toggle <?php echo $isProductsSection ? 'open' : ''; ?>">Manage Products</a>
                        <ul class="submenu" style="<?php echo $isProductsSection ? 'display:block' : 'display:none'; ?>;">
                            <li><a href="?page=admin_manage_category.php" class="<?php echo $page==='admin_manage_category.php'?'active':''; ?>" style="padding-left: 20px;">Categories</a></li>
                            <li><a href="?page=admin_manage_product.php" class="<?php echo $page==='admin_manage_product.php'?'active':''; ?>" style="padding-left: 20px;">Products</a></li>
                            <li><a href="?page=admin_manage_color.php" class="<?php echo $page==='admin_manage_color.php'?'active':''; ?>" style="padding-left: 20px;">Colors</a></li>
                        </ul>
                    </li>
                    <li><a href="?page=admin_manage_order.php" class="<?php echo $page==='admin_manage_order.php'?'active':''; ?>">Manage Orders</a></li>
                    <li><a href="?page=admin_manage_report.php" class="<?php echo $page==='admin_manage_report.php'?'active':''; ?>">Generate Report</a></li>
                    <li class="logout"><a href="#" onclick="showLogoutConfirmation(event)"><img src="https://cdn-icons-png.flaticon.com/512/660/660350.png" alt="Logout" style="width:16px;height:16px;margin-right:6px;vertical-align:middle;">Logout</a></li>
                </ul>
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
        <div id="logout-modal" class="logout-modal">
            <div class="logout-modal-content">
                <h3 style="margin-top:0; margin-bottom: 10px; font-size: 20px; color: #333;">Confirm Logout</h3>
                <p>Are you sure you want to logout?</p>
                <button class="confirm-yes" id="confirm-logout-yes">Yes</button>
                <button class="confirm-no" id="confirm-logout-no">No</button>
            </div>
        </div>
        <?php include 'admin_footer.php'; ?>
        <script>
            function showLogoutConfirmation(event) {
                event.preventDefault();
                document.getElementById('logout-modal').style.display = 'block';
            }

            document.getElementById('confirm-logout-yes').addEventListener('click', function() {
                window.location.href = 'admin_logout.php';
            });

            document.getElementById('confirm-logout-no').addEventListener('click', function() {
                document.getElementById('logout-modal').style.display = 'none';
            });
            // Toggle submenu on click
            document.querySelectorAll('.menu-dropdown-toggle').forEach(function(toggle){
                toggle.addEventListener('click', function(e){
                    e.preventDefault();
                    const submenu = this.parentElement.querySelector('.submenu');
                    if (submenu) {
                        const isOpen = submenu.style.display === 'block';
                        // Close all other product submenus
                        document.querySelectorAll('.menu-group .submenu').forEach(function(sm){
                            if (sm !== submenu) { sm.style.display = 'none'; }
                        });
                        document.querySelectorAll('.menu-dropdown-toggle').forEach(function(tg){
                            if (tg !== e.currentTarget) { tg.classList.remove('open'); }
                        });
                        submenu.style.display = isOpen ? 'none' : 'block';
                        this.classList.toggle('open', !isOpen);
                    }
                });
            });
        </script>
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