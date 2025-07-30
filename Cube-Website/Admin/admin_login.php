<?php
session_start(); // Moved to top
require_once 'dataconnection.php';

$messages = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $messages[] = "All required fields must be filled.";
    }

    if (!empty($email)) {
        $check_login = "SELECT Staff_ID, Staff_Password, Staff_Status FROM Staff WHERE Staff_Email = ?";
        $stmt = mysqli_prepare($conn, $check_login);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $staff_id, $stored_password, $staff_status);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if ($staff_id) {
            if ($staff_status != 1) {
                $messages[] = "This Email is blocked, please contact the administration";
            } elseif ($password === $stored_password) {
                $_SESSION['Staff_ID'] = $staff_id;
                $_SESSION['role'] = 'admin';
                echo '<script>window.location.replace("admin_home.php?login=success");</script>';
                exit();
            } else {
                $messages[] = "Invalid Email or Password";
            }
        } else {
            $messages[] = "Invalid Email or Password";
        }
    }

    mysqli_close($conn);
} elseif (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $messages[] = "Successfully Logout";
    session_unset();
    session_destroy();
    header("Location: admin_login.php"); // Redirect after logout
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
</head>
<body>
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <h2>Login</h2>
        <?php
        if (!empty($messages)) {
            echo '<div style="color: ' . ($messages[0] === "Successfully Logout" ? "green" : "red") . ';">';
            foreach ($messages as $message) {
                echo "<p>$message</p>";
            }
            echo '</div>';
        }
        ?>
        <label>Email</label><br>
        <input type="email" name="email" required><br>
        <label>Password</label><br>
        <input type="password" name="password" required><br><br>
        <button type="submit">Login</button><br>
    </form>

    <p><a href="admin_forgot_pwd.php">Forgot Password?</a></p>
</body>
</html>