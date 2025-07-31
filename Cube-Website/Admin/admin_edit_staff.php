<?php
require_once 'dataconnection.php';

$messages = [];
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$staff_data = null;

if ($staff_id > 0) {
    $sql = "SELECT Staff_ID, Staff_Name, Staff_Email, Staff_Role, Profile_Image FROM Staff WHERE Staff_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $staff_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Handle profile image upload and update
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $image_tmp = $_FILES['profile_image']['tmp_name'];
    $image_size = getimagesize($image_tmp);
    if ($image_size) {
        $image_data = base64_encode(file_get_contents($image_tmp));
        $update_sql = "UPDATE Staff SET Profile_Image = ?, Last_Profile_Update = NOW() WHERE Staff_ID = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "si", $image_data, $staff_id);
        if (mysqli_stmt_execute($update_stmt)) {
            mysqli_stmt_close($update_stmt);
            // Fetch updated staff data to reflect new image
            $sql = "SELECT Profile_Image FROM Staff WHERE Staff_ID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $staff_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $staff_data = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
        } else {
            $messages[] = "Failed to update profile image in database.";
        }
    } else {
        $messages[] = "Invalid image file.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit'])) {
    $name = trim($_POST['name']);
    $role = trim($_POST['role']);
    $password = !empty($_POST['password']) ? password_hash(trim($_POST['password']), PASSWORD_DEFAULT) : null;

    if (empty($messages)) {
        $update_sql = "UPDATE Staff SET Staff_Name = ?, Staff_Role = ?"
            . ($password ? ", Staff_Password = ?" : "")
            . " WHERE Staff_ID = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        if ($password) {
            mysqli_stmt_bind_param($update_stmt, "sssi", $name, $role, $password, $staff_id);
        } else {
            mysqli_stmt_bind_param($update_stmt, "ssi", $name, $role, $staff_id);
        }
        if (mysqli_stmt_execute($update_stmt)) {
            mysqli_stmt_close($update_stmt);
            header("Location: ?page=admin_manage_staff.php&update=success");
            exit();
        } else {
            $messages[] = "Failed to update staff. Please try again.";
        }
    }
}

mysqli_close($conn);
?>

<div style="margin-top: 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: stretch; max-height: calc(100vh - 60px - 40px); overflow-y: auto;">
    <div style="flex: 1; text-align: center; min-width: 150px; display: flex; flex-direction: column; justify-content: space-between; height: 100%; padding: 20px; box-sizing: border-box;">
        <h2 style="margin-bottom: 30px;">Edit Staff</h2>
        <?php if (!empty($messages)): ?>
            <div style="color: #ff0000; margin-bottom: 30px; text-align: center;"><?php echo $messages[0]; ?></div>
        <?php endif; ?>
        <?php if ($staff_data): ?>
            <form method="POST" action="" enctype="multipart/form-data" style="display: flex; flex-direction: column; align-items: center; height: 100%; width: 100%;">
                <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff_data['Staff_ID']); ?>">
                <div style="width: 100%; height: calc(70% - 40px); border: 2px solid #ccc; border-radius: 10px; overflow: hidden; display: flex; justify-content: center; align-items: center; margin-bottom: 30px;">
                    <img id="profileImage" src="<?php echo $staff_data['Profile_Image'] ? 'data:image/jpeg;base64,' . htmlspecialchars($staff_data['Profile_Image']) : 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png'; ?>" alt="Profile Image" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                </div>
                <input type="file" name="profile_image" accept="image/png, image/jpeg" id="imageUpload" style="display: block; margin: 0 auto 30px; width: 90%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box;">
            </form>
        <?php else: ?>
            <p style="text-align: center; color: #666; margin-top: 20px;">Staff not found.</p>
        <?php endif; ?>
    </div>
    <div style="flex: 2; padding: 20px; box-sizing: border-box;">
        <?php if ($staff_data): ?>
            <form method="POST" action="" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 25px;">
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
        <p style="text-align: center; margin-top: 30px;"><a href="?page=admin_manage_staff.php" style="color: #007bff; text-decoration: none; font-size: 16px;">Back to Manage Staff</a></p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const imageUpload = document.getElementById('imageUpload');
    const profileImage = document.getElementById('profileImage');

    imageUpload.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                profileImage.src = e.target.result;
                // Send image data to server for immediate update
                const formData = new FormData();
                formData.append('profile_image', file);
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                    }
                })
                .catch(error => console.error('Error:', error));
            };
            reader.readAsDataURL(file);
        }
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
</style>