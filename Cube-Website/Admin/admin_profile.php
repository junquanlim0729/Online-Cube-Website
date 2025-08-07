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
if (!file_exists($upload_dir)) {
    $upload_dir = __DIR__ . '/' . $upload_dir;
    if (!file_exists($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        error_log("Failed to create upload directory: $upload_dir");
        $messages[] = "Error: Could not create upload directory.";
    }
}
$error_messages = $config_data['error_messages'] ?? [];
$single_image_policy = $config_data['single_image_policy'] ?? true;
$cleanup_on_update = $config_data['cleanup_on_update'] ?? true;

// Ensure upload directory is writable
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

// Fetch staff data from database
if ($staff_id > 0) {
    $sql = "SELECT Staff_ID, Staff_Name, Staff_Email, Staff_Role, Staff_Password, Profile_Image FROM Staff WHERE Staff_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $staff_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$staff_data) {
        error_log("Staff data not found for staff_id: $staff_id");
    } else {
        error_log("Staff data loaded successfully for staff_id: $staff_id, has password: " . (!empty($staff_data['Staff_Password']) ? 'yes' : 'no'));
    }

    if ($staff_data) {
        $state_data[$staff_id] = $staff_data['Profile_Image'] ?: $default_image;
        file_put_contents($state_file, json_encode($state_data, JSON_PRETTY_PRINT));
    }
} else {
    error_log("Invalid staff_id: $staff_id");
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

        if ($single_image_policy && $cleanup_on_update && $staff_data['Profile_Image'] && file_exists($staff_data['Profile_Image']) && $staff_data['Profile_Image'] !== $default_image) {
            unlink($staff_data['Profile_Image']);
        }

        if (!move_uploaded_file($image_tmp, $upload_file)) {
            throw new Exception($error_messages['upload_failed'] ?? "Failed to upload the image. Check directory permissions or path: $upload_file");
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

        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'image' => $upload_file . '?t=' . time(), 'message' => 'Profile image updated successfully!']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Upload error for staff_id $staff_id: " . $e->getMessage());
        if (isset($upload_file) && file_exists($upload_file)) {
            unlink($upload_file);
        }
        $new_image = $staff_data['Profile_Image'] ?: $default_image;
        $state_data[$staff_id] = $new_image;
        file_put_contents($state_file, json_encode($state_data, JSON_PRETTY_PRINT));
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

        mysqli_commit($conn);
        echo json_encode(['status' => 'success', 'image' => $default_image . '?t=' . time(), 'message' => 'Profile image removed successfully!']);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Removal error for staff_id $staff_id: " . $e->getMessage());
        $new_image = $staff_data['Profile_Image'] ?: $default_image;
        $state_data[$staff_id] = $new_image;
        file_put_contents($state_file, json_encode($state_data, JSON_PRETTY_PRINT));
        echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'image' => $new_image . '?t=' . time()]);
    }
    exit();
}

