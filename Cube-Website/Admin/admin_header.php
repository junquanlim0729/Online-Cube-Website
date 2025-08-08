<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Panel - CubePro Hub</title>
    <?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
      $role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
      $isSuperAdmin = ($role === 'super admin' || $role === 'superadmin');
      $headerBg = $isSuperAdmin ? '#6c757d' : '#007bff';
    ?>
    <style>
        header {
            background-color: <?php echo $headerBg; ?>;
            color: white;
            padding: 10px;
            text-align: center;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 2; /* Above sidebar */
            height: 60px; /* Fixed height for consistency */
        }
    </style>
</head>
<body>
    <header>
        <?php 
        $panelTitle = $isSuperAdmin ? 'Super Admin Panel' : 'Admin Panel';
        ?>
        <h1><?php echo htmlspecialchars($panelTitle); ?></h1>
    </header>
</body>
</html>