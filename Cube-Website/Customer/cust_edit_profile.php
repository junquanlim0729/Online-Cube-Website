<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['Cust_ID'])) {
    header('Location: cust_login.php');
    exit();
}

require_once 'dataconnection.php';

$customer_id = $_SESSION['Cust_ID'];
$messages = [];

// Load configuration file
$config_file = 'customer_profile_image_config.json';
$config_data = [];
if (file_exists($config_file)) {
    $config_content = file_get_contents($config_file);
    $config_data = json_decode($config_content, true) ?: [];
}
$default_image = $config_data['default_image'] ?? 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png';
$allowed_types = $config_data['allowed_types'] ?? ['image/png', 'image/jpeg'];
$upload_dir = rtrim($config_data['upload_dir'] ?? 'customer_uploads', '/') . '/';
if (!file_exists($upload_dir)) {
    $upload_dir = __DIR__ . '/' . $upload_dir;
    if (!file_exists($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        error_log("Failed to create upload directory: $upload_dir");
        $messages[] = "Error: Could not create upload directory.";
    }
}

// Load or initialize the image state JSON file
$state_file = 'customer_profile_image_state.json';
$state_data = [];
if (file_exists($state_file)) {
    $state_content = file_get_contents($state_file);
    $state_data = json_decode($state_content, true) ?: [];
}

// Fetch customer data from database
$sql = "SELECT Cust_ID, Cust_First_Name, Cust_Last_Name, Cust_Email, Cust_Phone, Cust_Password, Profile_Image FROM Customer WHERE Cust_ID = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($customer_data) {
    $state_data[$customer_id] = $customer_data['Profile_Image'] ?: $default_image;
    file_put_contents($state_file, json_encode($state_data, JSON_PRETTY_PRINT));
}

// Handle profile image upload
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    mysqli_begin_transaction($conn);
    header('Content-Type: application/json');

    try {
        $image_tmp = $_FILES['profile_image']['tmp_name'];
        $image_type = mime_content_type($image_tmp);
        if (!in_array($image_type, $allowed_types)) {
            throw new Exception("Only PNG and JPEG files are allowed.");
        }

        $image_name = $customer_data['Cust_First_Name'] . '_' . uniqid() . '.' . pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $upload_file = $upload_dir . $image_name;

        if ($customer_data['Profile_Image'] && file_exists($customer_data['Profile_Image']) && $customer_data['Profile_Image'] !== $default_image) {
            unlink($customer_data['Profile_Image']);
        }

        if (move_uploaded_file($image_tmp, $upload_file)) {
            $update_sql = "UPDATE Customer SET Profile_Image = ? WHERE Cust_ID = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "si", $upload_file, $customer_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $state_data[$customer_id] = $upload_file;
                file_put_contents($state_file, json_encode($state_data, JSON_PRETTY_PRINT));
                mysqli_commit($conn);
                echo json_encode(['status' => 'success', 'message' => 'Profile image updated successfully!', 'image' => $upload_file]);
            } else {
                throw new Exception("Database update failed: " . mysqli_error($conn));
            }
            mysqli_stmt_close($update_stmt);
        } else {
            throw new Exception("Failed to move uploaded file.");
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'image' => $state_data[$customer_id] ?? $default_image]);
    }
    exit();
}

// Handle profile image removal
if (isset($_POST['remove_image'])) {
    header('Content-Type: application/json');
    
    try {
        if ($customer_data['Profile_Image'] && file_exists($customer_data['Profile_Image']) && $customer_data['Profile_Image'] !== $default_image) {
            unlink($customer_data['Profile_Image']);
        }
        
        $update_sql = "UPDATE Customer SET Profile_Image = NULL WHERE Cust_ID = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $customer_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $state_data[$customer_id] = $default_image;
            file_put_contents($state_file, json_encode($state_data, JSON_PRETTY_PRINT));
            echo json_encode(['status' => 'success', 'message' => 'Profile image removed successfully!', 'image' => $default_image]);
        } else {
            throw new Exception("Database update failed: " . mysqli_error($conn));
        }
        mysqli_stmt_close($update_stmt);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'image' => $state_data[$customer_id] ?? $default_image]);
    }
    exit();
}

