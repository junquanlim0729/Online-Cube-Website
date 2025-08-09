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

// Add cache control headers at the top
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Expires: 0');
header('Pragma: no-cache');

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

// Session/role check for non-AJAX
if (!$is_ajax) {
    $hasStaffId = isset($_SESSION['Staff_ID']);
    $role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
    $isAllowed = in_array($role, ['admin','super admin','superadmin'], true);
    if (!$hasStaffId || !$isAllowed) {
        header("Location: admin_login.php");
        exit();
    }
}

$current_staff_id = isset($_SESSION['Staff_ID']) ? $_SESSION['Staff_ID'] : 0;

// Load or initialize the image state JSON file
$state_file = 'profile_image_state.json';
$state_data = [];
if (file_exists($state_file)) {
    $state_content = file_get_contents($state_file);
    $state_data = json_decode($state_content, true) ?: [];
}

// Compute project base web path (e.g., /Online-Cube-Website)
$__script_name = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$__admin_dir   = rtrim(dirname($__script_name), '/\\');
$PROJECT_BASE_WEB = rtrim(dirname($__admin_dir), '/\\');
if ($PROJECT_BASE_WEB === '' || $PROJECT_BASE_WEB === '.') { $PROJECT_BASE_WEB = ''; }

// Normalize image URL from DB into a web path usable from Admin page
if (!function_exists('normalizeCustomerImageUrl')) {
    function normalizeCustomerImageUrl($path, $projectBaseWeb) {
        $path = trim((string)$path);
        if ($path === '') { return ''; }
        if (preg_match('~^https?://~i', $path)) { return $path; }
        // Already absolute within site
        if ($path[0] === '/') { return $path; }
        // Paths starting with Customer/
        if (stripos($path, 'Customer/') === 0) {
            return rtrim($projectBaseWeb, '/') . '/' . ltrim($path, '/');
        }
        // Paths starting with customer_uploads/ (saved from Customer pages)
        if (stripos($path, 'customer_uploads/') === 0) {
            return rtrim($projectBaseWeb, '/') . '/Customer/' . ltrim($path, '/');
        }
        // Fallback: treat as relative under Customer/
        return rtrim($projectBaseWeb, '/') . '/Customer/' . ltrim($path, '/');
    }
}

// Handle sorting
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'Cust_ID';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
$next_order = $sort_order === 'ASC' ? 'DESC' : 'ASC';

