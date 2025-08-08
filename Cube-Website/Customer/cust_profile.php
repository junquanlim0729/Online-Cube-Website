<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['Cust_ID'])) {
    header('Location: cust_login.php');
    exit();
}

require_once 'dataconnection.php';

$customer_id = $_SESSION['Cust_ID'];

// Fetch customer data from database
$sql = "SELECT Cust_ID, Cust_First_Name, Cust_Last_Name, Cust_Email, Cust_Phone FROM Customer WHERE Cust_ID = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer_data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
?>
<div>
    <h2 style="margin-top:0;">My Profile</h2>
    <div style="display:flex; gap:16px;">
        <div style="min-width:200px;">
            <h5>Name</h5>
            <h5>Email</h5>
            <h5>Phone No.</h5>
            <h5>Address</h5>
        </div>
        <div style="flex:1; color:#555;">
            <p><?php echo htmlspecialchars($customer_data['Cust_First_Name'] . ' ' . $customer_data['Cust_Last_Name']); ?></p>
            <p><?php echo htmlspecialchars($customer_data['Cust_Email']); ?></p>
            <p><?php echo htmlspecialchars($customer_data['Cust_Phone'] ?? 'Not provided'); ?></p>
            <p>Address management coming soon</p>
        </div>
    </div>
</div>