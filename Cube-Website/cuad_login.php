<?php
require_once 'dataconnection.php';

$messages = [];
$email_errors = [];
$showLoginForm = false;
$selectedRole = isset($_POST['role']) ? $_POST['role'] : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['next'])) {
        $selectedRole = $_POST['role'];
        $showLoginForm = true;
    } elseif (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $selectedRole = $_POST['role'];

        if (empty($email) || empty($password)) {
            $messages[] = "All required fields must be filled.";
        } else {
            if ($selectedRole === 'customer') {
                $check_login = "SELECT Cust_ID, Cust_Password, Cust_Status FROM Customer WHERE Cust_Email = ?";
                $stmt = mysqli_prepare($conn, $check_login);
                mysqli_stmt_bind_param($stmt, "s", $email);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $cust_id, $stored_password, $cust_status);
                mysqli_stmt_fetch($stmt);
                mysqli_stmt_close($stmt);

                if ($cust_id) {
                    if ($cust_status != 1) {
                        $messages[] = "This Email is blocked, please contact the administration";
                    } elseif ($password === $stored_password) {
                        session_start();
                        $_SESSION['Cust_ID'] = $cust_id;
                        $_SESSION['role'] = 'customer';
                        echo '<script>window.location.replace("cust_dashboard.php?login=success");</script>';
                        exit();
                    } else {
                        $messages[] = "Invalid Email or Password for Customer";
                    }
                } else {
                    $messages[] = "Invalid Email or Password for Customer";
                }
            } elseif ($selectedRole === 'admin') {
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
                        session_start();
                        $_SESSION['Staff_ID'] = $staff_id;
                        $_SESSION['role'] = 'admin';
                        echo '<script>window.location.replace("admin_home.php");</script>';
                        exit();
                    } else {
                        $messages[] = "Invalid Email or Password for Admin";
                    }
                } else {
                    $messages[] = "Invalid Email or Password for Admin";
                }
            }
        }
    }

    mysqli_close($conn);
} elseif (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $messages[] = "Successfully Logout";
    session_start();
    session_unset();
    session_destroy();
} elseif (isset($_GET['login']) && $_GET['login'] === 'failed') {
    $messages[] = "Invalid Email or Password";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 0 5px rgba(0, 0, 0, 0.1); }
        h2 { text-align: center; margin-bottom: 15px; }
        .message { text-align: center; margin-bottom: 10px; }
        .error { color: #ff0000; }
        .success { color: #008000; }
        select, input, button { width: 100%; padding: 8px; margin: 5px 0; border: 1px solid #ccc; border-radius: 3px; }
        button { background: #007bff; color: #fff; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Login</h2>
        <?php
        if (!empty($messages)) {
            echo '<div class="message ' . ($messages[0] === "Successfully Logout" ? "success" : "error") . '">';
            foreach ($messages as $message) {
                echo "<p>$message</p>";
            }
            echo '</div>';
        }
        ?>

        <form id="loginForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <?php if (!$showLoginForm): ?>
                <label for="role">Select Role:</label><br>
                <select id="role" name="role" required>
                    <option value="" <?php echo empty($selectedRole) ? 'selected' : ''; ?>>Select Role</option>
                    <option value="customer" <?php echo $selectedRole === 'customer' ? 'selected' : ''; ?>>Customer</option>
                    <option value="admin" <?php echo $selectedRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select><br>
                <button type="submit" name="next">Next</button>
            <?php else: ?>
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($selectedRole); ?>">
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
                ?><br>
                <label for="password">Password</label><br>
                <input type="password" id="password" name="password" required><br>
                <br><br>
                <button type="submit" name="login">Login</button><br>
            <?php endif; ?>
        </form>

        <?php if ($showLoginForm): ?>
            <p>Forgot Password? <a href="cust_forgot_pwd.php">Click here</a></p>
            <p><a href="cust_register.php">Create an account</a></p>
        <?php endif; ?>

        <script>
            // Intercept back button and redirect to cuad_login.php only if coming from password reset flow
            window.onpopstate = function(event) {
                if (event.state && (event.state.page === "forgot_pwd" || event.state.page === "reset_pwd")) {
                    window.location.replace("cuad_login.php");
                }
            };

            // Replace current history entry with cuad_login.php state
            if (window.history && window.history.replaceState) {
                window.history.replaceState({ page: "login" }, "Login", window.location.pathname);
            }

            // Prevent page caching on unload
            window.onunload = function() {};

            // Disable spaces in password
            document.getElementById('password')?.addEventListener('input', function(event) {
                let value = event.target.value.replace(/ /g, ''); // Remove spaces
                event.target.value = value;
                checkLoginButton();
            });

            function checkLoginButton() {
                const password = document.getElementById('password')?.value;
                const loginButton = document.getElementById('loginButton');
                if (password && loginButton) {
                    const length = password.length;
                    loginButton.disabled = length < 8 || length > 20;
                }
            }

            document.getElementById('loginForm')?.addEventListener('submit', function(event) {
                let errors = [];
                let emailErrors = [];
                const email = document.getElementById('email')?.value?.trim();
                const password = document.getElementById('password')?.value;

                if (email && password && !document.getElementById('role')) {
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
                }
            });

            // Initial check on page load
            window.addEventListener('load', function() {
                checkLoginButton();
                const roleSelect = document.getElementById('role');
                if (roleSelect && !roleSelect.value) {
                    document.querySelectorAll('input, button[name="login"]').forEach(el => el.classList.add('hidden'));
                }
            });
        </script>
    </div>
</body>
</html>