// Handle profile data update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    
    // Add +60 prefix to phone number if not already present
    if (!empty($phone) && !str_starts_with($phone, '+60')) {
        $phone = '+60' . $phone;
    }
    
    if (empty($first_name) || empty($last_name)) {
        $messages[] = "First name and last name are required.";
    } else {
        $update_sql = "UPDATE Customer SET Cust_First_Name = ?, Cust_Last_Name = ?, Cust_Phone = ? WHERE Cust_ID = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "sssi", $first_name, $last_name, $phone, $customer_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $messages[] = "Profile updated successfully!";
            $customer_data['Cust_First_Name'] = $first_name;
            $customer_data['Cust_Last_Name'] = $last_name;
            $customer_data['Cust_Phone'] = $phone;
        } else {
            $messages[] = "Failed to update profile: " . mysqli_error($conn);
        }
        mysqli_stmt_close($update_stmt);
    }
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $messages[] = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $messages[] = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $messages[] = "Password must be at least 6 characters long.";
    } else {
        // Verify current password
        $verify_sql = "SELECT Cust_Password FROM Customer WHERE Cust_ID = ?";
        $verify_stmt = mysqli_prepare($conn, $verify_sql);
        mysqli_stmt_bind_param($verify_stmt, "i", $customer_id);
        mysqli_stmt_execute($verify_stmt);
        $result = mysqli_stmt_get_result($verify_stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($verify_stmt);
        
        if ($row && $row['Cust_Password'] === $current_password) {
            $update_sql = "UPDATE Customer SET Cust_Password = ? WHERE Cust_ID = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "si", $new_password, $customer_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $messages[] = "Password updated successfully!";
            } else {
                $messages[] = "Failed to update password: " . mysqli_error($conn);
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $messages[] = "Current password is incorrect.";
        }
    }
}
?>

<!-- Page Title and Subtitle -->
<div style="margin-bottom: 20px;">
    <h1 style="margin: 0 0 5px 0; color: #333; font-size: 24px; font-weight: 600;">Edit Profile</h1>
    <p style="margin: 0; color: #666; font-size: 14px;">Manage your account information and settings</p>
</div>

<!-- Success Message Container -->
<div id="topMessageDiv" style="margin: 5px 0 15px 0; height: 34px; display: block; position: relative; overflow: hidden;">
    <div id="topMessageContent" style="opacity: 0; transform: translateY(-14px); transition: opacity 0.35s ease, transform 0.35s ease;"></div>
</div>

