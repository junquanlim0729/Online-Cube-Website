<?php
require_once 'dataconnection.php';

$messages = [];
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['Staff_ID']) ? intval($_SESSION['Staff_ID']) : 0);
$staff_data = null;

// Load configuration file
$config_file = 'profile_image_config.json';
$config_data = [];
if (file_exists($config_file)) {
    $config_content = file_get_contents($config_file);
    $config_data = json_decode($config_content, true) ?: [];
}
$default_image = $config_data['default_image'] ?? 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png';
$allowed_types = $config_data['allowed_types'] ?? ['image/png', 'image/jpeg'];
$upload_dir = rtrim($config_data['upload_dir'] ?? 'uploads', '/') . '/';
$error_messages = $config_data['error_messages'] ?? [];
$single_image_policy = $config_data['single_image_policy'] ?? true;
$cleanup_on_update = $config_data['cleanup_on_update'] ?? true;

// Ensure upload directory exists and is writable
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        error_log("Failed to create upload directory: $upload_dir");
        $messages[] = "Error: Could not create upload directory.";
    }
}
if (!is_writable($upload_dir)) {
    chmod($upload_dir, 0755);
    if (!is_writable($upload_dir)) {
        error_log("Upload directory $upload_dir is not writable.");
        $messages[] = "Error: Upload directory is not writable.";
    }
}

// Load or initialize the image state JSON file
$state_file = 'profile_image_state.json';
$state_data = [];
if (file_exists($state_file)) {
    $state_content = file_get_contents($state_file);
    $state_data = json_decode($state_content, true) ?: [];
}

// Initialize upload log file for real-time tracking
$log_file = 'upload_log.json';
$log_data = [];
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $log_data = json_decode($log_content, true) ?: [];
}

// Fetch staff data from database
if ($staff_id > 0) {
    $sql = "SELECT Staff_ID, Staff_Name, Staff_Email, Staff_Role, Staff_Password, Profile_Image FROM Staff WHERE Staff_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $staff_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Sync state with database
    if ($staff_data) {
        $state_data[$staff_id] = $staff_data['Profile_Image'] ?: $default_image;
        file_put_contents($state_file, json_encode($state_data, JSON_PRETTY_PRINT));
    }
}

// Handle profile image upload
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    mysqli_begin_transaction($conn);
    header('Content-Type: application/json');

    try {
        $image_tmp = $_FILES['profile_image']['tmp_name'];
        $image_type = mime_content_type($image_tmp);
        if (!in_array($image_type, $allowed_types)) {
            throw new Exception($error_messages['invalid_type'] ?? "Only PNG and JPEG files are allowed.");
        }

        $image_name = $staff_data['Staff_Name'] . '_' . uniqid() . '.' . pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $upload_file = $upload_dir . $image_name;

        // Remove existing image if single_image_policy and cleanup_on_update are true
        if ($single_image_policy && $cleanup_on_update && $staff_data['Profile_Image'] && file_exists($staff_data['Profile_Image']) && $staff_data['Profile_Image'] !== $default_image) {
            unlink($staff_data['Profile_Image']);
        }

        if (!move_uploaded_file($image_tmp, $upload_file)) {
            throw new Exception($error_messages['upload_failed'] ?? "Failed to upload the image.");
        }

        $update_sql = "UPDATE Staff SET Profile_Image = ? WHERE Staff_ID = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $upload_file, $staff_id);
        if (!mysqli_stmt_execute($update_stmt)) {
            throw new Exception("Database update failed: " . mysqli_error($conn));
        }
        mysqli_stmt_close($update_stmt);

        $state_data[$staff_id] = $upload_file;
        file_put_contents($state_file, json_encode($state_data, JSON_PRETTY_PRINT));

        // Log upload action
        $log_data[] = [
            'staff_id' => $staff_id,
            'action' => 'upload',
            'image' => $upload_file,
            'timestamp' => date('c')
        ];
        file_put_contents($log_file, json_encode($log_data, JSON_PRETTY_PRINT));

        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'image' => $upload_file . '?t=' . time()]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Upload error for staff_id $staff_id: " . $e->getMessage());
        if (isset($upload_file) && file_exists($upload_file)) {
            unlink($upload_file);
        }
        $new_image = $staff_data['Profile_Image'] ?: $default_image;
        $state_data[$staff_id] = $new_image;
        file_put_contents($state_file, json_encode($state_data, JSON_PRETTY_PRINT));

        // Log error
        $log_data[] = [
            'staff_id' => $staff_id,
            'action' => 'upload_error',
            'error' => $e->getMessage(),
            'timestamp' => date('c')
        ];
        file_put_contents($log_file, json_encode($log_data, JSON_PRETTY_PRINT));

        echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'image' => $new_image . '?t=' . time()]);
    }
    exit();
}