$sql = "SELECT c.Cust_ID, c.Cust_First_Name, c.Cust_Last_Name, c.Cust_Email, c.Cust_Phone, c.Profile_Image, c.Cust_Status, 
        a.Add_Line, a.City, a.State, a.Postcode 
        FROM Customer c 
        LEFT JOIN Address a ON c.Cust_ID = a.Cust_ID AND a.Add_Default = TRUE 
        ORDER BY c.$sort_column $sort_order";
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
            border: none !important; outline: none !important; box-shadow: none !important; /* Only page body reset */
        }
        .amc-container { margin-top: 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
        .amc-mainContainer { max-height: calc(78vh - 60px - 80px); overflow-y: auto; padding-bottom: 10px; background-color: lightgrey; border: 1px solid #ccc; border-color: #000000; border-radius: 5px; height: 430px; position: relative; box-sizing: border-box; }
        .amc-success-message { text-align: center; color: #28a745; font-weight: bold; margin: 10px 0; position: absolute; top: -40px; left: 0; width: 100%; }
        .amc-custom-search { display: inline-block; position: relative; }
        .amc-searchInput { padding: 12px; border: 1px solid #ccc; border-radius: 5px; width: 180px; box-sizing: border-box; }
        .amc-filterSelect { padding: 12px; border: 1px solid #ccc; border-radius: 5px; width: 180px; box-sizing: border-box; margin-left: 10px; }
        .amc-labels { display: flex; justify-content: space-between; padding: 8px 0; background-color: #f4f4f4; border-bottom: 2px solid #ccc; font-weight: bold; align-items: center; }
        .amc-labels div { flex: 1; text-align: center; vertical-align: middle; padding: 0 4px; position: relative; }
        .amc-customerBox { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee; background-color: white; min-height: 90px; }
        .amc-customerBox img { width: 50px; height: 50px; object-fit: cover; border: 1px solid #eee; border-radius: 5px; }
        .amc-viewButton { background: #0066cc; color: white; }
        .amc-toggleButton { color: white; }
        .amc-action button.amc-viewButton { background: #0066cc; color: white; }
    </style>

    <body>
    <script>const PROJECT_BASE = '<?php echo addslashes($PROJECT_BASE_WEB); ?>';</script>
    <div class="amc-title" style="position: relative;">
        <h1 class="amcheader" style="margin-right: 240px;">Customer Management</h1>
        <div id="amc-toast-anchor" style="position: absolute; top: 0; right: 0;"></div>
    </div>
    <h2 class="amcsubtitle">Manage and monitor all customer accounts</h2>
    <div class="amc-container" style="position: relative;">
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
        
        <div class="amc-labels">
            <div class="amc-id sortable">ID<span class="sort-arrow" id="arrow-id">↕</span></div>
            <div class="amc-image">Image</div>
            <div class="amc-fname sortable">First Name<span class="sort-arrow" id="arrow-fname">↕</span></div>
            <div class="amc-lname sortable">Last Name<span class="sort-arrow" id="arrow-lname">↕</span></div>
            <div class="amc-email sortable">Email<span class="sort-arrow" id="arrow-email">↕</span></div>
            <div class="amc-action">Action</div>
        </div>
        <div class="amc-customerContainer">
            <?php if (empty($customer_list)): ?>
                <div class="amc-customerBox" style="text-align: center; color: #666;">No customers found.</div>
            <?php else: ?>
                <?php foreach ($customer_list as $customer): ?>
                    <?php
                        $imgFromDb = !empty($customer['Profile_Image']) ? $customer['Profile_Image'] : '';
                        $normalizedImg = $imgFromDb !== '' ? normalizeCustomerImageUrl($imgFromDb, $PROJECT_BASE_WEB) : 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png';
                    ?>
                    <div class="amc-customerBox" data-name="<?php echo htmlspecialchars(strtolower($customer['Cust_First_Name'] . ' ' . $customer['Cust_Last_Name'])); ?>" data-email="<?php echo htmlspecialchars(strtolower($customer['Cust_Email'])); ?>">
                        <div class="amc-id"><?php echo htmlspecialchars($customer['Customer_ID']); ?></div>
                        <div class="amc-image"><img src="<?php echo htmlspecialchars($normalizedImg); ?>" alt="Profile"></div>
                        <div class="amc-fname"><?php echo htmlspecialchars($customer['Cust_First_Name']); ?></div>
                        <div class="amc-lname"><?php echo htmlspecialchars($customer['Cust_Last_Name']); ?></div>
                        <div class="amc-email"><?php echo htmlspecialchars($customer['Cust_Email']); ?></div>
                        <div class="amc-action">
                            <button type="button" class="amc-viewButton" data-customer='<?php echo json_encode($customer); ?>' title="View customer details">View</button>
                            <form method="POST" action="" style="display:inline;">
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
        <div class="amc-confirm-modal-content">
            <p>Are you sure you want to <strong id="amc-actionText"></strong> this customer?</p>
            <button class="amc-confirmYes" id="confirmYesBtn" title="Confirm action">Yes</button>
            <button class="amc-confirmNo" id="confirmNoBtn" title="Cancel action">No</button>
        </div>
    </div>

    <div id="amc-viewPopup" class="amc-modal">
        <div class="amc-modal-content">
            <h2 style="text-align: center; margin-top: 25px; margin-bottom: 30px; color: #333;">Customer Details</h2>
            <div id="amc-viewContent"></div>
            <button type="button" id="amc-closeViewBtn" style="padding: 10px 28px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; position: absolute; bottom: 20px; right: 20px;">Close</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('amc-searchInput');
        const filterSelect = document.getElementById('amc-filterSelect');
        const customerContainer = document.querySelector('.amc-customerContainer');
        let allBoxes = Array.from(customerContainer.querySelectorAll('.amc-customerBox'));

        // Sorting state
        let currentSort = '<?php echo $sort_column; ?>';
        let currentOrder = '<?php echo strtolower($sort_order); ?>';

        // Helper: get value for sorting
        function getSortValue(box, column) {
            switch (column) {
                case 'Cust_ID':
                    return parseInt(box.querySelector('.amc-id').textContent.trim()) || 0;
                case 'Cust_First_Name':
                    return box.querySelector('.amc-fname').textContent.trim().toLowerCase();
                case 'Cust_Last_Name':
                    return box.querySelector('.amc-lname').textContent.trim().toLowerCase();
                case 'Cust_Email':
                    return box.querySelector('.amc-email').textContent.trim().toLowerCase();
                default:
                    return '';
            }
        }

        function sortCustomerList(column) {
            if (currentSort === column) {
                currentOrder = currentOrder === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort = column;
                currentOrder = 'asc';
            }
            // Update URL with sort parameters
            const url = new URL(window.location);
            url.searchParams.set('sort', column);
            url.searchParams.set('order', currentOrder);
            window.history.pushState({}, '', url);
            
            allBoxes.sort((a, b) => {
                let valA = getSortValue(a, column);
                let valB = getSortValue(b, column);
                if (valA < valB) return currentOrder === 'asc' ? -1 : 1;
                if (valA > valB) return currentOrder === 'asc' ? 1 : -1;
                return 0;
            });
            // Remove all boxes and re-append in sorted order
            allBoxes.forEach(box => customerContainer.appendChild(box));
            updateSortArrows();
        }

        // Update sort arrow display
        function updateSortArrows() {
            // Reset all arrows to default
            const arrows = {
                'Cust_ID': 'arrow-id',
                'Cust_First_Name': 'arrow-fname',
                'Cust_Last_Name': 'arrow-lname',
                'Cust_Email': 'arrow-email'
            };
            Object.values(arrows).forEach(arrowId => {
                const arrow = document.getElementById(arrowId);
                arrow.textContent = '↕';
                arrow.className = 'sort-arrow';
            });
            // Set active arrow
            if (currentSort) {
                const arrowId = arrows[currentSort];
                if (arrowId) {
                    const arrow = document.getElementById(arrowId);
                    arrow.textContent = currentOrder === 'asc' ? '↑' : '↓';
                    arrow.className = 'sort-arrow active';
                }
            }
        }

        // Initialize sort arrows on page load
        updateSortArrows();

        // Add click listeners to headers
        document.querySelector('.amc-labels .amc-id').addEventListener('click', function() { sortCustomerList('Cust_ID'); });
        document.querySelector('.amc-labels .amc-fname').addEventListener('click', function() { sortCustomerList('Cust_First_Name'); });
        document.querySelector('.amc-labels .amc-lname').addEventListener('click', function() { sortCustomerList('Cust_Last_Name'); });
        document.querySelector('.amc-labels .amc-email').addEventListener('click', function() { sortCustomerList('Cust_Email'); });

        function filterAndSearch() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const filterStatus = filterSelect.value;
            let anyVisible = false;
            allBoxes.forEach(box => {
                if (box.hasAttribute('style') && box.style.textAlign === 'center') return; // skip 'No customers found.'
                const name = box.getAttribute('data-name');
                const email = box.getAttribute('data-email');
                const statusBtn = box.querySelector('.amc-toggleButton');
                const isActive = statusBtn && statusBtn.textContent.trim().toLowerCase() === 'deactivate';
                const matchesSearch = searchTerm === '' || (name && name.includes(searchTerm)) || (email && email.includes(searchTerm));
                const matchesFilter = filterStatus === 'all' || (filterStatus === 'activated' && isActive) || (filterStatus === 'deactivated' && !isActive);
                if (matchesSearch && matchesFilter) {
                    box.style.display = '';
                    anyVisible = true;
                } else {
                    box.style.display = 'none';
                }
            });
            let noResultMsg = customerContainer.querySelector('.amc-no-results-msg');
            if (!anyVisible) {
                if (!noResultMsg) {
                    noResultMsg = document.createElement('div');
                    noResultMsg.className = 'amc-no-results-msg';
                    noResultMsg.textContent = 'No matching records available';
                    customerContainer.appendChild(noResultMsg);
                }
            } else if (noResultMsg) {
                noResultMsg.remove();
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
                        showTopRightSuccess('Status updated successfully!');
                        setTimeout(() => {
                            window.location.reload();
                        }, 3200);
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

        // Unified top-right success notifier (3s show, float-up disappear)
        function showTopRightSuccess(message) {
            let note = document.getElementById('amc-notification');
            if (!note) {
                note = document.createElement('div');
                note.id = 'amc-notification';
                note.style.cssText = 'position: absolute; top: 0; right: 0; padding: 10px 12px; border-radius: 5px; z-index: 3; color: #155724; background: #d4edda; border: 1px solid #c3e6cb; font-weight: 600; display: block; overflow: hidden; max-width: 40vw; white-space: nowrap; text-overflow: ellipsis;';
                const anchor = document.getElementById('amc-toast-anchor') || document.body;
                anchor.appendChild(note);
            }
            note.innerHTML = '<img src="https://cdn-icons-png.flaticon.com/512/190/190411.png" alt="Success" style="width:16px;height:16px;margin-right:6px;vertical-align:middle;">' + message;
            note.style.height = '34px';
            note.style.opacity = '1';
            note.style.transform = 'translateY(0)';
            note.style.transition = 'none';
            void note.offsetHeight;
            note.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
            setTimeout(() => {
                note.style.opacity = '0';
                note.style.transform = 'translateY(-14px)';
                setTimeout(() => { note.style.display = 'none'; }, 350);
            }, 3000);
            note.style.display = 'block';
        }

        document.getElementById('confirmYesBtn').addEventListener('click', confirmYes);
        document.getElementById('confirmNoBtn').addEventListener('click', closeModal);

        function openViewPopup(customer) {
            let profileImg = (customer.Profile_Image && customer.Profile_Image.trim() !== '') ? customer.Profile_Image : 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png';
            if (!/^https?:\/\//i.test(profileImg)) {
                if (profileImg.charAt(0) !== '/') {
                    if (profileImg.toLowerCase().indexOf('customer/') === 0) {
                        profileImg = PROJECT_BASE.replace(/\/$/, '') + '/' + profileImg;
                    } else if (profileImg.toLowerCase().indexOf('customer_uploads/') === 0) {
                        profileImg = PROJECT_BASE.replace(/\/$/, '') + '/Customer/' + profileImg;
                    } else {
                        profileImg = PROJECT_BASE.replace(/\/$/, '') + '/Customer/' + profileImg.replace(/^\/+/, '');
                    }
                }
            }
            const custId = customer.Customer_ID || '';
            const fname = customer.Cust_First_Name || '';
            const lname = customer.Cust_Last_Name || '';
            const email = customer.Cust_Email || '';
            const phone = customer.Cust_Phone || 'N/A';
            let address = (customer.Default_Address && customer.Default_Address.replace(/[,\s-]+/g, '') !== '') ? customer.Default_Address : '-';
            const content = `
                <div style="display: flex; gap: 20px; align-items: flex-start; height: 400px;">
                    <div style="flex: 1.1; text-align: left;">
                        <img src="${profileImg}" alt="Profile" style="width: 200px; height: 200px; object-fit: cover; border: 1px solid #ccc; border-radius: 5px; margin-bottom: 10px;">
                        <label style="font-size: 16px; color: #555; display: block; margin-top: 6px; margin-bottom: 10px;">Customer ID</label>
                        <input type="text" value="${custId}" disabled style="width: 200px; text-align: center; font-size: 15px; background: #f4f4f4; border: 1px solid #ccc; border-radius: 3px; height: 30px; line-height: 30px;">
                    </div>
                    <div style="flex: 1.9; min-width: 300px; display: flex; flex-direction: column; gap: 15px;">
                        <div style="display: flex; gap: 10px;">
                            <div style="flex: 1;">
                                <label style="font-size: 16px; color: #555; display: block; margin-bottom: 10px;">First Name</label>
                                <input type="text" value="${fname}" disabled style="width: 100%; background: #f4f4f4; border: 1px solid #ccc; border-radius: 3px; height: 30px; line-height: 30px; font-size: 15px; padding: 12px">
                            </div>
                            <div style="flex: 1;">
                                <label style="font-size: 16px; color: #555; display: block; margin-bottom: 10px;">Last Name</label>
                                <input type="text" value="${lname}" disabled style="width: 100%; background: #f4f4f4; border: 1px solid #ccc; border-radius: 3px; height: 30px; line-height: 30px; font-size: 15px; padding: 12px">
                            </div>
                        </div>
                        <div>
                            <label style="font-size: 16px; color: #555; display: block; margin-bottom: 10px;">Email</label>
                            <input type="text" value="${email}" disabled style="width: 100%; background: #f4f4f4; border: 1px solid #ccc; border-radius: 3px; height: 30px; line-height: 30px; font-size: 15px; padding: 12px">
                        </div>
                        <div>
                            <label style="font-size: 16px; color: #555; display: block; margin-bottom: 10px;">Phone No</label>
                            <input type="text" value="${phone}" disabled style="width: 100%; background: #f4f4f4; border: 1px solid #ccc; border-radius: 3px; height: 30px; line-height: 30px; font-size: 15px; padding: 12px">
                        </div>
                        <div>
                            <label style="font-size: 16px; color: #555; display: block; margin-bottom: 10px;">Address</label>
                            <input type="text" value="${address}" disabled style="width: 100%; background: #f4f4f4; border: 1px solid #ccc; border-radius: 3px; height: 30px; line-height: 30px; font-size: 15px; padding: 12px">
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('amc-viewContent').innerHTML = content;
            document.getElementById('amc-viewPopup').style.display = 'block';
            document.getElementById('amc-closeViewBtn').onclick = closeViewPopup;
        }

        function closeViewPopup() {
            document.getElementById('amc-viewPopup').style.display = 'none';
        }

        customerContainer.addEventListener('click', function(event) {
            if (event.target.classList.contains('amc-viewButton')) {
                const customer = JSON.parse(event.target.getAttribute('data-customer'));
                openViewPopup(customer);
            }
        });

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