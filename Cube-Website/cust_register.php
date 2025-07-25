<?php
require_once 'dataconnection.php';

$errors = [];
$email_errors = [];
$phone_errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($password) || empty($confirm_password)) {
        $errors[] = "All required fields must be filled.";
        if (empty($phone)) {
            $phone_errors[] = "Phone number is required.";
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_errors[] = "Invalid email format.";
    } elseif (!preg_match('/@(gmail|hotmail|outlook|yahoo)\.com$/', $email)) {
        $email_errors[] = "Please use the correct Email format.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Password validation
    if ($password) {
        $length = strlen($password);
        if ($length < 8 || $length > 20) {
            $errors[] = "Please follow the password requirements.";
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = "Please follow the password requirements.";
        }
    }

    // Validate phone number format
    if ($phone) {
        $phone_digits = preg_replace('/[^0-9]/', '', $phone);
        $valid_prefixes = ['10', '11', '12', '13', '14', '16', '17', '18', '19'];
        $prefix = substr($phone_digits, 0, 2);
        $is011 = $prefix === '11';
        $digit_count = strlen($phone_digits);

        if (strlen($phone_digits) < 2 || !in_array($prefix, $valid_prefixes) || ($is011 && $digit_count !== 10) || (!$is011 && $digit_count !== 9)) {
            $phone_errors[] = "Incorrect phone number length.";
        } elseif ($is011 && $digit_count === 10) {
            $phone = '+6011' . substr($phone_digits, 2);
        } elseif (!$is011 && $digit_count === 9) {
            $phone = '+60' . $phone_digits;
        }
    }

    $check_email = "SELECT Cust_ID FROM Customer WHERE Cust_Email = ?";
    $stmt = mysqli_prepare($conn, $check_email);
    if ($stmt === false) {
        $errors[] = "Database preparation failed: " . mysqli_error($conn);
    } else {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $email_errors[] = "Email is already registered.";
        }
        mysqli_stmt_close($stmt);
    }

    if (empty($errors) && empty($email_errors) && empty($phone_errors)) {
        $insert_query = "INSERT INTO Customer (Cust_First_Name, Cust_Last_Name, Cust_Email, Cust_Password, Cust_Phone, Created_At, Cust_Status) VALUES (?, ?, ?, ?, ?, NOW(), 1)";
        $stmt = mysqli_prepare($conn, $insert_query);
        if ($stmt === false) {
            $errors[] = "Database preparation failed: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, "sssss", $first_name, $last_name, $email, $password, $phone); // Store original password
            if (mysqli_stmt_execute($stmt)) {
                header("Location: cust_login.php?register=success");
                exit();
            } else {
                $errors[] = "Registration failed: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }

    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Register</title>
    <style>
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
    <?php
    if (!empty($errors)) {
        echo '<div style="color: red;">';
        foreach ($errors as $error) {
            echo "<p>$error</p>";
        }
        echo '</div>';
    }
    ?>
    <form id="registerForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
        <h2>Register</h2>
        <label for="first_name">First Name</label>
        <input type="text" id="first_name" name="first_name" value="<?php echo isset($first_name) ? htmlspecialchars($first_name) : ''; ?>" required><br><br>
        <label for="last_name">Last Name</label>
        <input type="text" id="last_name" name="last_name" value="<?php echo isset($last_name) ? htmlspecialchars($last_name) : ''; ?>" required><br><br>
        <label for="email">Email</label><br>
        <input type="email" id="email" name="email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required><br>
        <?php
        if (!empty($email_errors)) {
            echo '<div style="color: red; font-size: 0.9em;">';
            foreach ($email_errors as $error) {
                echo "<p>$error</p>";
            }
            echo '</div>';
        }
        ?>
        <br>
        <label for="phone">Phone No.</label><br>
        <div>
            <span style="border: 1px solid #ccc; padding: 5px; margin-right: 5px;">+60</span>
            <input type="tel" id="phone" name="phone" pattern="[0-9- ]*" inputmode="numeric" value="<?php echo isset($phone) && !empty($phone) ? htmlspecialchars(substr($phone, 3)) : ''; ?>" required>
        </div>
        <?php
        if (!empty($phone_errors)) {
            echo '<div style="color: red; font-size: 0.9em;">';
            foreach ($phone_errors as $error) {
                echo "<p>$error</p>";
            }
            echo '</div>';
        }
        ?>
        <br>
        <label for="password">Password <a href="#" id="passwordTipsLink" style="margin-left: 10px; color: blue; text-decoration: underline;">Password Tips</a></label><br>
        <input type="password" id="password" name="password" maxlength="20" required><br>
        <div id="passwordTips" class="password-tips">
            <strong>Password Requirements:</strong><br>
            - Must be 8-20 characters long.<br>
            - Must include at least one uppercase letter.<br>
            - Must include at least one lowercase letter.<br>
            - Must include at least one number.<br>
            - Must include at least one special character (e.g., !@#$%^&*(),.?":{}|<>).
        </div><br>
        <label for="confirm_password">Confirm Password</label><br>
        <input type="password" id="confirm_password" name="confirm_password" maxlength="20" required><br><br>
        <button type="submit">Sign Up</button>
    </form>

    <p>Already have an account? <a href="cust_login.php">Go to Login</a></p>

    <script>
        function formatPhoneDisplay(phone) {
            const is011 = phone.startsWith('11');
            if (is011 && phone.length === 11) {
                return phone.slice(0, 2) + '-' + phone.slice(2, 6) + ' ' + phone.slice(6);
            } else if (!is011 && phone.length === 10) {
                return phone.slice(0, 2) + '-' + phone.slice(2, 5) + ' ' + phone.slice(5);
            }
            return phone;
        }

        document.getElementById('phone').addEventListener('input', function(event) {
            let value = event.target.value.replace(/[^0-9]/g, ''); // Remove non-digits
            const is011 = value.startsWith('11');
            const maxLength = is011 ? 10 : 9; // Max input digits after +60

            // Restrict first digit to 1
            if (value.length === 1 && value !== '1') {
                value = '';
            }

            // Restrict to maxLength digits
            if (value.length > maxLength) {
                value = value.slice(0, maxLength);
            }

            // Format with dashes
            if (is011 && value.length > 2) {
                if (value.length <= 6) {
                    value = value.slice(0, 2) + '-' + value.slice(2);
                } else {
                    value = value.slice(0, 2) + '-' + value.slice(2, 6) + ' ' + value.slice(6);
                }
            } else if (!is011 && value.length > 2) {
                if (value.length <= 5) {
                    value = value.slice(0, 2) + '-' + value.slice(2);
                } else {
                    value = value.slice(0, 2) + '-' + value.slice(2, 5) + ' ' + value.slice(5);
                }
            }

            event.target.value = value;
        });

        // Disable spaces in password
        document.getElementById('password').addEventListener('input', function(event) {
            let value = event.target.value.replace(/ /g, ''); // Remove spaces
            event.target.value = value;
        });

        document.getElementById('confirm_password').addEventListener('input', function(event) {
            let value = event.target.value.replace(/ /g, ''); // Remove spaces
            event.target.value = value;
        });

        // Toggle Password Tips tab
        document.getElementById('passwordTipsLink').addEventListener('click', function(event) {
            event.preventDefault();
            const tips = document.getElementById('passwordTips');
            tips.classList.toggle('show-tips');
        });

        document.getElementById('registerForm').addEventListener('submit', function(event) {
            let errors = [];
            let emailErrors = [];
            let phoneErrors = [];
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (!firstName || !lastName || !email || !phone || !password || !confirmPassword) {
                errors.push('All required fields must be filled.');
                if (!phone) {
                    phoneErrors.push('Phone number is required.');
                }
            }

            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                emailErrors.push('Please enter a valid email address.');
            } else if (!email.match(/@(gmail|hotmail|outlook|yahoo)\.com$/)) {
                emailErrors.push('Email must be from gmail.com, hotmail.com, outlook.com, or yahoo.com.');
            }

            if (phone) {
                const phoneDigits = phone.replace(/[^0-9]/g, '');
                const validPrefixes = ['10', '11', '12', '13', '14', '16', '17', '18', '19'];
                const prefix = phoneDigits.slice(0, 2);
                const is011 = prefix === '11';
                const digitCount = phoneDigits.length;

                if (phoneDigits.length < 2 || !validPrefixes.includes(prefix) || (is011 && digitCount !== 10) || (!is011 && digitCount !== 9)) {
                    phoneErrors.push('Incorrect phone number length.');
                } else if (is011 && digitCount === 10) {
                    phone = '+6011' + phoneDigits.slice(2);
                } else if (!is011 && digitCount === 9) {
                    phone = '+60' + phoneDigits;
                }
            }

            if (password) {
                const length = password.length;
                if (length < 8 || length > 20) {
                    errors.push('Please follow the password requirements.');
                } else if (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password) || !/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                    errors.push('Please follow the password requirements.');
                }
            }

            if (password !== confirmPassword) {
                errors.push('Passwords do not match.');
            }

            if (emailErrors.length > 0) {
                event.preventDefault();
                const emailErrorDiv = document.createElement('div');
                emailErrorDiv.style.color = 'red';
                emailErrorDiv.style.fontSize = '0.9em';
                emailErrorDiv.innerHTML = emailErrors.map(error => `<p>${error}</p>`).join('');
                const emailInput = document.getElementById('email');
                const existingEmailErrorDiv = emailInput.nextElementSibling;
                if (existingEmailErrorDiv && existingEmailErrorDiv.tagName === 'DIV') {
                    existingEmailErrorDiv.remove();
                }
                emailInput.parentNode.insertBefore(emailErrorDiv, emailInput.nextSibling);
            }

            if (phoneErrors.length > 0) {
                event.preventDefault();
                const phoneErrorDiv = document.createElement('div');
                phoneErrorDiv.style.color = 'red';
                phoneErrorDiv.style.fontSize = '0.9em';
                phoneErrorDiv.innerHTML = phoneErrors.map(error => `<p>${error}</p>`).join('');
                const phoneInput = document.getElementById('phone');
                const phoneContainer = phoneInput.parentNode;
                const existingPhoneErrorDiv = phoneContainer.nextElementSibling;
                if (existingPhoneErrorDiv && existingPhoneErrorDiv.tagName === 'DIV') {
                    existingPhoneErrorDiv.remove();
                }
                phoneContainer.parentNode.insertBefore(phoneErrorDiv, phoneContainer.nextSibling);
            }

            if (errors.length > 0) {
                event.preventDefault();
                alert(errors.join('\n'));
            }
        });
    </script>
</body>
</html>