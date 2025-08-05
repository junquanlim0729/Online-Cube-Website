<?php
date_default_timezone_set('Asia/Kuala_Lumpur');
require_once 'dataconnection.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('default_socket_timeout', 30);

// Connection retry mechanism
function getConnection($attempt = 0) {
    global $conn;
    $max_attempts = 3;
    if ($attempt >= $max_attempts) {
        die("Connection failed after $max_attempts attempts: " . mysqli_connect_error());
    }
    $conn = mysqli_connect("localhost", "root", "", "Cube");
    if (!$conn) {
        sleep(2); // Wait before retry
        return getConnection($attempt + 1);
    }
    return $conn;
}

$conn = getConnection();

// Check if this is an AJAX request
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$is_ajax && (!isset($_SESSION['Staff_ID']) || $_SESSION['role'] !== 'admin')) {
    header("Location: admin_login.php");
    exit();
}

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
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
if (!$is_ajax) { // Only run this for non-AJAX (page load)
    $sql = "SELECT Staff_ID, Staff_Name, Staff_Email, Staff_Status, Staff_Role, Profile_Image, Join_Date FROM Staff WHERE Staff_Role IN ('Admin', 'Super Admin')";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        $staff_list = [];
        echo "<p style='color: red;'>Query failed: " . mysqli_error($conn) . "</p>";
    } else {
        $staff_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_free_result($result);
    }
}

// Handle status toggle
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
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
                } else {
                    $error = mysqli_error($conn);
                    echo json_encode(['success' => false, 'error' => "Update failed: $error"]);
                }
            } else {
                $error = mysqli_error($conn);
                echo json_encode(['success' => false, 'error' => "Prepare failed: $error"]);
            }
        } else {
            $error = mysqli_error($conn);
            echo json_encode(['success' => false, 'error' => "Execute failed: $error"]);
        }
    } else {
        $error = mysqli_error($conn);
        echo json_encode(['success' => false, 'error' => "Prepare failed: $error"]);
    }
    exit();
}

// Handle add staff submission via AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_add'])) {
    $messages = [];
    $staff_name = trim($_POST['staff_name']);
    $staff_email = trim($_POST['staff_email']);
    $staff_role = trim($_POST['staff_role']);
    $join_date = trim($_POST['join_date']);

    if (empty($staff_name) || empty($staff_email) || empty($join_date)) {
        $messages[] = "Name, email, and join date are required.";
    } elseif (!filter_var($staff_email, FILTER_VALIDATE_EMAIL)) {
        $messages[] = "Invalid email format.";
    } else {
        $check_sql = "SELECT Staff_ID FROM Staff WHERE Staff_Email = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, "s", $staff_email);
            if (mysqli_stmt_execute($check_stmt)) {
                mysqli_stmt_store_result($check_stmt);
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    $messages[] = "Email already exists.";
                } else {
                    $staff_password = password_hash("@Bcd1234", PASSWORD_DEFAULT);
                    $insert_sql = "INSERT INTO Staff (Staff_Name, Staff_Email, Staff_Role, Join_Date, Staff_Password) VALUES (?, ?, ?, ?, ?)";
                    $insert_stmt = mysqli_prepare($conn, $insert_sql);
                    if ($insert_stmt) {
                        mysqli_stmt_bind_param($insert_stmt, "sssss", $staff_name, $staff_email, $staff_role, $join_date, $staff_password);
                        if (mysqli_stmt_execute($insert_stmt)) {
                            $new_staff_id = mysqli_insert_id($conn);
                            $new_staff = [
                                'Staff_ID' => $new_staff_id,
                                'Staff_Name' => $staff_name,
                                'Staff_Email' => $staff_email,
                                'Staff_Role' => $staff_role,
                                'Join_Date' => $join_date,
                                'Staff_Status' => 1,
                                'Profile_Image' => $state_data[$new_staff_id] ?? 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png'
                            ];
                            mysqli_stmt_close($insert_stmt);
                            echo json_encode(['success' => true, 'message' => 'Added staff successfully', 'new_staff' => $new_staff, 'refresh' => true]);
                        } else {
                            $error = mysqli_error($conn);
                            $messages[] = "Failed to add staff. Error: $error";
                            echo json_encode(['success' => false, 'message' => $messages[0], 'error' => $error, 'full_response' => ob_get_clean()]);
                        }
                    } else {
                        $error = mysqli_error($conn);
                        $messages[] = "Prepare failed. Error: $error";
                        echo json_encode(['success' => false, 'message' => $messages[0], 'error' => $error, 'full_response' => ob_get_clean()]);
                    }
                }
                mysqli_stmt_close($check_stmt);
            } else {
                $error = mysqli_error($conn);
                $messages[] = "Execute failed. Error: $error";
                echo json_encode(['success' => false, 'message' => $messages[0], 'error' => $error, 'full_response' => ob_get_clean()]);
            }
        } else {
            $error = mysqli_error($conn);
            $messages[] = "Prepare failed. Error: $error";
            echo json_encode(['success' => false, 'message' => $messages[0], 'error' => $error, 'full_response' => ob_get_clean()]);
        }
    }
    exit();
}

