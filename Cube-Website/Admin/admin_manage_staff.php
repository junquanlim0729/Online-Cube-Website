<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once 'dataconnection.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Disable output buffering and set error handling
ob_start();
ini_set('display_errors', 0);
ini_set('log_errors', 0);
ini_set('default_socket_timeout', 30);

// Debug log to confirm request type and session
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Connection retry mechanism
function getConnection($attempt = 0) {
    global $conn;
    $max_attempts = 3;
    if ($attempt >= $max_attempts) {
        ob_end_clean();
        die(json_encode(['success' => false, 'error' => 'Database connection failed']));
    }
    
    // Check if connection already exists and is valid
    if (isset($conn) && is_object($conn)) {
        try {
            if ($conn->ping()) {
                return $conn;
            }
        } catch (Exception $e) {
            // Connection ping failed
        }
    }
    
    $conn = mysqli_connect("localhost", "root", "", "Cube");
    if (!$conn) {
        sleep(2);
        return getConnection($attempt + 1);
    }
    return $conn;
}

// Only create new connection if not already set
if (!isset($conn) || !is_object($conn)) {
    $conn = getConnection();
}

// Session check only for non-AJAX
if (!$is_ajax && (!isset($_SESSION['Staff_ID']) || (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'))) {
    header("Location: admin_login.php");
    exit();
}

$current_staff_id = isset($_SESSION['Staff_ID']) ? $_SESSION['Staff_ID'] : 0;

// Load or initialize the image state JSON file
$state_file = 'profile_image_state.json';
$state_data = [];
if (file_exists($state_file)) {
    $state_content = file_get_contents($state_file);
    $state_data = json_decode($state_content, true) ?: [];
}

// Base query to fetch all Admins/Super Admins with profile images
if (!$is_ajax) {
    $sql = "SELECT Staff_ID, Staff_Name, Staff_Email, Staff_Status, Staff_Role, Profile_Image, Join_Date FROM Staff WHERE Staff_Role IN ('Admin', 'Super Admin')";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        $staff_list = [];
    } else {
        $staff_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_free_result($result);
    }
}

// Handle status toggle
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    // Clear any output and set headers
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    
    $staff_id = intval($_POST['staff_id']);
    $sql = "SELECT Staff_Status FROM Staff WHERE Staff_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $staff_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $current_status = mysqli_fetch_assoc($result)['Staff_Status'] ?? 1;
            mysqli_stmt_close($stmt);

            $new_status = $current_status ? 0 : 1;
            $update_sql = "UPDATE Staff SET Staff_Status = ? WHERE Staff_ID = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, "ii", $new_status, $staff_id);
                $success = mysqli_stmt_execute($update_stmt);
                mysqli_stmt_close($update_stmt);

                if ($success) {
                    echo json_encode(['success' => true, 'status' => $new_status]);
                    exit();
                } else {
                    $error = mysqli_error($conn);
                    echo json_encode(['success' => false, 'error' => "Update failed: $error"]);
                    exit();
                }
            }
        }
    }
    echo json_encode(['success' => false, 'error' => 'Database operation failed']);
    exit();
}

