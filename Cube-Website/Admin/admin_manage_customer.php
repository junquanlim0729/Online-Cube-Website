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

// Session check for non-AJAX
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

// Base query to fetch all customers with default address
if (!$is_ajax) {
    $sql = "SELECT c.Cust_ID, c.Cust_First_Name, c.Cust_Last_Name, c.Cust_Email, c.Cust_Phone, c.Profile_Image, c.Cust_Status, 
            a.Add_Line, a.City, a.State, a.Postcode 
            FROM Customer c 
            LEFT JOIN Address a ON c.Cust_ID = a.Cust_ID AND a.Add_Default = TRUE 
            ORDER BY c.Cust_ID ASC";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        $customer_list = [];
    } else {
        $customer_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_free_result($result);
        
        // Assign Customer IDs starting from 2001
        $base_id = 2001;
        foreach ($customer_list as &$customer) {
            $customer['Customer_ID'] = $base_id++;
            $customer['Default_Address'] = $customer['Add_Line'] . ', ' . $customer['City'] . ', ' . $customer['State'] . ', ' . $customer['Postcode'];
            unset($customer['Add_Line'], $customer['City'], $customer['State'], $customer['Postcode']);
        }
        unset($customer); // Unset reference
    }
}

// Handle status toggle
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    
    $cust_id = intval($_POST['cust_id']);
    $sql = "SELECT Cust_Status FROM Customer WHERE Cust_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $cust_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $current_status = mysqli_fetch_assoc($result)['Cust_Status'] ?? 1;
            mysqli_stmt_close($stmt);

            $new_status = $current_status ? 0 : 1;
            $update_sql = "UPDATE Customer SET Cust_Status = ? WHERE Cust_ID = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            if ($update_stmt) {
                mysqli_stmt_bind_param($update_stmt, "ii", $new_status, $cust_id);
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