// Handle image removal
if (isset($_POST['remove_image'])) {
    mysqli_begin_transaction($conn);
    header('Content-Type: application/json');

    try {
        if ($staff_data['Profile_Image'] && file_exists($staff_data['Profile_Image']) && $staff_data['Profile_Image'] !== $default_image) {
            unlink($staff_data['Profile_Image']);
        }

        $update_sql = "UPDATE Staff SET Profile_Image = NULL WHERE Staff_ID = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "i", $staff_id);
        if (!mysqli_stmt_execute($update_stmt)) {
            throw new Exception("Database update failed: " . mysqli_error($conn));
        }
        mysqli_stmt_close($update_stmt);

        $state_data[$staff_id] = $default_image;
        file_put_contents($state_file, json_encode($state_data, JSON_PRETTY_PRINT));

        // Log removal action
        $log_data[] = [
            'staff_id' => $staff_id,
            'action' => 'remove',
            'image' => $default_image,
            'timestamp' => date('c')
        ];
        file_put_contents($log_file, json_encode($log_data, JSON_PRETTY_PRINT));

        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'image' => $default_image . '?t=' . time()]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Removal error for staff_id $staff_id: " . $e->getMessage());
        $new_image = $staff_data['Profile_Image'] ?: $default_image;
        $state_data[$staff_id] = $new_image;
        file_put_contents($state_file, json_encode($state_data, JSON_PRETTY_PRINT));

        // Log error
        $log_data[] = [
            'staff_id' => $staff_id,
            'action' => 'remove_error',
            'error' => $e->getMessage(),
            'timestamp' => date('c')
        ];
        file_put_contents($log_file, json_encode($log_data, JSON_PRETTY_PRINT));

        echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'image' => $new_image . '?t=' . time()]);
    }
    exit();
}

// Handle "Save Changes" form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit'])) {
    $name = trim($_POST['name']);
    $role = trim($_POST['role']);
    $password = !empty($_POST['password']) ? password_hash(trim($_POST['password']), PASSWORD_DEFAULT) : $staff_data['Staff_Password'];

    $update_sql = "UPDATE Staff SET Staff_Name = ?, Staff_Role = ?, Staff_Password = ? WHERE Staff_ID = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "sssi", $name, $role, $password, $staff_id);
    if (mysqli_stmt_execute($update_stmt)) {
        $sql = "SELECT Staff_ID, Staff_Name, Staff_Email, Staff_Role, Staff_Password, Profile_Image FROM Staff WHERE Staff_ID = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $staff_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $staff_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        $messages[] = "Profile updated successfully.";
    } else {
        $messages[] = "Failed to update profile: " . mysqli_error($conn);
    }
    mysqli_stmt_close($update_stmt);
}

mysqli_close($conn);
?>

