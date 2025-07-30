<?php
require_once 'dataconnection.php';

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base query to fetch all Admins/Super Admins
$sql = "SELECT Staff_ID, Staff_Name, Staff_Email, Staff_Status, Staff_Role FROM Staff WHERE Staff_Role IN ('Admin', 'Super Admin')";
$params = [];
$types = '';

if (!empty($search_query)) {
    $sql .= " AND (Staff_Name LIKE ? OR Staff_Email LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params = [$search_param, $search_param];
    $types = "ss";
}

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$staff_list = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

mysqli_close($conn);
?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <form method="GET" action="" style="display: inline-block;">
        <input type="hidden" name="page" value="admin_manage_staff.php">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search by name or email" style="padding: 5px;">
        <button type="submit" style="padding: 5px 10px; background: #007bff; color: white; border: none; border-radius: 3px;">Search</button>
    </form>
    <a href="?page=admin_add_staff.php" style="padding: 5px 10px; background: #28a745; color: white; text-decoration: none; border-radius: 3px;">Add Staff</a>
</div>

<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
    <?php if (empty($staff_list)): ?>
        <p>No Admins or Super Admins found.</p>
    <?php else: ?>
        <?php foreach ($staff_list as $staff): ?>
            <div style="border: 1px solid #ccc; padding: 10px; border-radius: 5px; text-align: center;">
                <img src="images/staff_profile_<?php echo htmlspecialchars($staff['Staff_ID']); ?>.jpg" alt="Staff Profile" style="width: 100px; height: 100px; object-fit: cover; border-radius: 50%;" onerror="this.src='images/default_profile.jpg';">
                <h3>Name: <?php echo htmlspecialchars($staff['Staff_Name'] ?? $staff['Staff_Email']); ?></h3>
                <p>Email: <?php echo htmlspecialchars($staff['Staff_Email']); ?></p>
                <p>Role: <?php echo htmlspecialchars($staff['Staff_Role']); ?></p>
                <a href="?page=admin_edit_staff.php&id=<?php echo urlencode($staff['Staff_ID']); ?>" style="color: #007bff; text-decoration: none;">Edit</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>