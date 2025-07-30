<?php
require_once 'dataconnection.php';

$messages = [];
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$staff_data = null;

if ($staff_id > 0) {
    $sql = "SELECT Staff_ID, Staff_Email, Staff_Role FROM Staff WHERE Staff_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $staff_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit'])) {
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messages[] = "Invalid email format.";
    } else {
        $update_sql = "UPDATE Staff SET Staff_Email = ?, Staff_Role = ? WHERE Staff_ID = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "ssi", $email, $role, $staff_id);
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Staff</title>
    <style>
        .edit-staff-container {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .edit-staff-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        .edit-staff-container .message {
            text-align: center;
            color: #ff0000;
            margin-bottom: 15px;
        }
        .edit-staff-container .form-group {
            display: flex;
            margin-bottom: 15px;
        }
        .edit-staff-container .form-group label {
            flex: 1;
            font-weight: bold;
            color: #333;
            margin-right: 10px;
        }
        .edit-staff-container .form-group input,
        .edit-staff-container .form-group select {
            flex: 2;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        .edit-staff-container button {
            width: 100%;
            padding: 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .edit-staff-container button:hover {
            background: #0056b3;
        }
        .edit-staff-container a {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #007bff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="edit-staff-container">
        <h2>Edit Staff</h2>
        <?php if (!empty($messages)): ?>
            <div class="message"><?php echo $messages[0]; ?></div>
        <?php endif; ?>

        <?php if ($staff_data): ?>
            <form method="POST" action="">
                <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff_data['Staff_ID']); ?>">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($staff_data['Staff_Email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="Admin" <?php echo $staff_data['Staff_Role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="Super Admin" <?php echo $staff_data['Staff_Role'] === 'Super Admin' ? 'selected' : ''; ?>>Super Admin</option>
                    </select>
                </div>
                <button type="submit" name="submit_edit">Save Changes</button>
            </form>
        <?php else: ?>
            <p>Staff not found.</p>
        <?php endif; ?>

        <p><a href="?page=admin_manage_staff.php">Back to Manage Staff</a></p>
    </div>
</body>
</html>