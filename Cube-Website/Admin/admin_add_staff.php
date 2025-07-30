<?php
// session_start() is handled by admin_home.php
require_once 'dataconnection.php';

$messages = [];
$staff_name = '';
$staff_email = '';
$staff_role = 'Admin'; // Default to Admin

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_add'])) {
    $staff_name = trim($_POST['staff_name']);
    $staff_email = trim($_POST['staff_email']);
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
            $insert_sql = "INSERT INTO Staff (Staff_Name, Staff_Email, Staff_Role) VALUES (?, ?, ?)";
            $insert_stmt = mysqli_prepare($conn, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "sss", $staff_name, $staff_email, $staff_role);
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
        .add-staff-container {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .add-staff-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }
        .add-staff-container .message {
            text-align: center;
            color: #ff0000;
            margin-bottom: 15px;
        }
        .add-staff-container .form-group {
            display: flex;
            margin-bottom: 15px;
        }
        .add-staff-container .form-group label {
            flex: 1;
            font-weight: bold;
            color: #333;
            margin-right: 10px;
        }
        .add-staff-container .form-group input,
        .add-staff-container .form-group select {
            flex: 2;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        .add-staff-container button {
            width: 100%;
            padding: 10px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        .add-staff-container button:hover {
            background: #218838;
        }
        .add-staff-container a {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #007bff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="add-staff-container">
        <h2>Add Staff</h2>
        <?php if (!empty($messages)): ?>
            <div class="message"><?php echo $messages[0]; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="staff_name" value="<?php echo htmlspecialchars($staff_name); ?>" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="staff_email" value="<?php echo htmlspecialchars($staff_email); ?>" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="staff_role">
                    <option value="Admin" <?php echo $staff_role === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="Super Admin" <?php echo $staff_role === 'Super Admin' ? 'selected' : ''; ?>>Super Admin</option>
                </select>
            </div>
            <button type="submit" name="submit_add">Add Staff</button>
        </form>

        <p><a href="?page=admin_manage_staff.php">Back to Manage Staff</a></p>
    </div>
</body>
</html>