<?php
require_once 'dataconnection.php';

session_start();

$messages = [];
$email = '';
$show_otp_form = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['submit_email'])) {
        $email = trim($_POST['email']);
        if (empty($email)) {
            $messages[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $messages[] = "Invalid email format.";
        } else {
            $check_email = "SELECT Cust_ID FROM Customer WHERE Cust_Email = ?";
            $stmt = mysqli_prepare($conn, $check_email);
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $cust_id);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);

            if ($cust_id) {
                $otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $_SESSION['otp'] = $otp;
                $_SESSION['otp_timestamp'] = time();
                $_SESSION['otp_email'] = $email;

                $to = $email;
                $subject = "Your OTP for Password Reset";
                $message = "Dear Customer,\n\nYour One-Time Password (OTP) for password reset is: $otp\nThis OTP is valid for 2 minutes.\n\nRegards,\nYour Website Team";
                $headers = "From: no-reply@yourdomain.com";

                if (mail($to, $subject, $message, $headers)) {
                    $show_otp_form = true;
                    $messages[] = "An OTP has been sent to your email. It is valid for 2 minutes.";
                } else {
                    $messages[] = "Failed to send OTP. Please try again later.";
                }
            } else {
                $messages[] = "Email not found.";
            }
        }
    } elseif (isset($_POST['submit_otp'])) {
        $entered_otp = trim($_POST['otp']);
        if (empty($entered_otp)) {
            $messages[] = "OTP is required.";
        } elseif (strlen($entered_otp) != 6 || !ctype_digit($entered_otp)) {
            $messages[] = "Invalid OTP format. Please enter a 6-digit number.";
        } else {
            if (isset($_SESSION['otp']) && isset($_SESSION['otp_timestamp']) && isset($_SESSION['otp_email'])) {
                $otp_expiry = 120; // 2 minutes in seconds
                $current_time = time();
                $time_elapsed = $current_time - $_SESSION['otp_timestamp'];

                if ($time_elapsed > $otp_expiry) {
                    unset($_SESSION['otp']);
                    unset($_SESSION['otp_timestamp']);
                    unset($_SESSION['otp_email']);
                    $messages[] = "OTP has expired. Please request a new one.";
                } elseif ($_SESSION['otp'] === $entered_otp) {
                    header("Location: cust_reset_pwd.php?email=" . urlencode($_SESSION['otp_email']));
                    exit();
                } else {
                    $messages[] = "Invalid OTP. Please try again.";
                }
            } else {
                $messages[] = "Please request an OTP first.";
            }
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
    <title>Customer Forgot Password</title>
</head>
<body>
    <form id="forgotForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <h2>Forgot Password</h2>
        <?php
        if (!empty($messages)) {
            foreach ($messages as $message) {
                echo "<p>$message</p>";
            }
        }
        ?>
        <?php if (!$show_otp_form): ?>
            <label for="email">Enter your Email</label><br>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required><br><br>
            <button type="submit" name="submit_email">Send OTP</button>
        <?php else: ?>
            <label for="otp">Enter 6-digit OTP</label><br>
            <input type="number" id="otp" name="otp" maxlength="6" required><br>
            <button type="submit" name="submit_otp">Verify OTP</button>
            <?php
            if ($show_otp_form && isset($_SESSION['otp_timestamp'])) {
                $otp_expiry = 120; // 2 minutes
                $time_elapsed = time() - $_SESSION['otp_timestamp'];
                if ($time_elapsed > $otp_expiry) {
                    echo '<br><a href="#" id="requestAgain">Request OTP again</a>';
                }
            }
            ?>
        <?php endif; ?>
    </form>
    <p><a href="cust_login.php">Go Back</a></p>

    <script>
        document.getElementById('forgotForm').addEventListener('submit', function(event) {
            const otpInput = document.getElementById('otp');
            if (otpInput && otpInput.value.length > 6) {
                otpInput.value = otpInput.value.slice(0, 6);
            }
        });

        document.getElementById('requestAgain')?.addEventListener('click', function(event) {
            event.preventDefault();
            document.getElementById('forgotForm').reset();
            <?php $show_otp_form = false; unset($_SESSION['otp']); unset($_SESSION['otp_timestamp']); unset($_SESSION['otp_email']); ?>
        });
    </script>
</body>
</html>