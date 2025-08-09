<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'dataconnection.php';

// Redirect to login if not logged in
if (!isset($_SESSION['Cust_ID'])) {
    header('Location: cust_login.php');
    exit();
}

$page = isset($_GET['page']) ? $_GET['page'] : 'cust_profile.php';
$valid_pages = ['cust_profile.php','cust_edit_profile.php','cust_address.php','cust_cart.php','cust_about.php','cust_contact.php','cust_purchase_history.php'];
if (!in_array($page, $valid_pages)) { $page = 'cust_profile.php'; }
?>

<?php include 'cust_header.php'; ?>

<div style="display:flex; margin:0; padding:0; box-sizing:border-box; min-height:calc(100vh - 60px);">
  <!-- Sidebar connects from bottom of header to above footer -->
  <aside style="width:240px; background:#f4f4f4; border-right:1px solid #e0e0e0; padding:12px; position:fixed; top:60px; left:0; bottom:40px; overflow-y:auto;">
    <h3 style="margin: 8px 0 10px 0; color:#333;">My Account</h3>
    <nav style="display:flex; flex-direction:column; gap:8px;">
      <a href="?page=cust_profile.php" style="text-decoration:none; color:#333; padding:8px 10px; border-radius:4px; background:<?php echo $page==='cust_profile.php'?'#e9ecef':'transparent'; ?>">My Profile</a>
      <a href="?page=cust_edit_profile.php" style="text-decoration:none; color:#333; padding:8px 10px; border-radius:4px; background:<?php echo $page==='cust_edit_profile.php'?'#e9ecef':'transparent'; ?>">Edit Profile</a>
      <a href="?page=cust_address.php" style="text-decoration:none; color:#333; padding:8px 10px; border-radius:4px; background:<?php echo $page==='cust_address.php'?'#e9ecef':'transparent'; ?>">Address</a>
      <a href="?page=cust_purchase_history.php" style="text-decoration:none; color:#333; padding:8px 10px; border-radius:4px; background:<?php echo $page==='cust_purchase_history.php'?'#e9ecef':'transparent'; ?>">Purchase History</a>
      <a href="#" onclick="showCustomerMenuLogoutConfirmation(event)" style="text-decoration:none; color:#dc3545; padding:8px 10px; border-radius:4px;"><img src="https://cdn-icons-png.flaticon.com/512/660/660350.png" alt="Logout" style="width:16px;height:16px;margin-right:6px;vertical-align:middle; filter: invert(24%) sepia(83%) saturate(5346%) hue-rotate(346deg) brightness(94%) contrast(101%);">Logout</a>
    </nav>
  </aside>

  <!-- Content area with left margin equal to sidebar width; account for footer height -->
  <section style="flex:1; background:#fff; margin-left:240px; padding:20px; min-height:calc(100vh - 60px - 40px); padding-top:80px;">
    <?php include $page; ?>
  </section>
</div>

<?php include 'cust_footer.php'; ?>

<script>
function showCustomerMenuLogoutConfirmation(event) {
    event.preventDefault();
    document.getElementById('customer-menu-logout-modal').style.display = 'block';
}

function confirmCustomerMenuLogout() {
    window.location.href = 'cust_logout.php';
}

function cancelCustomerMenuLogout() {
    document.getElementById('customer-menu-logout-modal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('customer-menu-logout-modal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<!-- Logout Confirmation Modal -->
<div id="customer-menu-logout-modal" style="display:none; position:fixed; z-index:1001; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
    <div style="background-color:#fff; margin:15% auto; padding:30px; border-radius:8px; width:500px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <h3 style="margin-top:0; margin-bottom:10px; font-size:20px; color:#333;">Confirm Logout</h3>
        <p style="font-size:16px; color:#555; margin-bottom:20px;">Are you sure you want to logout?</p>
        <button onclick="confirmCustomerMenuLogout()" style="padding:12px 24px; margin:0 8px; background-color:#dc3545; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:14px;">Yes</button>
        <button onclick="cancelCustomerMenuLogout()" style="padding:12px 24px; margin:0 8px; background-color:#6c757d; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:14px;">No</button>
    </div>
</div>