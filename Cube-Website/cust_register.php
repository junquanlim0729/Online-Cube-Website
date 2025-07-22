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

    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $errors[] = "All required fields must be filled.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_errors[] = "Invalid email format.";
    } elseif (!preg_match('/@(gmail|hotmail|outlook|yahoo)\.com$/', $email)) {
        $email_errors[] = "Please use the correct Email format.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if ($phone && !preg_match('/^\d+$/', $phone)) {
        $phone_errors[] = "Phone number must contain only digits.";
    }

    $phone = !empty($phone) ? '+60' . $phone : null;

    $check_email = "SELECT Cust_ID FROM Customer WHERE Cust_Email = ?";
    $stmt = mysqli_prepare($conn, $check_email);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) {
        $email_errors[] = "Email is already registered.";
    }
    mysqli_stmt_close($stmt);

    if (empty($errors) && empty($email_errors) && empty($phone_errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $insert_query = "INSERT INTO Customer (Cust_First_Name, Cust_Last_Name, Cust_Email, Cust_Password, Cust_Phone, Created_At, Cust_Status) VALUES (?, ?, ?, ?, ?, NOW(), 1)";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "sssss", $first_name, $last_name, $email, $hashed_password, $phone);
        
        if (mysqli_stmt_execute($stmt)) {
            header("Location: cust_login.php?register=success");
            exit();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
        mysqli_stmt_close($stmt);
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
            <input type="tel" id="phone" name="phone" pattern="[0-9]*" inputmode="numeric" value="<?php echo isset($phone) && !empty($phone) ? htmlspecialchars(substr($phone, 3)) : ''; ?>">
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
        <label for="password">Password</label><br>
        <input type="password" id="password" name="password" required><br><br>
        <label for="confirm_password">Confirm Password</label><br>
        <input type="password" id="confirm_password" name="confirm_password" required><br><br>
        <button type="submit">Sign Up</button>
    </form>

    <p>Already have an account? <a href="cust_login.php">Go to Login</a></p>

    <script>
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

            if (!firstName || !lastName || !email || !password || !confirmPassword) {
                errors.push('All required fields must be filled.');
            }

            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                emailErrors.push('Please enter a valid email address.');
            } else if (!email.match(/@(gmail|hotmail|outlook|yahoo)\.com$/)) {
                emailErrors.push('Email must be from gmail.com, hotmail.com, outlook.com, or yahoo.com.');
            }

            if (phone && !/^\d+$/.test(phone)) {
                phoneErrors.push('Phone number must contain only digits.');
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

        // Restrict phone input to digits only
        document.getElementById('phone').addEventListener('input', function(event) {
            event.target.value = event.target.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>