// Fallback handler for any other AJAX POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST" && $is_ajax) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    
    echo json_encode(['success' => false, 'message' => 'No specific handler found for this request']);
    exit();
}

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
        .amc-container {
            margin-top: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .amc-mainContainer {
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
        .amc-success-message {
            text-align: center;
            color: #28a745;
            font-weight: bold;
            margin: 10px 0;
            position: absolute;
            top: -40px;
            left: 0;
            width: 100%;
        }
        .amc-custom-search {
            display: inline-block;
            position: relative;
        }
        .amc-searchInput {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 180px;
            box-sizing: border-box;
        }
        .amc-filterSelect {
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 180px;
            box-sizing: border-box;
            margin-left: 10px;
        }
        .amc-labels {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background-color: #f4f4f4;
            border-bottom: 2px solid #ccc;
            font-weight: bold;
        }
        .amc-labels div {
            flex: 1;
            text-align: center;
            vertical-align: middle;
        }
        .amc-customerContainer {
            width: 100%;
        }
        .amc-customerBox {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #ddd;
            background-color: white;
            min-height: 50px;
        }
        .amc-customerBox div {
            flex: 1;
            text-align: center;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .amc-customerBox .amc-id {
            flex: 0.5; /* Reduced space for ID */
        }
        .amc-customerBox .amc-image {
            flex: 0.8; /* Reduced space for Image */
        }
        .amc-customerBox .amc-email {
            flex: 2; /* Increased space for Email */
        }
        .amc-customerBox img {
            width: 40px; /* Reduced image size */
            height: 40px;
            object-fit: cover;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .amc-action-buttons {
            display: flex;
            gap: 5px;
            flex: 1.2;
        }
        .amc-viewButton, .amc-toggleButton {
            padding: 5px 8px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 10px;
            width: 70px;
        }
        .amc-viewButton {
            background: #0066cc;
            color: white;
        }
        .amc-toggleButton {
            background: #dc3545;
            color: white;
        }
        .amc-toggleButton[form] {
            background: #28a745;
        }
        .amc-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        .amc-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            width: 500px;
            text-align: center;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }
        .amc-modal button {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .amc-modal .amc-confirmYes {
            background-color: #28a745;
            color: white;
        }
        .amc-modal .amc-confirmNo {
            background-color: #dc3545;
            color: white;
        }
        .amcheader {
            font-size: 24px;
            color: #333;
            margin: 10px 0px 10px 0px;
        }
        .amcsubtitle {
            font-size: 16px;
            color: #666;
            margin: 10px 0px 10px 0px;
        }
    </style>

    <body>
    <h1 class="amcheader">Customer Management</h1>
    <h2 class="amcsubtitle">Manage and monitor all customer accounts</h2>
    <div class="amc-container">
        <div>
            <div class="amc-custom-search">
                <input type="text" id="amc-searchInput" placeholder="Search by name or email" title="Search customer by name or email">
            </div>
            <select id="amc-filterSelect" class="amc-filterSelect" title="Filter customers by status">
                <option value="all">All</option>
                <option value="activated">Activated</option>
                <option value="deactivated">Deactivated</option>
            </select>
        </div>
    </div>

    <div id="amc-status-message" style="display: none; text-align: left; color: #28a745; font-weight: bold; margin: 10px 0; padding: 10px; border-radius: 5px; background-color: #f8f9fa; border: 1px solid #d4edda;">
        <img src="https://cdn-icons-png.flaticon.com/512/190/190411.png" alt="Success" style="width: 20px; height: 20px; margin-right: 8px; vertical-align: middle;">
        <span id="amc-status-text"></span>
    </div>
    <div class="amc-mainContainer">
        <div id="amc-notification" style="display: none; position: fixed; top: 20px; right: 20px; padding: 15px; border-radius: 5px; z-index: 9999; color: white;"></div>
        <div class="amc-labels">
            <div>ID</div>
            <div>Image</div>
            <div>First Name</div>
            <div>Last Name</div>
            <div>Email</div>
            <div>Action</div>
        </div>
        <div class="amc-customerContainer">
            <?php if (empty($customer_list)): ?>
                <div class="amc-customerBox" style="text-align: center; color: #666;">No customers found.</div>
            <?php else: ?>
                <?php foreach ($customer_list as $customer): ?>
                    <div class="amc-customerBox" data-name="<?php echo htmlspecialchars(strtolower($customer['Cust_First_Name'] . ' ' . $customer['Cust_Last_Name'])); ?>" data-email="<?php echo htmlspecialchars(strtolower($customer['Cust_Email'])); ?>">
                        <div class="amc-id"><?php echo htmlspecialchars($customer['Customer_ID']); ?></div>
                        <div class="amc-image"><img src="<?php echo htmlspecialchars($state_data[$customer['Cust_ID']] ?? $customer['Profile_Image'] ?? 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png'); ?>" alt="Profile"></div>
                        <div><?php echo htmlspecialchars($customer['Cust_First_Name']); ?></div>
                        <div><?php echo htmlspecialchars($customer['Cust_Last_Name']); ?></div>
                        <div class="amc-email"><?php echo htmlspecialchars($customer['Cust_Email']); ?></div>
                        <div class="amc-action-buttons">
                            <button type="button" class="amc-viewButton" onclick="openViewPopup(<?php echo json_encode($customer); ?>)" title="View customer details">View</button>
                            <form method="POST" action="">
                                <input type="hidden" name="cust_id" value="<?php echo htmlspecialchars($customer['Cust_ID']); ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <button type="button" class="amc-toggleButton" style="background: <?php echo isset($customer['Cust_Status']) && $customer['Cust_Status'] ? '#dc3545' : '#28a745'; ?>" onclick="confirmToggle(event, <?php echo htmlspecialchars($customer['Cust_ID']); ?>, <?php echo json_encode(isset($customer['Cust_Status']) && $customer['Cust_Status']); ?>)" title="<?php echo isset($customer['Cust_Status']) && $customer['Cust_Status'] ? 'Deactivate this customer' : 'Activate this customer'; ?>">
                                    <?php echo isset($customer['Cust_Status']) && $customer['Cust_Status'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="amc-modal" class="amc-modal">
        <div class="amc-modal-content">
            <p>Are you sure you want to <strong id="amc-actionText"></strong> this customer?</p>
            <button class="amc-confirmYes" id="confirmYesBtn" title="Confirm action">Yes</button>
            <button class="amc-confirmNo" id="confirmNoBtn" title="Cancel action">No</button>
        </div>
    </div>

    <div id="amc-viewPopup" class="amc-modal">
        <div class="amc-modal-content" style="text-align: left;">
            <h2 style="text-align: center; margin-bottom: 20px; color: #333;">Customer Details</h2>
            <div id="amc-viewContent"></div>
            <button type="button" onclick="closeViewPopup()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px;">Close</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('amc-searchInput');
        const filterSelect = document.getElementById('amc-filterSelect');
        const customerContainer = document.querySelector('.amc-customerContainer');

        function filterAndSearch() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const filterStatus = filterSelect.value;

            const boxes = Array.from(customerContainer.querySelectorAll('.amc-customerBox'));
            boxes.forEach(box => {
                const name = box.getAttribute('data-name');
                const email = box.getAttribute('data-email');
                const status = box.querySelector('.amc-toggleButton').textContent.toLowerCase() === 'activate';
                const matchesSearch = searchTerm === '' || name.includes(searchTerm) || email.includes(searchTerm);
                const matchesFilter = filterStatus === 'all' || 
                    (filterStatus === 'activated' && status) || 
                    (filterStatus === 'deactivated' && !status);

                box.style.display = matchesSearch && matchesFilter ? '' : 'none';
            });

            const visibleBoxes = boxes.filter(box => box.style.display !== 'none');
            if (visibleBoxes.length === 0 && (searchTerm !== '' || filterStatus !== 'all')) {
                customerContainer.innerHTML = '<div class="amc-customerBox" style="text-align: center; color: #666;">No matching records available</div>';
            } else if (visibleBoxes.length > 0) {
                customerContainer.innerHTML = '';
                visibleBoxes.forEach(box => customerContainer.appendChild(box));
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
        function confirmToggle(event, custId, isActive) {
            event.preventDefault();
            currentToggleForm = event.target.closest('form');
            const actionText = isActive ? 'Deactivate' : 'Activate';
            document.getElementById('amc-actionText').textContent = actionText;
            document.getElementById('amc-modal').style.display = 'block';
        }

        function confirmYes(event) {
            event.preventDefault();
            if (currentToggleForm) {
                const formData = new FormData(currentToggleForm);
                const ajaxUrl = window.location.href;

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const statusMessage = document.getElementById('amc-status-message');
                        const statusText = document.getElementById('amc-status-text');
                        statusText.textContent = 'Status updated successfully!';
                        statusMessage.style.display = 'block';
                        setTimeout(() => {
                            statusMessage.style.display = 'none';
                            window.location.reload();
                        }, 2000);
                    } else {
                        const errorMsg = document.createElement('div');
                        errorMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #dc3545; color: white; padding: 15px; border-radius: 5px; z-index: 9999;';
                        errorMsg.textContent = 'Error: ' + (data.error || 'Unknown error occurred');
                        document.body.appendChild(errorMsg);
                        setTimeout(() => document.body.removeChild(errorMsg), 3000);
                    }
                    document.getElementById('amc-modal').style.display = 'none';
                    currentToggleForm = null;
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    const errorMsg = document.createElement('div');
                    errorMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #dc3545; color: white; padding: 15px; border-radius: 5px; z-index: 9999;';
                    errorMsg.textContent = 'Network error: ' + error.message;
                    document.body.appendChild(errorMsg);
                    setTimeout(() => document.body.removeChild(errorMsg), 3000);
                    document.getElementById('amc-modal').style.display = 'none';
                    currentToggleForm = null;
                });
            }
        }

        function closeModal(event) {
            event.preventDefault();
            document.getElementById('amc-modal').style.display = 'none';
            currentToggleForm = null;
        }

        document.getElementById('confirmYesBtn').addEventListener('click', confirmYes);
        document.getElementById('confirmNoBtn').addEventListener('click', closeModal);

        function openViewPopup(customer) {
            const content = `
                <img src="${customer.Profile_Image || 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png'}" alt="Profile" style="width: 100px; height: 100px; object-fit: cover; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 10px;">
                <p><strong>Customer ID:</strong> ${customer.Customer_ID}</p>
                <p><strong>First Name:</strong> ${customer.Cust_First_Name}</p>
                <p><strong>Last Name:</strong> ${customer.Cust_Last_Name}</p>
                <p><strong>Email:</strong> ${customer.Cust_Email}</p>
                <p><strong>Phone No:</strong> ${customer.Cust_Phone || 'N/A'}</p>
                <p><strong>Address:</strong> ${customer.Default_Address || 'N/A'}</p>
            `;
            document.getElementById('amc-viewContent').innerHTML = content;
            document.getElementById('amc-viewPopup').style.display = 'block';
        }

        function closeViewPopup() {
            document.getElementById('amc-viewPopup').style.display = 'none';
        }

        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('amc-toggleButton')) {
                const form = event.target.closest('form');
                if (form) {
                    const custId = form.querySelector('input[name="cust_id"]').value;
                    const isActive = event.target.style.backgroundColor === 'rgb(40, 167, 69)';
                    confirmToggle(event, custId, !isActive);
                }
            }
        });
    });
    </script>
    </body>
    <?php
}
ob_end_flush();
?>
