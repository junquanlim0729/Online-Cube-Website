<?php
session_start();
require_once 'dataconnection.php';

$messages = [];
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$show_otp_form = false;

// Check if password was just reset, redirect if true
if (isset($_SESSION['password_reset']) && $_SESSION['password_reset']) {
    unset($_SESSION['password_reset']);
    echo '<script>window.location.replace("cust_login.php");</script>';
    exit();
}

date_default_timezone_set('Asia/Kuala_Lumpur');

// Invalidate OTP if returning from login
if (isset($_GET['clear_otp']) && !empty($email)) {
    $clear_sql = "UPDATE Customer SET Reset_Token = NULL, Token_Expiry = NULL WHERE Cust_Email = ?";
    $clear_stmt = mysqli_prepare($conn, $clear_sql);
    if ($clear_stmt !== false) {
        mysqli_stmt_bind_param($clear_stmt, "s", $email);
        mysqli_stmt_execute($clear_stmt);
        mysqli_stmt_close($clear_stmt);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_email'])) {
    $email = trim($_POST['email']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messages[] = "Invalid email format.";
    } else {
        $sql = "SELECT Cust_ID FROM Customer WHERE Cust_Email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt === false) {
            $messages[] = "Database error: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $otp = sprintf("%06d", mt_rand(0, 999999));
                $token_expiry = date('Y-m-d H:i:s', time() + 60);
                $update_sql = "UPDATE Customer SET Reset_Token = ?, Token_Expiry = ? WHERE Cust_Email = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                if ($update_stmt === false) {
                    $messages[] = "Database error: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($update_stmt, "sss", $otp, $token_expiry, $email);
                    if (mysqli_stmt_execute($update_stmt)) {
                        $to = $email;
                        $subject = 'CubePro Hub - Password Reset OTP';
                        $message = "Dear Customer,\n\nYour OTP is: $otp (Expires: $token_expiry)\n";
                        $message .= "This code is valid for 1 minute.\n\nIf you did not request this, please ignore this email.\n\nBest regards,\nCubePro Hub Team";
                        $headers = "From: CubePro Hub <cubeprohub@gmail.com>\r\n";
                        $headers .= "Reply-To: cubeprohub@gmail.com\r\n";
                        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                        if (mail($to, $subject, $message, $headers)) {
                            $messages[] = "OTP has been sent to your email account.";
                            $show_otp_form = true;
                        } else {
                            $messages[] = "Failed to send OTP via email. For testing, your OTP is: $otp (Expires: $token_expiry)";
                        }
                    } else {
                        $messages[] = "Failed to generate OTP: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($update_stmt);
                }
            } else {
                $messages[] = "No account found with this email.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_otp'])) {
    $email = trim($_POST['email']);
    $entered_otp = trim($_POST['otp']);
    if (!ctype_digit($entered_otp) || strlen($entered_otp) !== 6) {
        $messages[] = "OTP must be a 6-digit number.";
        $show_otp_form = true;
    } else {
        $sql = "SELECT Cust_Email FROM Customer WHERE Cust_Email = ? AND Reset_Token = ? AND Token_Expiry > NOW()";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt === false) {
            $messages[] = "Database error: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, "ss", $email, $entered_otp);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                mysqli_stmt_bind_result($stmt, $verified_email);
                mysqli_stmt_fetch($stmt);
                $clear_sql = "UPDATE Customer SET Reset_Token = NULL, Token_Expiry = NULL WHERE Cust_Email = ?";
                $clear_stmt = mysqli_prepare($conn, $clear_sql);
                if ($clear_stmt === false) {
                    $messages[] = "Database error: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($clear_stmt, "s", $verified_email);
                    mysqli_stmt_execute($clear_stmt);
                    mysqli_stmt_close($clear_stmt);
                    echo '<script>window.location.replace("cust_reset_pwd.php?email=' . urlencode($verified_email) . '");</script>';
                    exit();
                }
            } else {
                $messages[] = "Invalid or expired OTP.";
                $show_otp_form = true;
            }
            mysqli_stmt_close($stmt);
        }
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CubePro Hub - Forgot Password</title>
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
        .success { color: #008000; }
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
        button:disabled {
            background: #cccccc;
            cursor: not-allowed;
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
    </style>
</head>
<body>
    <div class="container">
        <h2>Forgot Password</h2>
        <?php if (!empty($messages)): ?>
            <div class="message <?php echo strpos($messages[0], "OTP has been sent") !== false ? 'success' : 'error'; ?>">
                <?php echo $messages[0]; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <?php if (!$show_otp_form): ?>
                <input type="email" name="email" placeholder="Enter your email address" value="<?php echo htmlspecialchars($email); ?>" required>
                <button type="submit" name="submit_email">Send OTP</button>
            <?php else: ?>
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="text" name="otp" placeholder="Enter OTP" maxlength="6" required>
                <button type="submit" name="submit_otp">Verify OTP</button>
            <?php endif; ?>
        </form>
        
        <a href="cust_login.php?clear_otp=1&email=<?php echo urlencode($email); ?>" id="backToLogin">Back to Login</a>
    </div>

    <script>
        // Intercept back button and redirect to cust_login.php only if coming from reset flow
        window.onpopstate = function(event) {
            if (event.state && (event.state.page === "reset_pwd" || event.state.page === "forgot_pwd")) {
                window.location.replace("cust_login.php");
            }
        };

        // Replace current history entry with forgot_pwd state
        if (window.history && window.history.replaceState) {
            window.history.replaceState({ page: "forgot_pwd" }, "Forgot Password", window.location.pathname);
        }

        // Prevent page caching on unload
        window.onunload = function() {};
    </script>
</body>
</html>