// Handle add staff submission via AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_add'])) {
    // Clear any output and set headers
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => ''];
    $staff_name = trim($_POST['staff_name'] ?? '');
    $staff_email = trim($_POST['staff_email'] ?? '');
    $staff_role = trim($_POST['staff_role'] ?? '');
    $join_date = trim($_POST['join_date'] ?? '');

    if (empty($staff_name) || empty($staff_email) || empty($join_date)) {
        $response['message'] = "Name, email, and join date are required.";
    } elseif (!filter_var($staff_email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = "Invalid email format.";
    } else {
        $check_sql = "SELECT Staff_ID FROM Staff WHERE Staff_Email = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "s", $staff_email);
            if (mysqli_stmt_execute($check_stmt)) {
                mysqli_stmt_store_result($check_stmt);
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $response['message'] = "Email already exists.";
                } else {
                    $staff_password = "@Bcd1234"; // Store plain text password
                    $insert_sql = "INSERT INTO Staff (Staff_Name, Staff_Email, Staff_Role, Join_Date, Staff_Password) VALUES (?, ?, ?, ?, ?)";
                    $insert_stmt = mysqli_prepare($conn, $insert_sql);
                    if ($insert_stmt) {
                        mysqli_stmt_bind_param($insert_stmt, "sssss", $staff_name, $staff_email, $staff_role, $join_date, $staff_password);
                        if (mysqli_stmt_execute($insert_stmt)) {
                            $new_staff_id = mysqli_insert_id($conn);
                            $response = [
                                'success' => true,
                                'message' => 'Added staff successfully',
                                'refresh' => true,
                                'new_staff' => [
                                    'Staff_ID' => $new_staff_id,
                                    'Staff_Name' => $staff_name,
                                    'Staff_Email' => $staff_email,
                                    'Staff_Role' => $staff_role,
                                    'Join_Date' => $join_date,
                                    'Staff_Status' => 1,
                                    'Profile_Image' => $state_data[$new_staff_id] ?? 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png'
                                ]
                            ];
                        } else {
                            $error = mysqli_error($conn);
                            $response['message'] = "Failed to add staff. Error: $error";
                            $response['error'] = $error;
                        }
                        mysqli_stmt_close($insert_stmt);
                    } else {
                        $error = mysqli_error($conn);
                        $response['message'] = "Prepare failed. Error: $error";
                        $response['error'] = $error;
                    }
                }
                mysqli_stmt_close($check_stmt);
            } else {
                $error = mysqli_error($conn);
                $response['message'] = "Execute failed. Error: $error";
                $response['error'] = $error;
            }
        } else {
            $error = mysqli_error($conn);
            $response['message'] = "Prepare failed. Error: $error";
            $response['error'] = $error;
        }
    }
    
    echo json_encode($response);
    exit();
}

// Fallback handler for any other AJAX POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST" && $is_ajax) {
    // Clear any output and set headers
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    
    echo json_encode(['success' => false, 'message' => 'No specific handler found for this request']);
    exit();
}

