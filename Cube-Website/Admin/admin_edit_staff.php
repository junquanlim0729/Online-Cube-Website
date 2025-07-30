<?php
require_once 'dataconnection.php';

$messages = [];
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$staff_data = null;

if ($staff_id > 0) {
    $sql = "SELECT Staff_ID, Staff_Email, Staff_Status, Staff_Role FROM Staff WHERE Staff_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $staff_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit'])) {
    $email = trim($_POST['email']);
    $status = isset($_POST['status']) ? 1 : 0;
    $role = trim($_POST['role']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messages[] = "Invalid email format.";
    } else {
        $update_sql = "UPDATE Staff SET Staff_Email = ?, Staff_Status = ?, Staff_Role = ? WHERE Staff_ID = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "sisi", $email, $status, $role, $staff_id);
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
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container { max-width: 400px; margin: 0 auto; }
        h2 { text-align: center; }
        .message { text-align: center; color: #ff0000; }
        input, select { width: 100%; padding: 8px; margin: 5px 0; }
        button { width: 100%; padding: 8px; background: #007bff; color: white; border: none; border-radius: 3px; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit Staff</h2>
        <?php if (!empty($messages)): ?>
            <div class="message"><?php echo $messages[0]; ?></div>
        <?php endif; ?>

        <?php if ($staff_data): ?>
            <form method="POST" action="">
                <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff_data['Staff_ID']); ?>">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($staff_data['Staff_Email']); ?>" required><br>
                <label>Status</label>
                <select name="status">
                    <option value="1" <?php echo $staff_data['Staff_Status'] ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo !$staff_data['Staff_Status'] ? 'selected' : ''; ?>>Blocked</option>
                </select><br>
                <label>Role</label>
                <select name="role">
                    <option value="Admin" <?php echo $staff_data['Staff_Role'] === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="Super Admin" <?php echo $staff_data['Staff_Role'] === 'Super Admin' ? 'selected' : ''; ?>>Super Admin</option>
                </select><br>
                <button type="submit" name="submit_edit">Save Changes</button>
            </form>
        <?php else: ?>
            <p>Staff not found.</p>
        <?php endif; ?>

        <p><a href="?page=admin_manage_staff.php">Back to Manage Staff</a></p>
    </div>
</body>
</html>