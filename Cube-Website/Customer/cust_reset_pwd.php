<?php
session_start();
require_once 'dataconnection.php';

$messages = [];
$new_password = '';
$confirm_password = '';

if (isset($_GET['email']) && !empty($_GET['email'])) {
    $email = urldecode($_GET['email']);

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_reset'])) {
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);

        if (empty($new_password) || empty($confirm_password)) {
            $messages[] = "Both fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $messages[] = "Passwords do not match.";
        } else {
            // Password validation
            $length = strlen($new_password);
            if ($length < 8 || $length > 20) {
                $messages[] = "Please follow the password requirements.";
            } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
                $messages[] = "Please follow the password requirements.";
            } else {
                // Check old password
                $check_sql = "SELECT Cust_Password FROM Customer WHERE Cust_Email = ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "s", $email);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_bind_result($check_stmt, $old_password);
                mysqli_stmt_fetch($check_stmt);
                mysqli_stmt_close($check_stmt);

                if ($old_password === $new_password) {
                    $messages[] = "New password cannot be the same as the old password.";
                } else {
                    // Update password in database
                    $update_sql = "UPDATE Customer SET Cust_Password = ? WHERE Cust_Email = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "ss", $new_password, $email);
                    if (mysqli_stmt_execute($update_stmt)) {
                        mysqli_stmt_close($update_stmt);
                        $_SESSION['password_reset'] = true; // Set flag after successful reset
                        echo '<script>window.location.replace("cust_login.php");</script>';
                        exit();
                    } else {
                        $messages[] = "Failed to update password. Please try again.";
                    }
                }
            }
        }
    }
} else {
    echo '<script>window.location.replace("cust_login.php");</script>';
    exit();
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f0f0f0;
        }
        .container {
            padding: 20px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 15px;
        }
        .message {
            text-align: center;
            margin-bottom: 10px;
        }
        .error { color: #ff0000; }
        input {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        button {
            width: 100%;
            padding: 8px;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        a {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .password-tips {
            display: none;
            position: absolute;
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            padding: 10px;
            z-index: 1;
            margin-top: 5px;
        }
        .show-tips {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        <?php if (!empty($messages)): ?>
            <div class="message error">
                <?php echo $messages[0]; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?email=' . urlencode($email); ?>">
            <label>New Password <a href="#" id="passwordTipsLink" style="margin-left: 10px; color: blue; text-decoration: underline;">Password Tips</a></label><br>
            <input type="password" name="new_password" value="<?php echo htmlspecialchars($new_password); ?>" maxlength="20" required><br><br>
            <div id="passwordTips" class="password-tips">
                <strong>Password Requirements:</strong><br>
                - Must be 8-20 characters long.<br>
                - Must include at least one uppercase letter.<br>
                - Must include at least one lowercase letter.<br>
                - Must include at least one number.<br>
                - Must include at least one special character (e.g., !@#$%^&*(),.?":{}|<>).
            </div>
            <label>Confirm New Password</label><br>
            <input type="password" name="confirm_password" value="<?php echo htmlspecialchars($confirm_password); ?>" maxlength="20" required><br>
            <button type="submit" name="submit_reset">Reset Password</button>
        </form>

        <p><a href="cust_login.php">Go Home</a></p>
    </div>

    <script>
        // Intercept back button and redirect to cust_login.php only if coming from reset or forgot flow
        window.onpopstate = function(event) {
            if (event.state && (event.state.page === "reset_pwd" || event.state.page === "forgot_pwd")) {
                window.location.replace("cust_login.php");
            }
        };

        // Replace current history entry with reset_pwd state
        if (window.history && window.history.replaceState) {
            window.history.replaceState({ page: "reset_pwd" }, "Reset Password", window.location.pathname);
        }

        // Prevent page caching on unload
        window.onunload = function() {};

        // Toggle Password Tips tab
        document.getElementById('passwordTipsLink').addEventListener('click', function(event) {
            event.preventDefault();
            const tips = document.getElementById('passwordTips');
            tips.classList.toggle('show-tips');
        });

        // Disable spaces in password
        document.querySelectorAll('input[type="password"]').forEach(input => {
            input.addEventListener('input', function(event) {
                let value = event.target.value.replace(/ /g, ''); // Remove spaces
                event.target.value = value;
            });
        });
    </script>
</body>
</html>