// Handle password verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verify_password'])) {
    $verify_password = trim($_POST['verify_password']);
    // Always fetch the latest password from the database (plain text)
    $sql = "SELECT Staff_Password FROM Staff WHERE Staff_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $current_password = $row ? $row['Staff_Password'] : '';
    mysqli_stmt_close($stmt);

    if (empty($current_password)) {
        echo "Error: Staff data not found or password is empty.";
        exit();
    } else {
        if ($verify_password === $current_password) {
            echo "verification_success";
            exit();
        } else {
            echo "Current password is incorrect. Please try again.";
            exit();
        }
    }
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    // Always fetch the latest password from the database (plain text)
    $sql = "SELECT Staff_Password FROM Staff WHERE Staff_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $current_password = $row ? $row['Staff_Password'] : '';
    mysqli_stmt_close($stmt);

    if ($new_password !== $confirm_password) {
        $messages[] = "New password and confirm new password do not match.";
    } else if ($new_password === $current_password || $confirm_password === $current_password) {
        $messages[] = "New password and confirm new password cannot be the same as the current password.";
    } else {
        $length = strlen($new_password);
        if ($length < 8 || $length > 20) {
            $messages[] = "Password must be 8-20 characters long.";
        } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
            $messages[] = "Password must include uppercase, lowercase, number, and special character.";
        } else {
            $update_sql = "UPDATE Staff SET Staff_Password = ?, Last_Password_Change = NOW() WHERE Staff_ID = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "si", $new_password, $staff_id);
            if (mysqli_stmt_execute($update_stmt)) {
                $messages[] = "Password changed successfully.";
            } else {
                $messages[] = "Failed to update password: " . mysqli_error($conn);
            }
            mysqli_stmt_close($update_stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<div style="border-bottom: 2px solid #dee2e6; padding: 20px; margin-bottom: 20px;">
    <h1 style="margin: 0; color: #333; font-size: 28px; font-weight: bold;">My Profile</h1>
</div>
<div id="topMessageDiv" style="margin: 0 20px 20px 20px; display: none; position: relative;"></div>
<?php if (!empty($messages)): ?>
    <div id="phpErrorMessages" style="margin: 0 20px 20px 20px; color: #dc3545; font-weight: bold;">
        <?php foreach ($messages as $msg): ?>
            <div><?php echo htmlspecialchars($msg); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div style="margin-top: 20px; margin-bottom: 20px; position: relative;">
    <div style="display: flex;">
        <div style="flex: 1; text-align: center; min-width: 150px; padding: 20px; box-sizing: border-box; border-right: 2px solid #ccc;">
            <?php if ($staff_data): ?>
                <form id="imageForm" enctype="multipart/form-data" style="display: flex; flex-direction: column; align-items: center; height: 100%; width: 100%;">
                    <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff_data['Staff_ID']); ?>">
                    <div style="width: 300px; height: 300px; border: 2px solid #ccc; border-radius: 5px; overflow: hidden; margin-bottom: 30px; display: flex; justify-content: center; align-items: center;" id="imageContainer">
                        <img id="profileImage" src="<?php echo htmlspecialchars($state_data[$staff_id] ?? $default_image); ?>?t=<?php echo time(); ?>" alt="Profile Image" style="max-width: 100%; max-height: 100%; object-fit: contain;" onload="this.style.opacity=1;">
                    </div>
                    <input type="file" name="profile_image" accept="<?php echo implode(',', $allowed_types); ?>" id="imageUpload" style="display: block; margin: 0 auto 15px; width: 90%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box;">
                    <button type="button" id="removeImageBtn" style="display: block; margin: 0 auto 30px; width: 90%; padding: 10px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;" <?php echo ($state_data[$staff_id] ?? $default_image) === $default_image ? 'disabled' : ''; ?>>Remove Image</button>
                </form>
            <?php else: ?>
                <p style="text-align: center; color: #666; margin-top: 20px;">Staff not found.</p>
            <?php endif; ?>
        </div>

        <div style="flex: 2; padding: 20px; box-sizing: border-box;">
            <?php if ($staff_data): ?>
                <div id="profileDisplaySection">
                    <h3 style="margin-bottom: 20px; color: #333;">Profile Information</h3>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <div style="display: flex; align-items: center;">
                            <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Email</label>
                            <input type="text" value="<?php echo htmlspecialchars($staff_data['Staff_Email']); ?>" readonly style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; background-color: #f0f0f0; cursor: not-allowed; width: 100%;">
                        </div>
                        <div style="display: flex; align-items: center;">
                            <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Name</label>
                            <input type="text" value="<?php echo htmlspecialchars($staff_data['Staff_Name']); ?>" readonly style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; background-color: #f0f0f0; cursor: not-allowed; width: 100%;">
                        </div>
                        <div style="display: flex; align-items: center;">
                            <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Role</label>
                            <input type="text" value="<?php echo htmlspecialchars($staff_data['Staff_Role']); ?>" readonly style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; background-color: #f0f0f0; cursor: not-allowed; width: 100%;">
                        </div>
                    </div>
                </div>

                <div id="passwordChangeSection" style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #dee2e6; max-height: 400px; overflow-y: auto;">
                    <h3 style="margin-bottom: 20px; color: #333;">Change Password</h3>
                    
                    <div id="passwordVerifySection">
                        <form method="POST" action="" id="passwordVerifyForm" style="display: flex; flex-direction: column; gap: 15px;">
                            <div style="display: flex; align-items: center;">
                                <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Current Password</label>
                                <input type="password" name="verify_password" required style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; width: 100%;">
                            </div>
                            <div id="verifyPasswordError" style="color: #dc3545; font-size: 12px; margin-left: 33%; display: none;"></div>
                            <div style="margin-top: 20px;">
                                <button type="submit" name="verify_password" style="width: 100%; padding: 15px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Verify Password</button>
                            </div>
                        </form>
                    </div>
                    
                    <div id="passwordChangeFormSection" style="display: none;">
                        <form method="POST" action="" id="passwordForm" style="display: flex; flex-direction: column; gap: 15px;">
                            <div style="display: flex; align-items: center; position: relative;">
                                <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">New Password <a href="#" id="passwordTipsLink" style="margin-left: 10px; color: #007bff; text-decoration: underline; font-size: 12px;">Password Tips</a></label>
                                <input type="password" name="new_password" id="newPassword" maxlength="20" required style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; width: 100%;">
                                <div id="passwordTips" class="password-tips">
                                    <strong>Password Requirements:</strong><br>
                                    - Must be 8-20 characters long.<br>
                                    - Must include at least one uppercase letter.<br>
                                    - Must include at least one lowercase letter.<br>
                                    - Must include at least one number.<br>
                                    - Must include at least one special character (e.g., !@#$%^&*(),.?":{}|<>).
                                </div>
                            </div>
                            <div id="newPasswordError" style="color: #dc3545; font-size: 12px; margin-left: 33%; display: none;"></div>
                            
                            <div style="display: flex; align-items: center;">
                                <label style="flex: 1; font-weight: bold; color: #333; margin-right: 15px;">Confirm New Password</label>
                                <input type="password" name="confirm_password" required style="flex: 2; padding: 12px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; width: 100%;">
                            </div>
                            <div id="confirmPasswordError" style="color: #dc3545; font-size: 12px; margin-left: 33%; display: none;"></div>
                            
                            <div style="display: flex; gap: 10px; margin-top: 20px;">
                                <button type="submit" name="change_password" style="flex: 1; padding: 15px; background: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Update Password</button>
                                <button type="button" id="cancelPasswordBtn" style="flex: 1; padding: 15px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
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
    const topMessageDiv = document.getElementById('topMessageDiv');

    const currentImageSrc = profileImage.src;
    const defaultImageSrc = '<?php echo htmlspecialchars($default_image); ?>';
    if (currentImageSrc.includes(defaultImageSrc.split('/').pop())) {
        removeImageBtn.disabled = true;
        removeImageBtn.style.opacity = '0.5';
        removeImageBtn.style.cursor = 'not-allowed';
    }

    function showTopMessage(message, isSuccess = true) {
        topMessageDiv.style.display = 'none';
        topMessageDiv.style.opacity = '0';
        topMessageDiv.style.transform = 'translateY(20px)';
        
        topMessageDiv.textContent = message;
        topMessageDiv.style.cssText = isSuccess ? 
            'color: #28a745; font-weight: bold; font-size: 16px; padding: 10px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; transition: all 0.5s ease; width: 100%; box-sizing: border-box;' :
            'color: #dc3545; font-weight: bold; font-size: 16px; padding: 10px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; transition: all 0.5s ease; width: 100%; box-sizing: border-box;';
        
        topMessageDiv.style.display = 'block';
        setTimeout(() => {
            topMessageDiv.style.opacity = '1';
            topMessageDiv.style.transform = 'translateY(0)';
        }, 10);
        
        setTimeout(() => {
            topMessageDiv.style.opacity = '0';
            topMessageDiv.style.transform = 'translateY(-30px)';
            const leftSection = document.querySelector('div[style*="flex: 1"]');
            const rightSection = document.querySelector('div[style*="flex: 2"]');
            if (leftSection && rightSection) {
                leftSection.style.transition = 'transform 1s ease';
                rightSection.style.transition = 'transform 1s ease';
                leftSection.style.transform = 'translateY(-10px)';
                rightSection.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    leftSection.style.transform = 'translateY(0)';
                    rightSection.style.transform = 'translateY(0)';
                }, 100);
            }
            setTimeout(() => {
                topMessageDiv.style.display = 'none';
            }, 500);
        }, 3000);
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
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .then(data => {
            if (data.status === 'success') {
                updateImage(data.image);
                showTopMessage(data.message || 'Profile image updated successfully!', true);
                imageUpload.value = '';
                removeImageBtn.disabled = false;
                removeImageBtn.style.opacity = '1';
                removeImageBtn.style.cursor = 'pointer';
            } else {
                updateImage(data.image || '<?php echo htmlspecialchars($state_data[$staff_id] ?? $default_image); ?>?t=' + new Date().getTime());
                showTopMessage(data.message || 'Failed to upload image.', false);
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            showTopMessage('Profile image updated successfully!', true);
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        });
    });

    removeImageBtn.addEventListener('click', function() {
        if (removeImageBtn.disabled) return;

        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'remove_image=1'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON response: ' + text);
                }
            });
        })
        .then(data => {
            if (data.status === 'success') {
                updateImage(data.image);
                imageUpload.value = '';
                showTopMessage(data.message || 'Profile image removed successfully!', true);
                removeImageBtn.disabled = true;
                removeImageBtn.style.opacity = '0.5';
                removeImageBtn.style.cursor = 'not-allowed';
            } else {
                updateImage(data.image || '<?php echo htmlspecialchars($state_data[$staff_id] ?? $default_image); ?>?t=' + new Date().getTime());
                showTopMessage(data.message || 'Failed to remove image.', false);
            }
        })
        .catch(error => {
            console.error('Removal error:', error);
            showTopMessage('Profile image removed successfully!', true);
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        });
    });

    const passwordTipsLink = document.getElementById('passwordTipsLink');
    const passwordTips = document.getElementById('passwordTips');
    
    passwordTipsLink.addEventListener('click', function(event) {
        event.preventDefault();
        passwordTips.classList.toggle('show-tips');
    });

    document.addEventListener('click', function(event) {
        if (!passwordTipsLink.contains(event.target) && !passwordTips.contains(event.target)) {
            passwordTips.classList.remove('show-tips');
        }
    });

    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
    const verifyPasswordInput = document.querySelector('input[name="verify_password"]');

    [newPasswordInput, confirmPasswordInput, verifyPasswordInput].forEach(input => {
        if (input) {
            input.addEventListener('input', function(event) {
                let value = event.target.value.replace(/ /g, '');
                event.target.value = value;
            });
        }
    });

    let currentPasswordValue = '';
    verifyPasswordInput.addEventListener('input', function() {
        currentPasswordValue = this.value;
    });

    // The backend PHP already checks and displays the error after form submission.

    confirmPasswordInput.addEventListener('input', function() {
        const newPasswordValue = newPasswordInput.value;
        const confirmPasswordValue = this.value;
        if (newPasswordValue !== confirmPasswordValue && confirmPasswordValue !== '') {
            document.getElementById('confirmPasswordError').textContent = 'New password and confirm new password do not match.';
            document.getElementById('confirmPasswordError').style.display = 'block';
        } else {
            document.getElementById('confirmPasswordError').style.display = 'none';
        }
    });

    const passwordVerifyForm = document.getElementById('passwordVerifyForm');
    const passwordForm = document.getElementById('passwordForm');
    const passwordVerifySection = document.getElementById('passwordVerifySection');
    const passwordChangeFormSection = document.getElementById('passwordChangeFormSection');
    const verifyPasswordError = document.getElementById('verifyPasswordError');
    const newPasswordError = document.getElementById('newPasswordError');
    const confirmPasswordError = document.getElementById('confirmPasswordError');
    const cancelPasswordBtn = document.getElementById('cancelPasswordBtn');
    
    function clearPasswordErrors() {
        verifyPasswordError.style.display = 'none';
        verifyPasswordError.textContent = '';
        newPasswordError.style.display = 'none';
        newPasswordError.textContent = '';
        confirmPasswordError.style.display = 'none';
        confirmPasswordError.textContent = '';
    }
    
    passwordVerifyForm.addEventListener('submit', function(e) {
        e.preventDefault();
        clearPasswordErrors();
        const formData = new FormData(this);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.includes('verification_success')) {
                passwordVerifySection.style.display = 'none';
                passwordChangeFormSection.style.display = 'block';
                passwordVerifyForm.reset();
                clearPasswordErrors();
            } else if (data.includes('incorrect')) {
                verifyPasswordError.textContent = 'Current password is incorrect. Please try again.';
                verifyPasswordError.style.display = 'block';
            } else {
                showTopMessage('Failed to verify password.', false);
            }
        })
        .catch(error => {
            console.error('Password verification error:', error);
            showTopMessage('Error verifying password.', false);
        });
    });
    
    passwordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        clearPasswordErrors();
        
        const newPasswordValue = newPasswordInput.value;
        const confirmPasswordValue = confirmPasswordInput.value;
        
        if (newPasswordValue !== confirmPasswordValue) {
            confirmPasswordError.textContent = 'New password and confirm new password do not match.';
            confirmPasswordError.style.display = 'block';
            return;
        }
        
        const formData = new FormData(this);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.includes('success')) {
                showTopMessage('Password changed successfully!', true);
                passwordForm.reset();
                clearPasswordErrors();
                setTimeout(() => {
                    passwordChangeFormSection.style.display = 'none';
                    passwordVerifySection.style.display = 'block';
                }, 1500);
            } else {
                if (data.includes('cannot be the same')) {
                    newPasswordError.textContent = 'New password cannot be the same as the current password.';
                    newPasswordError.style.display = 'block';
                } else if (data.includes('8-20 characters')) {
                    newPasswordError.textContent = 'Password must be 8-20 characters long.';
                    newPasswordError.style.display = 'block';
                } else if (data.includes('uppercase, lowercase, number, and special character')) {
                    newPasswordError.textContent = 'Password must include uppercase, lowercase, number, and special character.';
                    newPasswordError.style.display = 'block';
                } else if (data.includes('do not match')) {
                    confirmPasswordError.textContent = 'New password and confirm new password do not match.';
                    confirmPasswordError.style.display = 'block';
                } else {
                    showTopMessage('Failed to change password. Please try again.', false);
                }
            }
        })
        .catch(error => {
            console.error('Password change error:', error);
            showTopMessage('Error changing password.', false);
        });
    });
    
    cancelPasswordBtn.addEventListener('click', function() {
        passwordForm.reset();
        clearPasswordErrors();
        passwordChangeFormSection.style.display = 'none';
        passwordVerifySection.style.display = 'block';
    });

    <?php if (!empty($messages)): ?>
    showTopMessage('<?php echo htmlspecialchars($messages[0]); ?>', <?php echo strpos($messages[0], 'success') !== false ? 'true' : 'false'; ?>);
    <?php endif; ?>
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
    #profileImage {
        transition: opacity 0.3s;
        opacity: 1;
    }
    #removeImageBtn:disabled {
        background-color: #6c757d !important;
        cursor: not-allowed !important;
        opacity: 0.5;
    }
    .password-tips {
        display: none;
        position: absolute;
        background-color: #f9f9f9;
        border: 1px solid #ccc;
        padding: 10px;
        z-index: 1;
        margin-top: 5px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        font-size: 12px;
        line-height: 1.4;
        max-width: 300px;
        opacity: 0;
        transform: translateY(-10px);
        transition: opacity 0.3s ease, transform 0.3s ease;
    }
    .show-tips {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }
</style>
</body>
</html>

<?php
// Close the connection only at the end
if (isset($conn) && is_object($conn)) {
    mysqli_close($conn);
}
?>