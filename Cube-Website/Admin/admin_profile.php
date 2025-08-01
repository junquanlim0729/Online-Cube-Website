<?php
require_once 'dataconnection.php';

$messages = [];
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['Staff_ID']) ? intval($_SESSION['Staff_ID']) : 0);
$staff_data = null;

// Load or initialize the image state JSON file
$state_file = 'profile_image_state.json';
$state_data = [];
if (file_exists($state_file)) {
    $json_content = file_get_contents($state_file);
    $state_data = json_decode($json_content, true) ?: [];
}

// Fetch or refresh staff data directly from database
if ($staff_id > 0) {
    $sql = "SELECT Staff_ID, Staff_Name, Staff_Email, Staff_Role, Staff_Password, Profile_Image FROM Staff WHERE Staff_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $staff_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Sync state with database if not set or modified
    if ($staff_data && (!isset($state_data[$staff_id]) || isset($_FILES['profile_image']) || isset($_POST['remove_image']))) {
        $state_data[$staff_id] = $staff_data['Profile_Image'] ?: 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png';
        file_put_contents($state_file, json_encode($state_data));
    }
}

// Handle profile image upload and update
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/';
    $image_name = $staff_data['Staff_Name'] . '_' . uniqid() . '.png';
    $upload_file = $upload_dir . $image_name;
    $image_tmp = $_FILES['profile_image']['tmp_name'];
    $image_size = getimagesize($image_tmp);

    if ($image_size && move_uploaded_file($image_tmp, $upload_file)) {
        $update_sql = "UPDATE Staff SET Profile_Image = ? WHERE Staff_ID = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $upload_file, $staff_id);
        $success = mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);

        if ($success) {
            $state_data[$staff_id] = $upload_file;
            file_put_contents($state_file, json_encode($state_data));
        } else {
            unlink($upload_file);
        }
    }
    // No headers, return plain text
    echo $success ? 'success' : 'fail';
    exit();
}

// Handle image removal
if (isset($_POST['remove_image'])) {
    if ($staff_data['Profile_Image'] && file_exists($staff_data['Profile_Image'])) {
        unlink($staff_data['Profile_Image']);
    }
    $update_sql = "UPDATE Staff SET Profile_Image = NULL WHERE Staff_ID = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "i", $staff_id);
    $success = mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);

    if ($success) {
        $state_data[$staff_id] = 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png';
        file_put_contents($state_file, json_encode($state_data));
    }
    // No headers, return plain text
    echo $success ? 'success' : 'fail';
    exit();
}

// Handle "Save Changes" form submission without headers
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit'])) {
    $name = trim($_POST['name']);
    $role = trim($_POST['role']);
    $password = !empty($_POST['password']) ? password_hash(trim($_POST['password']), PASSWORD_DEFAULT) : $staff_data['Staff_Password'];

    if (empty($messages)) {
        $update_sql = "UPDATE Staff SET Staff_Name = ?, Staff_Role = ?, Staff_Password = ? WHERE Staff_ID = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ssss", $name, $role, $password, $staff_id);
        if (mysqli_stmt_execute($update_stmt)) {
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $staff_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $staff_data = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            $messages[] = "Profile updated successfully.";
        } else {
            $messages[] = "Failed to update profile.";
        }
    }
    // No redirect, handle via JavaScript
}

mysqli_close($conn);
?>

<div style="margin-top: 20px; margin-bottom: 20px; max-height: calc(100vh - 60px - 40px); overflow-y: auto; position: relative;">
    <h2 style="position: absolute; top: 0; left: 0; margin: 0; padding: 10px 20px; color: #333; background: #f4f4f4; border-bottom: 1px solid #ccc;">My Profile</h2>
    <div style="display: flex; height: calc(100% - 40px); margin-top: 40px;">
        <div style="flex: 1; text-align: center; min-width: 150px; padding: 20px; box-sizing: border-box; border-right: 2px solid #ccc;">
            <?php if (!empty($messages)): ?>
                <div style="color: #ff0000; margin-bottom: 30px; text-align: center;" id="messageDiv"><?php echo $messages[0]; ?></div>
            <?php endif; ?>
            <?php if ($staff_data): ?>
                <form id="imageForm" enctype="multipart/form-data" style="display: flex; flex-direction: column; align-items: center; height: 100%; width: 100%;">
                    <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff_data['Staff_ID']); ?>">
                    <div style="width: 300px; height: 300px; border: 2px solid #ccc; border-radius: 5px; overflow: hidden; margin-bottom: 30px; display: flex; justify-content: center; align-items: center;" id="imageContainer">
                        <img id="profileImage" src="<?php echo $state_data[$staff_id] ? htmlspecialchars($state_data[$staff_id]) . '?v=' . time() : 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png?v=' . time(); ?>" alt="Profile Image" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                    </div>
                    <input type="file" name="profile_image" accept="image/png, image/jpeg" id="imageUpload" style="display: block; margin: 0 auto 15px; width: 90%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box;">
                    <button type="button" id="removeImageBtn" style="display: block; margin: 0 auto 30px; width: 90%; padding: 10px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">Remove Image</button>
                </form>
            <?php else: ?>
                <p style="text-align: center; color: #666; margin-top: 20px;">Staff not found.</p>
            <?php endif; ?>
        </div>
        <div style="flex: 2; padding: 20px; box-sizing: border-box;">
            <?php if ($staff_data): ?>
                <form method="POST" action="" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 25px;" id="editForm">
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
    const staffId = <?php echo $staff_id; ?>;

    // Initial load from state file
    function loadImage() {
        fetch('profile_image_state.json')
            .then(response => response.json())
            .then(data => {
                const currentPath = data[staffId] || 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png';
                profileImage.src = currentPath + '?v=' + Date.now();
            })
            .catch(error => {
                console.error('Error loading state:', error);
                profileImage.src = 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png?v=' + Date.now();
            });
    }

    loadImage();

    imageUpload.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                profileImage.src = e.target.result; // Show preview
            };
            reader.readAsDataURL(file);

            const formData = new FormData(imageForm);
            formData.append('profile_image', file);
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data === 'success') {
                    loadImage(); // Immediately update with new state
                } else {
                    console.error('Upload failed:', data);
                    loadImage(); // Revert on error
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loadImage(); // Revert on error
            });
        }
    });

    removeImageBtn.addEventListener('click', function() {
        if (confirm('Are you sure you want to remove the image?')) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'remove_image=1'
            })
            .then(response => response.text())
            .then(data => {
                if (data === 'success') {
                    loadImage(); // Immediately update with new state
                    imageUpload.value = ''; // Clear file input value
                } else {
                    console.error('Removal failed:', data);
                    loadImage(); // Revert on error
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loadImage(); // Revert on error
            });
        }
    });

    // Handle "Save Changes" form submission without headers
    editForm.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent default form submission
        const formData = new FormData(this);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            if (data.includes('success')) {
                messageDiv.textContent = "Profile updated successfully.";
            } else {
                messageDiv.textContent = "Failed to update profile.";
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messageDiv.textContent = "Error updating profile.";
        });
    });
});
</script>

<style>
    body {
        margin: 0;
        padding: 0;
        margin-top: 0px; /* Start from bottom of header (60px height) */
        margin-bottom: 0px; /* Remove bottom margin to extend to footer */
        height: calc(100vh - 60px); /* Extend to top of footer, header (60px) subtracted */
        box-sizing: border-box; /* Ensure padding/margins are included in height */
    }
    #imageContainer {
        min-height: 300px; /* Extended height */
        width: 300px; /* Extended width */
    }
</style>