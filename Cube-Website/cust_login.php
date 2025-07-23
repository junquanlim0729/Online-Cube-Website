<?php
require_once 'dataconnection.php';

$messages = [];
$email_errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $messages[] = "All required fields must be filled.";
    }

    if (!empty($email)) {
        $check_login = "SELECT Cust_ID, Cust_Password, Cust_Status FROM Customer WHERE Cust_Email = ?";
        $stmt = mysqli_prepare($conn, $check_login);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $cust_id, $stored_password, $cust_status);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if ($cust_id) {
            if ($cust_status != 1) {
                $messages[] = "This Email is blocked, please contact to the administration";
            } elseif ($password === $stored_password) {
                session_start();
                $_SESSION['Cust_ID'] = $cust_id;
                header("Location: cust_dashboard.php?login=success");
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
} elseif (isset($_GET['login']) && $_GET['login'] === 'failed') {
    $messages[] = "Invalid Email or Password";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Login</title>
</head>
<body>
    <form id="loginForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
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
        <label for="password">Password</label><br>
        <input type="password" id="password" name="password" required><br>
        <br><br>
        <button type="submit" id="loginButton">Login</button><br>
    </form>

    <p>Forgot Password? <a href="cust_forgot_pwd.html">Click here</a></p>
    <p><a href="cust_register.php">Create an account</a></p>

    <script>
        // Disable spaces in password
        document.getElementById('password').addEventListener('input', function(event) {
            let value = event.target.value.replace(/ /g, ''); // Remove spaces
            event.target.value = value;
            checkLoginButton();
        });

        function checkLoginButton() {
            const password = document.getElementById('password').value;
            const loginButton = document.getElementById('loginButton');
            const length = password.length;
            loginButton.disabled = length < 8 || length > 20;
        }

        document.getElementById('loginForm').addEventListener('submit', function(event) {
            let errors = [];
            let emailErrors = [];
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            if (!email || !password) {
                errors.push('All required fields must be filled.');
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

            if (errors.length > 0) {
                event.preventDefault();
                alert(errors.join('\n'));
            }
        });

        // Initial check on page load
        window.addEventListener('load', checkLoginButton);
    </script>
</body>
</html>