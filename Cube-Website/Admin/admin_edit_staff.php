<?php
require_once 'dataconnection.php';

$messages = [];
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$staff_data = null;

if ($staff_id > 0) {
    $sql = "SELECT Staff_ID, Staff_Name, Staff_Email, Staff_Role FROM Staff WHERE Staff_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $staff_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit'])) {
    $name = trim($_POST['name']);
    $role = trim($_POST['role']);
    $password = !empty($_POST['password']) ? password_hash(trim($_POST['password']), PASSWORD_DEFAULT) : null;

    // Handle profile image upload
    $profile_image = $staff_data['Staff_Name'] . '_profile.png'; // Unique filename based on Staff_Name
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/'; // Using uploads folder
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Create uploads folder if it doesn't exist
        }
        $upload_file = $upload_dir . $profile_image;
        $image_tmp = $_FILES['profile_image']['tmp_name'];
        $image_size = getimagesize($image_tmp);
        if ($image_size && move_uploaded_file($image_tmp, $upload_file)) {
            // Image uploaded successfully
        } else {
            $messages[] = "Failed to upload profile image.";
        }
    }

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

<div class="edit-staff-page">
    <div class="edit-staff-container">
        <h2>Edit Staff</h2>
        <?php if (!empty($messages)): ?>
            <div class="message"><?php echo $messages[0]; ?></div>
        <?php endif; ?>

        <?php if ($staff_data): ?>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff_data['Staff_ID']); ?>">
                <div class="left-section">
                    <div style="width: 150px; height: 150px; margin: 0 auto 15px; border: 2px solid #ccc; border-radius: 5px; overflow: hidden;">
                        <img src="uploads/<?php echo htmlspecialchars($staff_data['Staff_Name']); ?>_profile.png" alt="Profile Image" onerror="this.src='https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png';">
                    </div>
                    <input type="file" name="profile_image" accept="image/png, image/jpeg" style="display: block; margin: 0 auto; width: 100%; box-sizing: border-box;">
                </div>
                <div class="right-section">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="text" value="<?php echo htmlspecialchars($staff_data['Staff_Email']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($staff_data['Staff_Name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role">
                            <option value="Admin" <?php echo $staff_data['Staff_Role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="Super Admin" <?php echo $staff_data['Staff_Role'] === 'Super Admin' ? 'selected' : ''; ?>>Super Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Leave blank to keep current">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="Confirm new password">
                    </div>
                    <button type="submit" name="submit_edit">Save Changes</button>
                </div>
            </form>
        <?php else: ?>
            <p>Staff not found.</p>
        <?php endif; ?>

        <p><a href="?page=admin_manage_staff.php">Back to Manage Staff</a></p>
    </div>
</div>

<style>
    .edit-staff-page .edit-staff-container {
        font-family: Arial, sans-serif;
        width: 100%;
        padding: 20px;
        background: #fff;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        display: flex;
        gap: 20px;
    }
    .edit-staff-page .edit-staff-container h2 {
        text-align: center;
        margin-bottom: 20px;
        color: #333;
        width: 100%;
    }
    .edit-staff-page .edit-staff-container .message {
        text-align: center;
        color: #ff0000;
        margin-bottom: 15px;
        width: 100%;
    }
    .edit-staff-page .left-section {
        flex: 1;
        text-align: center;
        min-width: 150px;
    }
    .edit-staff-page .left-section img {
        width: 150px;
        height: 150px;
        object-fit: cover;
        border: 2px solid #ccc;
        border-radius: 5px;
    }
    .edit-staff-page .right-section {
        flex: 2;
        min-width: 0; /* Prevent overflow */
    }
    .edit-staff-page .right-section .form-group {
        display: flex;
        margin-bottom: 15px;
    }
    .edit-staff-page .right-section .form-group label {
        flex: 1;
        font-weight: bold;
        color: #333;
        margin-right: 10px;
    }
    .edit-staff-page .right-section .form-group input,
    .edit-staff-page .right-section .form-group select {
        flex: 2;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 3px;
        box-sizing: border-box;
    }
    .edit-staff-page .right-section .form-group input[readonly] {
        background-color: #f0f0f0;
        cursor: not-allowed;
    }
    .edit-staff-page .right-section button {
        width: 100%;
        padding: 10px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }
    .edit-staff-page .right-section button:hover {
        background: #0056b3;
    }
    .edit-staff-page .edit-staff-container a {
        display: block;
        text-align: center;
        margin-top: 15px;
        color: #007bff;
        text-decoration: none;
        width: 100%;
    }
</style>