// HTML output for non-AJAX requests
if (!$is_ajax) {
    ?>
    <style>
        body {
            margin: 0;
            padding: 0;
            margin-top: 0px;
            margin-bottom: 0px;
            height: calc(100vh - 60px);
            box-sizing: border-box;
            background-color: white;
            position: relative;
        }
                 .ams-adminGrid {
             display: grid;
             grid-template-columns: repeat(4, 1fr);
             gap: 20px;
             padding: 20px;
             background-color: transparent;
             min-height: 300px;
             align-items: start;
             justify-items: start;
             grid-auto-flow: row;
             grid-auto-columns: 1fr;
             box-sizing: border-box;
             width: 100%;
             position: relative;
         }
        .ams-container {
            margin-top: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ams-mainContainer {
            max-height: calc(78vh - 60px - 80px);
            overflow-y: auto;
            padding-bottom: 10px;
            background-color: lightgrey;
            border: 1px solid #ccc;
            border-color: #000000;
            border-radius: 5px;
            height: 430px;
            position: relative;
            box-sizing: border-box;
        }
        .ams-success-message {
            text-align: center;
            color: #28a745;
            font-weight: bold;
            margin: 10px 0;
            position: absolute;
            top: -40px;
            left: 0;
            width: 100%;
        }
         .ams-custom-search {
             display: inline-block;
             position: relative;
         }
         .ams-searchInput {
             padding: 12px;
             border: 1px solid #ccc;
             border-radius: 5px;
             width: 180px;
             box-sizing: border-box;
         }
        .ams-filterSelect {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 180px;
            box-sizing: border-box;
            margin-left: 10px;
        }
        .ams-addStaffLink {
            padding: 12px 30px;
            background: #0066cc;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-left: auto;
            border: 2px solid #004d99;
        }
                 .ams-adminBox {
             border: 1px solid #ccc;
             border-color: #005b6fff;
             border-radius: 5px;
             padding: 15px;
             text-align: center;
             line-height: 25px;
             min-height: 300px;
             box-sizing: border-box;
             display: flex;
             flex-direction: column;
             justify-content: space-between;
             background-color: white;
             width: 100%;
             max-width: 100%;
             position: relative;
         }
        .ams-adminBox div:first-child div {
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border: 2px solid #ccc;
            border-radius: 5px;
            overflow: hidden;
        }
        .ams-adminBox div:first-child div img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .ams-adminBox div:first-child span.label {
            font-weight: bold;
            color: #333;
            text-align: left;
            display: inline-block;
            width: 70px;
            margin-bottom: 5px;
        }
        .ams-adminBox div:first-child span.value {
            color: #555;
            text-align: right;
            display: inline-block;
            width: calc(100% - 80px);
            margin-bottom: 5px;
        }
        .ams-adminBox div:first-child span.value.role {
            text-align: right;
        }
        .ams-roleBox-admin {
            background-color: #5490ffff;
            padding: 2px 8px;
            border: 1px solid #ccc;
            border-color: #005b6fff;
            border-radius: 3px;
            display: inline-block;
        }
        .ams-roleBox-superAdmin {
            background-color: #ffd700;
            padding: 2px 8px;
            border: 1px solid #ccc;
            border-color: #005b6fff;
            border-radius: 3px;
            display: inline-block;
        }
        .ams-roleBox-admin span, .ams-roleBox-superAdmin span {
            color: black;
            font-weight: bold;
        }
        .ams-adminBox div:last-child {
            margin-top: auto;
        }
        .ams-adminBox div:last-child form {
            display: inline;
        }
        .ams-toggleButton {
            padding: 12px 30px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
        }
        .ams-toggleButton[form] {
            background: #28a745;
        }
        p[style*="grid-column"] {
            grid-column: 1 / -1;
            text-align: center;
            color: #666;
        }
        h1.amsheader {
            font-size: 24px;
            color: #333;
            margin: 10px 0px 10px 0px;
        }
        h2.amssubtitle {
            font-size: 16px;
            color: #666;
            margin: 10px 0px 10px 0px;
        }
        .ams-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        .ams-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            width: 500px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        .ams-modal button {
            padding: 12px 30px;
            margin: 8px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .ams-modal .ams-confirmYes {
            background-color: #28a745;
            color: white;
        }
        .ams-modal .ams-confirmNo {
            background-color: #dc3545;
            color: white;
        }
        .ams-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1001;
        }
        .ams-popup-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 500px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }
    </style>

    <body>
    <h1 class="amsheader">Admin Staff Management</h1>
    <h2 class="amssubtitle">Manage and monitor all admin and super admin accounts</h2>
    <div class="ams-container">
        <div>
            <div class="ams-custom-search">
                <input type="text" id="ams-searchInput" placeholder="Search by name or email" title="Search staff by name or email">
            </div>
            <select id="ams-filterSelect" class="ams-filterSelect" title="Filter staff by role">
                <option value="all">All Roles</option>
                <option value="Admin">Admin</option>
                <option value="Super Admin">Super Admin</option>
            </select>
        </div>
        <a href="#" class="ams-addStaffLink" onclick="openPopup(); return false;" title="Add new staff member">Add Staff</a>
    </div>

         <div id="ams-status-message" style="display: none; text-align: left; color: #28a745; font-weight: bold; margin: 10px 0; padding: 10px; border-radius: 5px; background-color: #f8f9fa; border: 1px solid #d4edda;">
         <img src="https://cdn-icons-png.flaticon.com/512/190/190411.png" alt="Success" style="width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;">
         <span id="ams-status-text"></span>
     </div>
     <div class="ams-mainContainer">
         <?php if (isset($success_message)): ?>
             <div class="ams-success-message"><a href="https://i.pinimg.com/564x/e3/0d/b7/e30db7466f1c3f7eaa110351e400bb79.jpg" style="margin-right: 10px;"><img src="https://i.pinimg.com/564x/e3/0d/b7/e30db7466f1c3f7eaa110351e400bb79.jpg" alt="Success Icon" style="width: 20px; height: 20px;"></a><?php echo $success_message; ?></div>
         <?php endif; ?>
         <div id="ams-notification" style="display: none; position: fixed; top: 20px; right: 20px; padding: 15px; border-radius: 5px; z-index: 9999; color: white;"></div>
        <div class="ams-adminGrid">
            <?php if (empty($staff_list)): ?>
                <p>No Admins or Super Admins found.</p>
            <?php else: ?>
                <?php foreach ($staff_list as $staff): ?>
                    <?php if (isset($staff['Staff_ID']) && $staff['Staff_ID'] != $current_staff_id): ?>
                        <div class="ams-adminBox" data-name="<?php echo htmlspecialchars(strtolower($staff['Staff_Name'] ?? $staff['Staff_Email'])); ?>" data-email="<?php echo htmlspecialchars(strtolower($staff['Staff_Email'])); ?>" data-role="<?php echo htmlspecialchars(strtolower($staff['Staff_Role'])); ?>">
                            <div>
                                <div>
                                    <img src="<?php echo htmlspecialchars($state_data[$staff['Staff_ID']] ?? $staff['Profile_Image'] ?? 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png'); ?>" alt="Staff Profile">
                                </div>
                                <span class="label">Name:</span><span class="value"><?php echo htmlspecialchars($staff['Staff_Name'] ?? $staff['Staff_Email']); ?></span><br>
                                <span class="label">Email:</span><span class="value"><?php echo htmlspecialchars($staff['Staff_Email']); ?></span><br>
                                <span class="label">Role:</span><span class="value role">
                                    <?php if ($staff['Staff_Role'] === 'Admin'): ?>
                                        <span class="ams-roleBox-admin"><span><?php echo htmlspecialchars($staff['Staff_Role']); ?></span></span>
                                    <?php elseif ($staff['Staff_Role'] === 'Super Admin'): ?>
                                        <span class="ams-roleBox-superAdmin"><span><?php echo htmlspecialchars($staff['Staff_Role']); ?></span></span>
                                    <?php endif; ?>
                                </span>
                                <span class="label">JoinDate:</span><span class="value"><?php echo htmlspecialchars($staff['Join_Date']); ?></span>
                            </div>
                            <div>
                                <form method="POST" action="">
                                    <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff['Staff_ID']); ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <button type="button" class="ams-toggleButton" style="background: <?php echo isset($staff['Staff_Status']) && $staff['Staff_Status'] ? '#dc3545' : '#28a745'; ?>" onclick="confirmToggle(event, <?php echo htmlspecialchars($staff['Staff_ID']); ?>, <?php echo json_encode(isset($staff['Staff_Status']) && $staff['Staff_Status']); ?>)" title="<?php echo isset($staff['Staff_Status']) && $staff['Staff_Status'] ? 'Deactivate this staff' : 'Activate this staff'; ?>">
                                        <?php echo isset($staff['Staff_Status']) && $staff['Staff_Status'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="ams-modal" class="ams-modal">
        <div class="ams-modal-content">
            <p>Are you sure you want to <strong id="ams-actionText"></strong> this staff?</p>
            <button class="ams-confirmYes" id="confirmYesBtn" title="Confirm action">Yes</button>
            <button class="ams-confirmNo" id="confirmNoBtn" title="Cancel action">No</button>
        </div>
    </div>

    <div id="ams-popup" class="ams-popup">
        <div class="ams-popup-content">
            <h2 style="text-align: center; margin-bottom: 20px; color: #333;">Add Staff</h2>
            <div id="ams-error-message" style="text-align: center; color: #ff0000; margin-bottom: 15px; display: none;"></div>
            <form method="POST" action="" id="addStaffForm" style="display: flex; flex-direction: column; gap: 15px;">
                <div style="display: flex; align-items: center;">
                    <label for="staff_name" style="flex: 1; font-weight: bold; color: #333; margin-right: 10px;">Name</label>
                    <input type="text" id="staff_name" name="staff_name" value="" required style="flex: 2; padding: 8px; border: 1px solid #ccc; border-radius: 3px;" placeholder="Enter staff name">
                </div>
                <div style="display: flex; align-items: center;">
                    <label for="staff_email" style="flex: 1; font-weight: bold; color: #333; margin-right: 10px;">Email</label>
                    <input type="email" id="staff_email" name="staff_email" value="" required style="flex: 2; padding: 8px; border: 1px solid #ccc; border-radius: 3px;" placeholder="Enter staff email">
                </div>
                <div style="display: flex; align-items: center;">
                    <label for="staff_role" style="flex: 1; font-weight: bold; color: #333; margin-right: 10px;">Role</label>
                    <select id="staff_role" name="staff_role" style="flex: 2; padding: 8px; border: 1px solid #ccc; border-radius: 3px;" title="Select staff role">
                        <option value="Admin">Admin</option>
                        <option value="Super Admin">Super Admin</option>
                    </select>
                </div>
                <div style="display: flex; align-items: center;">
                    <label for="join_date" style="flex: 1; font-weight: bold; color: #333; margin-right: 10px;">Join Date</label>
                    <input type="date" id="join_date" name="join_date" value="" required style="flex: 2; padding: 8px; border: 1px solid #ccc; border-radius: 3px;" placeholder="Select join date">
                </div>
                <div style="display: flex; align-items: center;">
                    <label for="staff_password" style="flex: 1; font-weight: bold; color: #333; margin-right: 10px;">Password</label>
                    <input type="text" id="staff_password" name="staff_password" value="@Bcd1234" disabled style="flex: 2; padding: 8px; border: 1px solid #ccc; border-radius: 3px;" placeholder="Default password">
                </div>
                                 <button type="submit" name="submit_add" value="Add Staff" style="padding: 10px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;" title="Submit to add new staff">Add Staff</button>
            </form>
            <div style="text-align: center; margin-top: 15px;">
                <button type="button" onclick="closePopup()" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 3px; cursor: pointer;">Cancel</button>
            </div>
        </div>
    </div>

    <script>
    // Global functions for popup management
    function openPopup() {
        const popup = document.getElementById('ams-popup');
        const errorMessage = document.getElementById('ams-error-message');
        if (popup) {
            popup.style.display = 'block';
            if (errorMessage) {
                errorMessage.style.display = 'none';
                errorMessage.textContent = '';
            }
        }
    }

    function closePopup() {
        const popup = document.getElementById('ams-popup');
        const addStaffForm = document.getElementById('addStaffForm');
        if (popup) {
            popup.style.display = 'none';
            if (addStaffForm) {
                addStaffForm.reset();
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('ams-searchInput');
        const filterSelect = document.getElementById('ams-filterSelect');
        const adminGrid = document.querySelector('.ams-adminGrid');
        const addStaffForm = document.getElementById('addStaffForm');
        const popup = document.getElementById('ams-popup');
        const errorMessage = document.getElementById('ams-error-message');

        let initialBoxes = Array.from(document.querySelectorAll('.ams-adminBox'));

                 function filterAndSearch() {
             const searchTerm = searchInput.value.toLowerCase().trim();
             const filterRole = filterSelect.value.toLowerCase();
             
             // Get all current boxes in the grid
             const currentBoxes = Array.from(adminGrid.querySelectorAll('.ams-adminBox'));
             
             currentBoxes.forEach(box => {
                 const name = box.getAttribute('data-name');
                 const email = box.getAttribute('data-email');
                 const role = box.getAttribute('data-role');
                 const matchesSearch = searchTerm === '' || name.includes(searchTerm) || email.includes(searchTerm);
                 const matchesFilter = filterRole === 'all' || role === filterRole;

                 if (matchesSearch && matchesFilter) {
                     box.style.display = 'block';
                 } else {
                     box.style.display = 'none';
                 }
             });
             
             // Check if any boxes are visible
             const visibleBoxes = currentBoxes.filter(box => box.style.display !== 'none');
             
             if (visibleBoxes.length === 0 && (searchTerm !== '' || filterRole !== 'all')) {
                 // Show "no results" message while maintaining grid structure
                 const noResultsMsg = adminGrid.querySelector('.no-results-msg');
                 if (!noResultsMsg) {
                     const msg = document.createElement('div');
                     msg.className = 'no-results-msg';
                     msg.style.cssText = 'position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: #666; font-size: 20px; font-weight: 500; display: flex; flex-direction: column; justify-content: center; align-items: center;';
                     
                     // Create search icon
                     const searchIcon = document.createElement('img');
                     searchIcon.src = 'https://cdn-icons-png.flaticon.com/512/3917/3917132.png';
                     searchIcon.style.cssText = 'width: 40px; height: 40px; margin-bottom: 15px; opacity: 0.6;';
                     searchIcon.alt = 'Search';
                     
                     // Create text
                     const text = document.createElement('span');
                     text.textContent = 'No matching records available';
                     
                     msg.appendChild(searchIcon);
                     msg.appendChild(text);
                     adminGrid.appendChild(msg);
                 }
             } else {
                 // Remove "no results" message if it exists
                 const noResultsMsg = adminGrid.querySelector('.no-results-msg');
                 if (noResultsMsg) {
                     noResultsMsg.remove();
                 }
             }
         }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        searchInput.addEventListener('input', debounce(filterAndSearch, 300));
        filterSelect.addEventListener('change', filterAndSearch);

        let currentToggleForm = null;
        function confirmToggle(event, staffId, isActive) {
            event.preventDefault();
            currentToggleForm = event.target.closest('form');
            const actionText = isActive ? 'Deactivate' : 'Activate';
            document.getElementById('ams-actionText').textContent = actionText;
            document.getElementById('ams-modal').style.display = 'block';
        }

        function confirmYes(event) {
            event.preventDefault();
            if (currentToggleForm) {
                const formData = new FormData(currentToggleForm);
                
                // Fix: Use the correct URL for AJAX requests
                const currentUrl = window.location.href.split('?')[0];
                const ajaxUrl = currentUrl + '?page=admin_manage_staff.php';
                
                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                                 .then(data => {
                     if (data.success) {
                         // Show success message above mainContainer
                         const statusMessage = document.getElementById('ams-status-message');
                         const statusText = document.getElementById('ams-status-text');
                         statusText.textContent = 'Status updated successfully!';
                         statusMessage.style.display = 'block';
                         setTimeout(() => {
                             statusMessage.style.display = 'none';
                             window.location.reload();
                         }, 2000);
                     } else {
                         // Show error message
                         const errorMsg = document.createElement('div');
                         errorMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #dc3545; color: white; padding: 15px; border-radius: 5px; z-index: 9999;';
                         errorMsg.textContent = 'Error: ' + (data.error || 'Unknown error occurred');
                         document.body.appendChild(errorMsg);
                         setTimeout(() => document.body.removeChild(errorMsg), 3000);
                     }
                    document.getElementById('ams-modal').style.display = 'none';
                    currentToggleForm = null;
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    // Show error message
                    const errorMsg = document.createElement('div');
                    errorMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #dc3545; color: white; padding: 15px; border-radius: 5px; z-index: 9999;';
                    errorMsg.textContent = 'Network error: ' + error.message;
                    document.body.appendChild(errorMsg);
                    setTimeout(() => document.body.removeChild(errorMsg), 3000);
                    document.getElementById('ams-modal').style.display = 'none';
                    currentToggleForm = null;
                });
            }
        }

        function closeModal(event) {
            event.preventDefault();
            document.getElementById('ams-modal').style.display = 'none';
            currentToggleForm = null;
        }

        // Add event listeners for confirmation buttons
        document.getElementById('confirmYesBtn').addEventListener('click', confirmYes);
        document.getElementById('confirmNoBtn').addEventListener('click', closeModal);

        // Add event listeners for popup management
        popup.addEventListener('click', function(event) {
            if (event.target === popup) {
                closePopup();
            }
        });

                 addStaffForm.addEventListener('submit', function(event) {
             event.preventDefault();
             const formData = new FormData(this);
             
             // Explicitly add the submit_add field
             formData.append('submit_add', 'Add Staff');
             
             // Debug: Log the form data
             console.log('Submitting form data:', Object.fromEntries(formData));
             
             // Clear any previous error messages
             errorMessage.style.display = 'none';
             errorMessage.textContent = '';
             
             // Use the current URL directly since we're already on the correct page
             fetch(window.location.href, {
                 method: 'POST',
                 body: formData,
                 headers: {
                     'X-Requested-With': 'XMLHttpRequest'
                 }
             })
                         .then(response => {
                 console.log('Response status:', response.status);
                 console.log('Response headers:', response.headers);
                 
                 if (!response.ok) {
                     throw new Error(`HTTP error! Status: ${response.status}`);
                 }
                 return response.text().then(text => {
                     console.log('Raw response text:', text);
                     try {
                         return JSON.parse(text);
                     } catch (e) {
                         console.error('Response text:', text);
                         throw new Error('Invalid JSON response: ' + text);
                     }
                 });
             })
                         .then(data => {
                 if (data.success) {
                     // Show success message above mainContainer
                     const statusMessage = document.getElementById('ams-status-message');
                     const statusText = document.getElementById('ams-status-text');
                     statusText.textContent = 'Staff added successfully!';
                     statusMessage.style.display = 'block';
                     closePopup(); // Close the popup
                     setTimeout(() => {
                         statusMessage.style.display = 'none';
                         window.location.reload();
                     }, 2000);
                 } else {
                     errorMessage.textContent = data.message || 'An error occurred. Please try again.';
                     errorMessage.style.display = 'block';
                 }
             })
            .catch(error => {
                console.error('Fetch error:', error);
                if (error.message.includes('Method not allowed')) {
                    errorMessage.textContent = 'Server error: Please try again. If the problem persists, refresh the page.';
                } else {
                    errorMessage.textContent = 'Network error: ' + error.message;
                }
                errorMessage.style.display = 'block';
            });
        });

        // Use event delegation for toggle buttons since they're dynamically created
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('ams-toggleButton')) {
                const form = event.target.closest('form');
                if (form) {
                    const staffId = form.querySelector('input[name="staff_id"]').value;
                    const isActive = event.target.style.backgroundColor === 'rgb(40, 167, 69)';
                    confirmToggle(event, staffId, !isActive);
                }
            }
        });

        const addStaffButton = document.querySelector('.ams-addStaffLink');
        if (addStaffButton) {
            addStaffButton.addEventListener('click', function(event) {
                event.preventDefault();
                openPopup();
            });
        }
    });
    </script>
    </body>
    <?php
}
ob_end_flush();
?>