<?php
require_once 'dataconnection.php';

$messages = [];
$email = '';
$show_otp_form = false;

// Handle email submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_email'])) {
    $email = trim($_POST['email']);
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messages[] = "Invalid email format.";
    } else {
        // Check if email exists
        $sql = "SELECT Cust_ID FROM Customer WHERE Cust_Email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            // Generate 6-digit OTP
            $otp = sprintf("%06d", mt_rand(0, 999999));
            
            // Update customer with OTP and expiry (2 minutes)
            $expiry = date('Y-m-d H:i:s', time() + 120);
            $update_sql = "UPDATE Customer SET Reset_Token = ?, Token_Expiry = ? WHERE Cust_Email = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "sss", $otp, $expiry, $email);
            if (mysqli_stmt_execute($update_stmt)) {
                // Send OTP via email using PHP mail()
                $to = $email;
                $subject = 'CubePro Hub - Password Reset OTP';
                $message = "Dear Customer,\n\n";
                $message .= "You have requested to reset your password for your CubePro Hub account.\n";
                $message .= "Your OTP for password reset is: $otp\n";
                $message .= "This code is valid for 2 minutes.\n\n";
                $message .= "If you did not request this, please ignore this email.\n\n";
                $message .= "Best regards,\nCubePro Hub Team";
                $headers = "From: CubePro Hub <cubeprohub@gmail.com>\r\n";
                $headers .= "Reply-To: cubeprohub@gmail.com\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                if (mail($to, $subject, $message, $headers)) {
                    $messages[] = "OTP has been sent to your email account.";
                    $show_otp_form = true;
                } else {
                    $error_info = error_get_last();
                    $error_message = $error_info ? $error_info['message'] : 'Unknown error';
                    $messages[] = "Failed to send OTP via email. For testing, your OTP is: $otp (This code is valid for 2 minutes.)";
                    $show_otp_form = true;
                    file_put_contents('email_errors.log', date('Y-m-d H:i:s') . " - Failed to send OTP email to $email: $error_message\n", FILE_APPEND);
                }
            } else {
                $messages[] = "Failed to generate OTP. Try again.";
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $messages[] = "No account found with this email.";
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle OTP verification
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_otp'])) {
    $entered_otp = trim($_POST['otp']);
    if (!ctype_digit($entered_otp) || strlen($entered_otp) !== 6) {
        $messages[] = "OTP must be a 6-digit number.";
        $show_otp_form = true;
    } else {
        $sql = "SELECT Cust_Email FROM Customer WHERE Cust_Email = ? AND Reset_Token = ? AND Token_Expiry > NOW()";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $email, $entered_otp);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_bind_result($stmt, $verified_email);
            mysqli_stmt_fetch($stmt);
            $clear_sql = "UPDATE Customer SET Reset_Token = NULL, Token_Expiry = NULL WHERE Cust_Email = ?";
            $clear_stmt = mysqli_prepare($conn, $clear_sql);
            mysqli_stmt_bind_param($clear_stmt, "s", $verified_email);
            mysqli_stmt_execute($clear_stmt);
            mysqli_stmt_close($clear_stmt);
            header("Location: cust_reset_pwd.php?email=" . urlencode($verified_email));
            exit();
        } else {
            $messages[] = "Invalid or expired OTP.";
            $show_otp_form = true;
        }
        mysqli_stmt_close($stmt);
    }
}

// Handle "Request OTP again" from verify OTP page
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_otp_again'])) {
    $email = trim($_POST['email']);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $messages[] = "Invalid email format.";
    } else {
        $sql = "SELECT Cust_ID FROM Customer WHERE Cust_Email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $otp = sprintf("%06d", mt_rand(0, 999999));
            $expiry = date('Y-m-d H:i:s', time() + 120);
            $update_sql = "UPDATE Customer SET Reset_Token = ?, Token_Expiry = ? WHERE Cust_Email = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param($update_stmt, "sss", $otp, $expiry, $email);
            if (mysqli_stmt_execute($update_stmt)) {
                $to = $email;
                $subject = 'CubePro Hub - Password Reset OTP';
                $message = "Dear Customer,\n\n";
                $message .= "You have requested to reset your password for your CubePro Hub account.\n";
                $message .= "Your new OTP for password reset is: $otp\n";
                $message .= "This code is valid for 2 minutes.\n\n";
                $message .= "If you did not request this, please ignore this email.\n\n";
                $message .= "Best regards,\nCubePro Hub Team";
                $headers = "From: CubePro Hub <cubeprohub@gmail.com>\r\n";
                $headers .= "Reply-To: cubeprohub@gmail.com\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                if (mail($to, $subject, $message, $headers)) {
                    $messages[] = "A new OTP has been sent to your email account.";
                    $show_otp_form = true;
                } else {
                    $error_info = error_get_last();
                    $error_message = $error_info ? $error_info['message'] : 'Unknown error';
                    $messages[] = "Failed to send OTP via email. For testing, your OTP is: $otp (This code is valid for 2 minutes.)";
                    $show_otp_form = true;
                    file_put_contents('email_errors.log', date('Y-m-d H:i:s') . " - Failed to send OTP email to $email: $error_message\n", FILE_APPEND);
                }
            } else {
                $messages[] = "Failed to generate OTP. Try again.";
            }
            mysqli_stmt_close($update_stmt);
        } else {
            $messages[] = "No account found with this email.";
        }
        mysqli_stmt_close($stmt);
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
            <div class="message <?php echo strpos($messages[0], "OTP has been sent") !== false || strpos($messages[0], "A new OTP") !== false ? 'success' : 'error'; ?>">
                <?php echo $messages[0]; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <?php if (!$show_otp_form): ?>
                <input type="email" name="email" placeholder="Enter your email address" required>
                <button type="submit" name="submit_email">Send OTP</button>
            <?php else: ?>
                <input type="text" name="otp" placeholder="Enter OTP" maxlength="6" required>
                <button type="submit" name="submit_otp">Verify OTP</button>
                <?php
                $sql = "SELECT Token_Expiry FROM Customer WHERE Cust_Email = ? LIMIT 1";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $token_expiry);
                mysqli_stmt_fetch($stmt);
                if ($token_expiry && strtotime($token_expiry) < time()) {
                    echo '<input type="hidden" name="email" value="' . htmlspecialchars($email) . '">';
                    echo '<button type="submit" name="request_otp_again">Request OTP again</button>';
                }
                mysqli_stmt_close($stmt);
                ?>
            <?php endif; ?>
        </form>
        
        <a href="cust_login.php">Back to Login</a>
    </div>
</body>
</html>