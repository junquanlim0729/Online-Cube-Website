<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['Cust_ID'])) {
    header('Location: cust_login.php');
    exit();
}
?>
<form>
  <h2 style="margin-top:0;">Change Password</h2>
  <label>Enter Current Password</label><br>
  <input type="password"><br><br>
  <button type="button">Verify</button><br><br>
  <label>New Password</label><br>
  <input type="password"><br><br>
  <label>Confirm New Password</label><br>
  <input type="password"><br><br>
  <button type="submit">Save Changes</button>
</form>