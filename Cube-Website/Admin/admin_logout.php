<?php
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Pragma: no-cache");

// Check if the user is logged in
if (isset($_SESSION['Staff_ID'])) {
    // Unset all session variables
    $_SESSION = array();

    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();

    // Redirect to the login page with a success message
    header("Location: admin_login.php?logout=success");
    exit();
} else {
    // If the user is not logged in, redirect to the login page
    header("Location: admin_login.php");
    exit();
}
?>