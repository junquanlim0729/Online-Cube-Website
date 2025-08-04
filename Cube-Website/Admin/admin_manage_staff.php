<?php
require_once 'dataconnection.php';

$current_staff_id = isset($_SESSION['Staff_ID']) ? $_SESSION['Staff_ID'] : 0;

// Load or initialize the image state JSON file
$state_file = 'profile_image_state.json';
$state_data = [];
if (file_exists($state_file)) {
    $state_content = file_get_contents($state_file);
    $state_data = json_decode($state_content, true) ?: [];
}

// Base query to fetch all Admins/Super Admins with profile images
$sql = "SELECT Staff_ID, Staff_Name, Staff_Email, Staff_Status, Staff_Role, Profile_Image FROM Staff WHERE Staff_Role IN ('Admin', 'Super Admin')";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$staff_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Handle status toggle
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $staff_id = intval($_POST['staff_id']);
    $sql = "SELECT Staff_Status FROM Staff WHERE Staff_ID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $staff_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $current_status = mysqli_fetch_assoc($result)['Staff_Status'] ?? 1;
    mysqli_stmt_close($stmt);

    $new_status = $current_status ? 0 : 1;
    $update_sql = "UPDATE Staff SET Staff_Status = ? WHERE Staff_ID = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "ii", $new_status, $staff_id);
    $success = mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);

    if ($success) {
        $redirect_url = "?page=admin_manage_staff.php";
        echo "<script>window.location.href = '$redirect_url';</script>";
    } else {
        error_log("Failed to update Staff_Status for Staff_ID: $staff_id");
    }
    exit();
}

// Close connection after all operations
mysqli_close($conn);
?>

<style>
    body {
        margin: 0;
        padding: 0;
        margin-top: 0px;
        margin-bottom: 0px;
        height: calc(100vh - 60px);
        box-sizing: border-box;
        background-color: white;
    }
    .ams-adminGrid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        padding: 20px;
        background-color: transparent;
    }
    .ams-container {
        margin-top: 20px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .ams-mainContainer {
        max-height: calc(78vh - 60px - 80px); /* Adjusted for header (60px) and footer (40px) */
        overflow-y: auto;
        padding-bottom: 10px;
        background-color: lightgrey;
        border: 1px solid #ccc;
        border-color: #000000;
        border-radius: 5px;
    }
    .ams-searchInput {
        padding: 12px; /* Increased padding for larger size */
        border: 1px solid #ccc;
        border-radius: 5px;
        width: 300px; /* Increased width for better usability */
    }
    a[href="?page=admin_add_staff.php"] {
        padding: 12px 20px; /* Increased padding for larger size */
        background: #28a745;
        color: white;
        text-decoration: none;
        border-radius: 5px;
    }
    .ams-adminBox {
        border: 1px solid #ccc;
        border-color: #005b6fff;
        border-radius: 5px;
        padding: 15px;
        text-align: center;
        min-height: 300px;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        background-color: white;
    }
    .ams-adminBox div:first-child div {
        width: 150px;
        height: 150px;
        margin: 0 auto 15px;
        border: 2px solid #ccc;
        border-radius: 5px;
        overflow: hidden;
    }
    .ams-adminBox div:first-child div img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .ams-adminBox div:first-child span.label {
        font-weight: bold;
        color: #333;
        text-align: left;
        display: inline-block;
        width: 50px;
    }
    .ams-adminBox div:first-child span.value {
        color: #555;
        text-align: right;
        display: inline-block;
        width: calc(100% - 60px);
    }
    .ams-adminBox div:last-child {
        margin-top: auto;
    }
    .ams-adminBox div:last-child form {
        display: inline;
    }
    .ams-adminBox div:last-child button {
        padding: 10px 20px; /* Increased padding for larger size */
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        width: 100%;
    }
    .ams-adminBox div:last-child button[form] {
        background: #28a745;
    }
    p[style*="grid-column"] {
        grid-column: 1 / -1;
        text-align: center;
        color: #666;
    }
    h1.amsheader {
        font-size: 24px;
        color: #333;
        margin: 10px 0px 10px 0px;
    }
    h2.amssubtitle {
        font-size: 16px;
        color: #666;
        margin: 10px 0px 10px 0px;
    }
</style>

<body>
<h1 class="amsheader">Admin Staff Management</h1>
<h2 class="amssubtitle">Manage and monitor all admin and super admin accounts</h2>
<div class="ams-container">
    <input type="text" id="ams-searchInput" placeholder="Search by name or email">
    <a href="?page=admin_add_staff.php">Add Staff</a>
</div>

<div class="ams-mainContainer">
    <div class="ams-adminGrid">
        <?php if (empty($staff_list)): ?>
            <p>No Admins or Super Admins found.</p>
        <?php else: ?>
            <?php foreach ($staff_list as $staff): ?>
                <?php if ($staff['Staff_ID'] != $current_staff_id): ?>
                    <div class="ams-adminBox" data-name="<?php echo htmlspecialchars(strtolower($staff['Staff_Name'] ?? $staff['Staff_Email'])); ?>" data-email="<?php echo htmlspecialchars(strtolower($staff['Staff_Email'])); ?>">
                        <div>
                            <div>
                                <img src="<?php echo htmlspecialchars($state_data[$staff['Staff_ID']] ?? $staff['Profile_Image'] ?? 'https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png'); ?>" alt="Staff Profile">
                            </div>
                            <span class="label">Name:</span><span class="value"><?php echo htmlspecialchars($staff['Staff_Name'] ?? $staff['Staff_Email']); ?></span><br>
                            <span class="label">Email:</span><span class="value"><?php echo htmlspecialchars($staff['Staff_Email']); ?></span><br>
                            <span class="label">Role:</span><span class="value"><?php echo htmlspecialchars($staff['Staff_Role']); ?></span>
                        </div>
                        <div>
                            <form method="POST" action="">
                                <input type="hidden" name="staff_id" value="<?php echo htmlspecialchars($staff['Staff_ID']); ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <button type="submit" style="background: <?php echo $staff['Staff_Status'] ? '#dc3545' : '#28a745'; ?>;">
                                    <?php echo $staff['Staff_Status'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('ams-searchInput');
    const adminBoxes = document.querySelectorAll('.ams-adminBox');

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        adminBoxes.forEach(box => {
            const name = box.getAttribute('data-name');
            const email = box.getAttribute('data-email');
            if (searchTerm === '' || name.includes(searchTerm) || email.includes(searchTerm)) {
                box.style.display = 'block';
            } else {
                box.style.display = 'none';
            }
        });
    });
});
</script>