// HTML output only for non-AJAX requests
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
        .ams-searchInput {
            padding: 25px;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 600px;
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
            padding: 15px 30px;
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
            border: 2px solid #b30000;
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
            padding: 20px;
            border-radius: 5px;
            width: 400px;
            text-align: center;
        }
        .ams-modal button {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
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
            <input type="text" id="ams-searchInput" placeholder="Search by name or email">
            <select id="ams-filterSelect" class="ams-filterSelect">
                <option value="all">All Roles</option>
                <option value="Admin">Admin</option>
                <option value="Super Admin">Super Admin</option>
            </select>
        </div>
        <a href="#" class="ams-addStaffLink" onclick="openPopup(); return false;">Add Staff</a>
    </div>

    <div class="ams-mainContainer">
        <?php if (isset($success_message)): ?>
            <div class="ams-success-message"><a href="https://i.pinimg.com/564x/e3/0d/b7/e30db7466f1c3f7eaa110351e400bb79.jpg" style="margin-right: 10px;"><img src="https://i.pinimg.com/564x/e3/0d/b7/e30db7466f1c3f7eaa110351e400bb79.jpg" alt="Success Icon" style="width: 20px; height: 20px;"></a><?php echo $success_message; ?></div>
        <?php endif; ?>
        <div class="ams-adminGrid">
            <?php if (empty($staff_list)): ?>
                <p>No Admins or Super Admins found.</p>
            <?php else: ?>
                <?php foreach ($staff_list as $staff): ?>
                    <?php if ($staff['Staff_ID'] != $current_staff_id): ?>
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
                                    <button type="button" class="ams-toggleButton" style="background: <?php echo $staff['Staff_Status'] ? '#dc3545' : '#28a745'; ?>" onclick="confirmToggle(event, <?php echo htmlspecialchars($staff['Staff_ID']); ?>, <?php echo $staff['Staff_Status'] ? 'true' : 'false'; ?>)">
                                        <?php echo $staff['Staff_Status'] ? 'Deactivate' : 'Activate'; ?>
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
            <button class="ams-confirmYes" onclick="confirmYes(event)">Yes</button>
            <button class="ams-confirmNo" onclick="closeModal(event)">No</button>
        </div>
    </div>

    <div id="ams-popup" class="ams-popup">
        <div class="ams-popup-content">
            <h2 style="text-align: center; margin-bottom: 20px; color: #333;">Add Staff</h2>
            <?php if (isset($messages) && !empty($messages)): ?>
                <div style="text-align: center; color: #ff0000; margin-bottom: 15px;"><?php echo $messages[0]; ?></div>
            <?php endif; ?>
            <form method="POST" action="" id="addStaffForm" style="display: flex; flex-direction: column; gap: 15px;">
                <div style="display: flex; align-items: center;">
                    <label style="flex: 1; font-weight: bold; color: #333; margin-right: 10px;">Name</label>
                    <input type="text" name="staff_name" value="" required style="flex: 2; padding: 8px; border: 1px solid #ccc; border-radius: 3px;">
                </div>
                <div style="display: flex; align-items: center;">
                    <label style="flex: 1; font-weight: bold; color: #333; margin-right: 10px;">Email</label>
                    <input type="email" name="staff_email" value="" required style="flex: 2; padding: 8px; border: 1px solid #ccc; border-radius: 3px;">
                </div>
                <div style="display: flex; align-items: center;">
                    <label style="flex: 1; font-weight: bold; color: #333; margin-right: 10px;">Role</label>
                    <select name="staff_role" style="flex: 2; padding: 8px; border: 1px solid #ccc; border-radius: 3px;">
                        <option value="Admin">Admin</option>
                        <option value="Super Admin">Super Admin</option>
                    </select>
                </div>
                <div style="display: flex; align-items: center;">
                    <label style="flex: 1; font-weight: bold; color: #333; margin-right: 10px;">Join Date</label>
                    <input type="date" name="join_date" value="" required style="flex: 2; padding: 8px; border: 1px solid #ccc; border-radius: 3px;">
                </div>
                <div style="display: flex; align-items: center;">
                    <label style="flex: 1; font-weight: bold; color: #333; margin-right: 10px;">Password</label>
                    <input type="text" name="staff_password" value="@Bcd1234" disabled style="flex: 2; padding: 8px; border: 1px solid #ccc; border-radius: 3px;">
                </div>
                <button type="submit" name="submit_add" value="Add Staff" style="padding: 10px; background: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer;">Add Staff</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('ams-searchInput');
        const filterSelect = document.getElementById('ams-filterSelect');
        const adminGrid = document.querySelector('.ams-adminGrid');
        const addStaffForm = document.getElementById('addStaffForm');
        const popup = document.getElementById('ams-popup');

        let initialBoxes = Array.from(document.querySelectorAll('.ams-adminBox'));

        function filterAndSearch() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const filterRole = filterSelect.value.toLowerCase();
            adminGrid.innerHTML = '';

            let found = false;
            initialBoxes.forEach(box => {
                const name = box.getAttribute('data-name');
                const email = box.getAttribute('data-email');
                const role = box.getAttribute('data-role');
                const matchesSearch = searchTerm === '' || name.includes(searchTerm) || email.includes(searchTerm);
                const matchesFilter = filterRole === 'all' || role === filterRole;

                if (matchesSearch && matchesFilter) {
                    adminGrid.appendChild(box.cloneNode(true));
                    found = true;
                }
            });

            if (!found && searchTerm !== '') {
                adminGrid.innerHTML = '<p style="grid-column: 1 / -1; text-align: center; color: #666;">No matching records available</p>';
            } else if (!found) {
                initialBoxes.forEach(box => adminGrid.appendChild(box.cloneNode(true)));
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
                fetch(window.location.href + '?t=' + new Date().getTime(), {
                    method: 'POST',
                    body: formData,
                    timeout: 30000
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}, Text: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(text => {
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error('Invalid JSON response: ' + text);
                    }
                    if (data.success) {
                        const button = currentToggleForm.querySelector('.ams-toggleButton');
                        button.style.backgroundColor = data.status ? 'rgb(40, 167, 69)' : 'rgb(220, 53, 69)';
                        button.textContent = data.status ? 'Activate' : 'Deactivate';
                    } else {
                        alert('Error: ' + (data.error || 'Unknown error occurred') + '\nFull response: ' + (data.full_response || 'No additional details'));
                    }
                    document.getElementById('ams-modal').style.display = 'none';
                    currentToggleForm = null;
                })
                .catch(error => console.error('Error:', error));
            }
        }

        function closeModal(event) {
            event.preventDefault();
            document.getElementById('ams-modal').style.display = 'none';
            currentToggleForm = null;
        }

        function openPopup() {
            if (popup) {
                popup.style.display = 'block';
            }
        }

        function closePopup() {
            if (popup) {
                popup.style.display = 'none';
                addStaffForm.reset();
            }
        }

        popup.addEventListener('click', function(event) {
            if (event.target === popup) {
                closePopup();
            }
        });

        addStaffForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const timestamp = new Date().getTime();
            const formData = new FormData(this);
            formData.append('submit_add', 'Add Staff');
            fetch(`${window.location.href}?t=${timestamp}`, {
                method: 'POST',
                body: formData,
                timeout: 30000
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}, Text: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text);
                }
                if (data.success) {
                    const successDiv = document.createElement('div');
                    successDiv.className = 'ams-success-message';
                    successDiv.innerHTML = `<a href="https://i.pinimg.com/564x/e3/0d/b7/e30db7466f1c3f7eaa110351e400bb79.jpg" style="margin-right: 10px;"><img src="https://i.pinimg.com/564x/e3/0d/b7/e30db7466f1c3f7eaa110351e400bb79.jpg" alt="Success Icon" style="width: 20px; height: 20px;"></a>${data.message}`;
                    document.querySelector('.ams-mainContainer').insertBefore(successDiv, document.querySelector('.ams-adminGrid'));
                    setTimeout(() => successDiv.remove(), 3000);

                    closePopup();
                    if (data.refresh) {
                        window.location.reload();
                    }
                } else {
                    const errorDiv = document.createElement('div');
                    errorDiv.style = 'text-align: center; color: #ff0000; margin-bottom: 15px;';
                    errorDiv.textContent = data.message || 'An error occurred. Please try again.' + (data.error ? '\nDetails: ' + data.error : '') + (data.full_response ? '\nFull response: ' + data.full_response : '');
                    this.insertBefore(errorDiv, this.firstChild);
                    setTimeout(() => errorDiv.remove(), 3000);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                const errorDiv = document.createElement('div');
                errorDiv.style = 'text-align: center; color: #ff0000; margin-bottom: 15px;';
                errorDiv.textContent = 'Network error: ' + error.message;
                this.insertBefore(errorDiv, this.firstChild);
                setTimeout(() => errorDiv.remove(), 3000);
            });
        });

        document.querySelectorAll('.ams-toggleButton').forEach(button => {
            button.addEventListener('click', function(event) {
                const form = this.closest('form');
                if (form) {
                    const staffId = form.querySelector('input[name="staff_id"]').value;
                    const isActive = this.style.backgroundColor === 'rgb(40, 167, 69)';
                    confirmToggle(event, staffId, !isActive);
                }
            });
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
mysqli_close($conn);
?>