<!-- Two Row Layout -->
<div style="display:flex; gap:15px; height: calc(100vh - 200px);">
    <!-- Left Column: Profile Image and Personal Info -->
    <div style="flex:1; display:flex; flex-direction:column; gap:15px;">
        <!-- Profile Image Section -->
        <div style="flex:1; background:#f8f9fa; padding:15px; border-radius:8px; border:1px solid #e0e0e0;">
            <h3 style="margin:0 0 15px 0; color:#333; font-size: 16px;">Profile Image</h3>
            <div style="text-align:center; margin-bottom:15px;">
                <img id="profile-image" src="<?php echo htmlspecialchars($state_data[$customer_id] ?? $default_image); ?>" 
                     alt="Profile Image" style="width:120px; height:120px; border-radius:50%; object-fit:cover; border:3px solid #fff; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
            </div>
            <form id="image-form" enctype="multipart/form-data" style="text-align:center;">
                <input type="file" id="image-upload" name="profile_image" accept="image/*" style="display:none;">
                <button type="button" onclick="document.getElementById('image-upload').click()" 
                        style="background:#007bff; color:#fff; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; margin-bottom:8px; width: 100%; font-size: 14px;">
                    Choose File
                </button>
                <button type="button" id="remove-image" onclick="removeProfileImage()" 
                        style="background:#dc3545; color:#fff; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; width: 100%; font-size: 14px;"
                        <?php echo ($state_data[$customer_id] ?? $default_image) === $default_image ? 'disabled' : ''; ?>>
                    Remove Image
                </button>
            </form>
        </div>

        <!-- Personal Information Section -->
        <div style="flex:2; background:#f8f9fa; padding:15px; border-radius:8px; border:1px solid #e0e0e0;">
            <h3 style="margin:0 0 15px 0; color:#333; font-size: 16px;">Personal Information</h3>
            
            <form method="POST">
                <div style="margin-bottom:12px;">
                    <label style="display:block; margin-bottom:3px; font-weight:600; color:#333; font-size: 14px;">Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($customer_data['Cust_Email'] ?? ''); ?>" 
                           disabled style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; background:#f8f9fa; font-size: 14px;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block; margin-bottom:3px; font-weight:600; color:#333; font-size: 14px;">First Name</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($customer_data['Cust_First_Name'] ?? ''); ?>" 
                           required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; font-size: 14px;">
                </div>
                <div style="margin-bottom:12px;">
                    <label style="display:block; margin-bottom:3px; font-weight:600; color:#333; font-size: 14px;">Last Name</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($customer_data['Cust_Last_Name'] ?? ''); ?>" 
                           required style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; font-size: 14px;">
                </div>
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:3px; font-weight:600; color:#333; font-size: 14px;">Phone Number</label>
                    <div style="display:flex; align-items:center;">
                        <span style="background:#f8f9fa; padding:8px; border:1px solid #ddd; border-right:none; border-radius:4px 0 0 4px; color:#666; font-size: 14px;">+60</span>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars(str_replace('+60', '', $customer_data['Cust_Phone'] ?? '')); ?>" 
                               placeholder="Enter phone number without +60" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:0 4px 4px 0; border-left:none; font-size: 14px;">
                    </div>
                </div>
                <button type="submit" name="update_profile" 
                        style="background:#28a745; color:#fff; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; width: 100%; font-size: 14px;">
                    Save Changes
                </button>
            </form>
        </div>
    </div>

    <!-- Right Column: Password Change -->
    <div style="flex:1; background:#f8f9fa; padding:15px; border-radius:8px; border:1px solid #e0e0e0;">
        <h3 style="margin:0 0 15px 0; color:#333; font-size: 16px;">Change Password</h3>
        <form method="POST" id="password-form">
            <div style="margin-bottom:12px;">
                <label style="display:block; margin-bottom:3px; font-weight:600; color:#333; font-size: 14px;">Current Password</label>
                <input type="password" name="current_password" required 
                       style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; font-size: 14px;">
            </div>
            <div style="margin-bottom:12px;">
                <label style="display:block; margin-bottom:3px; font-weight:600; color:#333; font-size: 14px;">New Password</label>
                <input type="password" name="new_password" id="new-password" required 
                       style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; font-size: 14px;">
            </div>
            <div style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:3px; font-weight:600; color:#333; font-size: 14px;">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm-password" required 
                       style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; font-size: 14px;">
            </div>
            <button type="submit" name="change_password" 
                    style="background:#007bff; color:#fff; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; width: 100%; font-size: 14px;">
                Update Password
            </button>
        </form>
    </div>
</div>

<script>
const profileImage = document.getElementById('profile-image');
const imageUpload = document.getElementById('image-upload');
const imageForm = document.getElementById('image-form');
const removeImageBtn = document.getElementById('remove-image');
const topMessageContent = document.getElementById('topMessageContent');