<div style="margin-top: 20px; margin-bottom: 20px; max-height: calc(100% - 60px); overflow-y: auto; position: relative;">
    <h2 style="position: absolute; top: 0; left: 0; margin: 0; padding: 10px 20px; color: #333; background: #f4f4f4; border-bottom: 1px solid #ccc;">My Profile</h2>
    <div style="display: flex; height: calc(100% - 40px); margin-top: 40px;">
        <div style="flex: 1; text-align: center; min-width: 150px; padding: 20px; box-sizing: border-box; border-right: 2px solid #ccc;">
            <?php if (!empty($messages)): ?>
                <div style="color: #ff0000; margin-bottom: 30px; text-align: center;" id="messageDiv"><?php echo htmlspecialchars($messages[0]); ?></div>
            <?php endif; ?>
            <?php if ($staff_data): ?>
                <form id="imageForm" enctype="multipart/form-data" style="display: flex; flex-direction: column; align-items: center; height: 100%; width: 100%;">
                    <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff_data['Staff_ID']); ?>">
                    <div style="width: 300px; height: 300px; border: 2px solid #ccc; border-radius: 5px; overflow: hidden; margin-bottom: 30px; display: flex; justify-content: center; align-items: center;" id="imageContainer">
                        <img id="profileImage" src="<?php echo htmlspecialchars($state_data[$staff_id] ?? $default_image); ?>?t=<?php echo time(); ?>" alt="Profile Image" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                    </div>
                    <input type="file" name="profile_image" accept="<?php echo implode(',', $allowed_types); ?>" id="imageUpload" style="display: block; margin: 0 auto 15px; width: 90%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box;">
                    <button type="button" id="removeImageBtn" style="display: block; margin: 0 auto 30px; width: 90%; padding: 10px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">Remove Image</button>
                </form>
            <?php else: ?>
                <p style="text-align: center; color: #666; margin-top: 20px;">Staff not found.</p>
            <?php endif; ?>
        </div>
        <div style="flex: 2; padding: 20px; box-sizing: border-box;">
            <?php if ($staff_data): ?>
                <form method="POST" action="" style="display: flex; flex-direction: column; gap: 25px;" id="editForm">
                    <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff_data['Staff_ID']); ?>">
                    <div style="display: flex; align-items: center;">
                        <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Email</label>
                        <input type="text" value="<?php echo htmlspecialchars($staff_data['Staff_Email']); ?>" readonly style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; background-color: #f0f0f0; cursor: not-allowed; width: 100%;">
                    </div>
                    <div style="display: flex; align-items: center;">
                        <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($staff_data['Staff_Name']); ?>" required style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; width: 100%;">
                    </div>
                    <div style="display: flex; align-items: center;">
                        <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Role</label>
                        <select name="role" style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; width: 100%;">
                            <option value="Admin" <?php echo $staff_data['Staff_Role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="Super Admin" <?php echo $staff_data['Staff_Role'] === 'Super Admin' ? 'selected' : ''; ?>>Super Admin</option>
                        </select>
                    </div>
                    <div style="display: flex; align-items: center;">
                        <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Password</label>
                        <input type="password" name="password" placeholder="Leave blank to keep current" style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; width: 100%;">
                    </div>
                    <div style="display: flex; align-items: center;">
                        <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="Confirm new password" style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; width: 100%;">
                    </div>
                    <button type="submit" name="submit_edit" style="width: 100%; padding: 15px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Save Changes</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const imageUpload = document.getElementById('imageUpload');
    const profileImage = document.getElementById('profileImage');
    const removeImageBtn = document.getElementById('removeImageBtn');
    const imageForm = document.getElementById('imageForm');
    const editForm = document.getElementById('editForm');
    const messageDiv = document.getElementById('messageDiv');

    imageUpload.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            profileImage.src = e.target.result; // Live preview
        };
        reader.readAsDataURL(file);

        const formData = new FormData(imageForm);
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                profileImage.src = data.image;
                messageDiv.textContent = 'Image uploaded successfully.';
            } else {
                profileImage.src = '<?php echo htmlspecialchars($state_data[$staff_id] ?? $default_image); ?>?t=' + new Date().getTime();
                messageDiv.textContent = data.message || 'Failed to upload image.';
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            profileImage.src = '<?php echo htmlspecialchars($state_data[$staff_id] ?? $default_image); ?>?t=' + new Date().getTime();
            messageDiv.textContent = 'Error uploading image.';
        });
    });

    removeImageBtn.addEventListener('click', function() {
        if (!confirm('Are you sure you want to remove the image?')) return;

        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'remove_image=1'
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                profileImage.src = data.image;
                imageUpload.value = '';
                messageDiv.textContent = 'Image removed successfully.';
            } else {
                profileImage.src = '<?php echo htmlspecialchars($state_data[$staff_id] ?? $default_image); ?>?t=' + new Date().getTime();
                messageDiv.textContent = data.message || 'Failed to remove image.';
            }
        })
        .catch(error => {
            console.error('Removal error:', error);
            profileImage.src = '<?php echo htmlspecialchars($state_data[$staff_id] ?? $default_image); ?>?t=' + new Date().getTime();
            messageDiv.textContent = 'Error removing image.';
        });
    });

    editForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            messageDiv.textContent = data.includes('success') ? 'Profile updated successfully.' : 'Failed to update profile.';
        })
        .catch(error => {
            console.error('Edit error:', error);
            messageDiv.textContent = 'Error updating profile.';
        });
    });
});
</script>

<style>
    body {
        margin: 0;
        padding: 0;
        margin-top: 0px;
        margin-bottom: 0px;
        height: calc(100vh - 60px);
        box-sizing: border-box;
    }
    #imageContainer {
        min-height: 300px;
        width: 300px;
    }
</style>