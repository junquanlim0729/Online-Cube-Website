<?php
require_once 'dataconnection.php';

$current_staff_id = isset($_SESSION['Staff_ID']) ? $_SESSION['Staff_ID'] : 0;

// Base query to fetch all Admins/Super Admins
$sql = "SELECT Staff_ID, Staff_Name, Staff_Email, Staff_Status, Staff_Role FROM Staff WHERE Staff_Role IN ('Admin', 'Super Admin')";
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
        min-height: 100vh;
        box-sizing: border-box;
        background-color: #f0f0f0; /* Grey background behind the grid */
    }
    #adminGrid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        padding: 20px;
        background-color: transparent; /* Ensure grid background is transparent */
    }
    div[style*="margin-top: 20px; margin-bottom: 20px;"] {
        margin-top: 20px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    #searchInput {
        padding: 5px;
        border: 1px solid #ccc;
        border-radius: 3px;
        width: 200px;
    }
    a[href="?page=admin_add_staff.php"] {
        padding: 5px 10px;
        background: #28a745;
        color: white;
        text-decoration: none;
        border-radius: 3px;
    }
    div[style*="width: 100%; max-height: calc(100% - 60px);"] {
        width: 100%;
        max-height: calc(100% - 60px);
        overflow-y: auto;
        padding-bottom: 20px;
    }
    .admin-box {
        border: 1px solid #ccc;
        border-radius: 5px;
        padding: 15px;
        text-align: center;
        min-height: 300px;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        background-color: white; /* White background for admin boxes */
    }
    .admin-box div:first-child div {
        width: 150px;
        height: 150px;
        margin: 0 auto 15px;
        border: 2px solid #ccc;
        border-radius: 5px;
        overflow: hidden;
    }
    .admin-box div:first-child div img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .admin-box div:first-child span.label {
        font-weight: bold;
        color: #333;
        margin-right: 10px;
    }
    .admin-box div:first-child span.value {
        color: #555;
        text-align: right;
        flex-grow: 1;
    }
    .admin-box div:first-child span.value:nth-child(3) {
        word-break: break-all;
    }
    .admin-box div:last-child form {
        display: inline;
    }
    .admin-box div:last-child button {
        padding: 5px 10px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 3px;
        cursor: pointer;
    }
    .admin-box div:last-child button[form] {
        background: #28a745;
    }
    p[style*="grid-column"] {
        grid-column: 1 / -1;
        text-align: center;
        color: #666;
    }
</style>

<body>
<div style="margin-top: 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <input type="text" id="searchInput" placeholder="Search by name or email">
    <a href="?page=admin_add_staff.php">Add Staff</a>
</div>

<div style="width: 100%; max-height: calc(100% - 60px); overflow-y: auto; padding-bottom: 20px;">
    <div id="adminGrid">
        <?php if (empty($staff_list)): ?>
            <p>No Admins or Super Admins found.</p>
        <?php else: ?>
            <?php foreach ($staff_list as $staff): ?>
                <?php if ($staff['Staff_ID'] != $current_staff_id): ?>
                    <div class="admin-box" data-name="<?php echo htmlspecialchars(strtolower($staff['Staff_Name'] ?? $staff['Staff_Email'])); ?>" data-email="<?php echo htmlspecialchars(strtolower($staff['Staff_Email'])); ?>">
                        <div>
                            <div>
                                <img src="https://www.iconpacks.net/icons/2/free-user-icon-3296-thumb.png" alt="Staff Profile">
                            </div>
                            <span class="label">Name:</span><span class="value"><?php echo htmlspecialchars($staff['Staff_Name'] ?? $staff['Staff_Email']); ?></span><br>
                            <span class="label">Email:</span><span class="value"><?php echo htmlspecialchars($staff['Staff_Email']); ?></span><br>
                            <span class="label">Role:</span><span class="value"><?php echo htmlspecialchars($staff['Staff_Role']); ?></span>
                        </div>
                        <div>
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to <?php echo $staff['Staff_Status'] ? 'deactivate' : 'activate'; ?> this staff?');">
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
    const searchInput = document.getElementById('searchInput');
    const adminBoxes = document.querySelectorAll('.admin-box');

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