function showTopMessage(message, isSuccess = true) {
    topMessageContent.className = isSuccess ? 'success' : 'error';
    topMessageContent.innerHTML = (isSuccess ? '<img src="https://cdn-icons-png.flaticon.com/512/190/190411.png" alt="Success" style="width:16px;height:16px;margin-right:6px;vertical-align:middle;">' : '') + message;
    topMessageContent.style.transition = 'none';
    topMessageContent.style.opacity = '1';
    topMessageContent.style.transform = 'translateY(0)';
    void topMessageContent.offsetHeight;
    topMessageContent.style.transition = 'opacity 0.35s ease, transform 0.35s ease';

    setTimeout(() => {
        topMessageContent.style.opacity = '0';
        topMessageContent.style.transform = 'translateY(-14px)';
        setTimeout(() => { topMessageContent.innerHTML = ''; }, 350);
    }, isSuccess ? 3000 : 2200);
}

function updateImage(src) {
    const tempImg = new Image();
    tempImg.onload = function() {
        profileImage.src = src;
        profileImage.style.opacity = '1';
    };
    tempImg.onerror = function() {
        console.error('Image load failed:', src);
        setTimeout(() => {
            window.location.reload();
        }, 500);
    };
    tempImg.src = src;
}

imageUpload.addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (!file) return;

    profileImage.style.opacity = '0.5';

    const formData = new FormData(imageForm);
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Non-JSON response text:', text);
            if (text && text.toLowerCase().includes('success')) {
                showTopMessage('Profile image updated successfully!', true);
                setTimeout(() => window.location.reload(), 4500);
                return null;
            }
            throw new Error('Invalid JSON response');
        }
    })
    .then(data => {
        if (!data) return;
        if (data.status === 'success') {
            updateImage(data.image);
            showTopMessage(data.message || 'Profile image updated successfully!', true);
            imageUpload.value = '';
            removeImageBtn.disabled = false;
            removeImageBtn.style.opacity = '1';
            removeImageBtn.style.cursor = 'pointer';
        } else {
            updateImage(data.image || '<?php echo htmlspecialchars($state_data[$customer_id] ?? $default_image); ?>?t=' + new Date().getTime());
            showTopMessage(data.message || 'Failed to upload image.', false);
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        showTopMessage('Failed to upload image.', false);
    });
});

function removeProfileImage() {
    if (removeImageBtn.disabled) return;

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'remove_image=1'
    })
    .then(response => response.text())
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Non-JSON response text:', text);
            if (text && text.toLowerCase().includes('success')) {
                showTopMessage('Profile image removed successfully!', true);
                setTimeout(() => window.location.reload(), 4500);
                return null;
            }
            throw new Error('Invalid JSON response');
        }
    })
    .then(data => {
        if (!data) return;
        if (data.status === 'success') {
            updateImage(data.image);
            showTopMessage(data.message || 'Profile image removed successfully!', true);
            removeImageBtn.disabled = true;
            removeImageBtn.style.opacity = '0.5';
            removeImageBtn.style.cursor = 'not-allowed';
        } else {
            showTopMessage(data.message || 'Failed to remove image.', false);
        }
    })
    .catch(error => {
        console.error('Remove error:', error);
        showTopMessage('Failed to remove image.', false);
    });
}

// Password validation
document.getElementById('password-form').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        showTopMessage('New passwords do not match.', false);
        return;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        showTopMessage('Password must be at least 6 characters long.', false);
        return;
    }
});

// Show messages on page load
<?php if (!empty($messages)): ?>
    <?php foreach ($messages as $message): ?>
        showTopMessage('<?php echo addslashes($message); ?>', <?php echo strpos($message, 'successfully') !== false ? 'true' : 'false'; ?>);
    <?php endforeach; ?>
<?php endif; ?>
</script>

<style>
#topMessageContent.success {
    color: #155724;
    font-weight: 600;
    font-size: 15px;
    padding: 10px 12px;
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 5px;
    width: 100%;
    box-sizing: border-box;
}

#topMessageContent.error {
    color: #721c24;
    font-weight: 600;
    font-size: 15px;
    padding: 10px 12px;
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    border-radius: 5px;
    width: 100%;
    box-sizing: border-box;
}
</style>