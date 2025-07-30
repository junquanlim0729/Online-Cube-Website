<?php
// session_start() is handled by admin_home.php
require_once 'dataconnection.php';

$messages = [];
$staff_name = '';
$staff_email = '';
$staff_status = 1; // Default to Active
$staff_role = 'Admin'; // Default to Admin

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_add'])) {
    $staff_name = trim($_POST['staff_name']);
    $staff_email = trim($_POST['staff_email']);
    $staff_status = isset($_POST['staff_status']) ? 1 : 0;
    $staff_role = trim($_POST['staff_role']);

    if (empty($staff_name) || empty($staff_email)) {
        $messages[] = "Name and email are required.";
    } elseif (!filter_var($staff_email, FILTER_VALIDATE_EMAIL)) {
        $messages[] = "Invalid email format.";
    } else {
        $check_sql = "SELECT Staff_ID FROM Staff WHERE Staff_Email = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $staff_email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $messages[] = "Email already exists.";
        } else {
            $insert_sql = "INSERT INTO Staff (Staff_Name, Staff_Email, Staff_Status, Staff_Role) VALUES (?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "ssis", $staff_name, $staff_email, $staff_status, $staff_role);
            if (mysqli_stmt_execute($insert_stmt)) {
                mysqli_stmt_close($insert_stmt);
                header("Location: ?page=admin_manage_staff.php&add=success");
                exit();
            } else {
                $messages[] = "Failed to add staff. Please try again.";
            }
        }
        mysqli_stmt_close($check_stmt);
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Staff</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .container { max-width: 400px; margin: 0 auto; }
        h2 { text-align: center; }
        .message { text-align: center; color: #ff0000; }
        input, select { width: 100%; padding: 8px; margin: 5px 0; }
        button { width: 100%; padding: 8px; background: #28a745; color: white; border: none; border-radius: 3px; }
        button:hover { background: #218838; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add Staff</h2>
        <?php if (!empty($messages)): ?>
            <div class="message"><?php echo $messages[0]; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label>Name</label>
            <input type="text" name="staff_name" value="<?php echo htmlspecialchars($staff_name); ?>" required><br>
            <label>Email</label>
            <input type="email" name="staff_email" value="<?php echo htmlspecialchars($staff_email); ?>" required><br>
            <label>Status</label>
            <select name="staff_status">
                <option value="1" <?php echo $staff_status ? 'selected' : ''; ?>>Active</option>
                <option value="0" <?php echo !$staff_status ? 'selected' : ''; ?>>Blocked</option>
            </select><br>
            <label>Role</label>
            <select name="staff_role">
                <option value="Admin" <?php echo $staff_role === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="Super Admin" <?php echo $staff_role === 'Super Admin' ? 'selected' : ''; ?>>Super Admin</option>
            </select><br>
            <button type="submit" name="submit_add">Add Staff</button>
        </form>

        <p><a href="?page=admin_manage_staff.php">Back to Manage Staff</a></p>
    </div>
</body>
</html>