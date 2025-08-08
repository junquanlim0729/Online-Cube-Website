<?php if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>
<header style="background:#000; color:#fff; border-bottom:1px solid #333; padding:10px 20px; text-align:left;">
    <div style="max-width:1200px; margin:0 auto; display:flex; align-items:center; justify-content:space-between;">
        <h2 style="margin:0; color:#fff;">CubePro Hub</h2>
        <nav style="display:flex; gap:16px;">
            <a href="cust_about.php" style="color:#fff; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='#ff8c00'" onmouseout="this.style.color='#fff'">About Us</a>
            <a href="cust_contact.php" style="color:#fff; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='#ff8c00'" onmouseout="this.style.color='#fff'">Contact</a>
            <?php if (isset($_SESSION['Cust_ID'])): ?>
                <div style="position:relative; display:inline-block;">
                    <a href="#" onclick="toggleCustomerDropdown(event)" style="color:#fff; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='#ff8c00'" onmouseout="this.style.color='#fff'">My Account ▼</a>
                                         <div id="customer-dropdown" style="display:none; position:absolute; top:100%; right:0; background:#fff; border:1px solid #ddd; border-radius:4px; box-shadow:0 2px 8px rgba(0,0,0,0.1); min-width:150px; z-index:1000;">
                         <a href="cust_menu.php?page=cust_profile.php" style="display:block; padding:10px 15px; color:#333; text-decoration:none; border-bottom:1px solid #eee;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='#fff'">My Account</a>
                         <a href="cust_menu.php?page=cust_purchase_history.php" style="display:block; padding:10px 15px; color:#333; text-decoration:none; border-bottom:1px solid #eee;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='#fff'">My Purchase</a>
                         <a href="#" onclick="showCustomerLogoutConfirmation(event)" style="display:block; padding:10px 15px; color:#dc3545; text-decoration:none;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='#fff'">Logout</a>
                     </div>
                </div>
            <?php else: ?>
                <a href="cust_login.php" style="color:#fff; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='#ff8c00'" onmouseout="this.style.color='#fff'">Login</a>
            <?php endif; ?>
            <a href="cust_cart.php" style="color:#fff; text-decoration:none; transition:color 0.2s;" onmouseover="this.style.color='#ff8c00'" onmouseout="this.style.color='#fff'">Cart</a>
        </nav>
    </div>
    
    <script>
    function toggleCustomerDropdown(event) {
        event.preventDefault();
        const dropdown = document.getElementById('customer-dropdown');
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }
    
    function showCustomerLogoutConfirmation(event) {
        event.preventDefault();
        document.getElementById('customer-logout-modal').style.display = 'block';
    }
    
    function confirmCustomerLogout() {
        window.location.href = 'cust_logout.php';
    }
    
    function cancelCustomerLogout() {
        document.getElementById('customer-logout-modal').style.display = 'none';
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('customer-dropdown');
        const toggle = event.target.closest('a[onclick*="toggleCustomerDropdown"]');
        if (!toggle && dropdown && dropdown.style.display === 'block') {
            dropdown.style.display = 'none';
        }
    });
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('customer-logout-modal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
    </script>
    
    <!-- Logout Confirmation Modal -->
    <div id="customer-logout-modal" style="display:none; position:fixed; z-index:1001; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
        <div style="background-color:#fff; margin:15% auto; padding:30px; border-radius:8px; width:500px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
            <h3 style="margin-top:0; margin-bottom:10px; font-size:20px; color:#333;">Confirm Logout</h3>
            <p style="font-size:16px; color:#555; margin-bottom:20px;">Are you sure you want to logout?</p>
            <button onclick="confirmCustomerLogout()" style="padding:12px 24px; margin:0 8px; background-color:#dc3545; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:14px;">Yes</button>
            <button onclick="cancelCustomerLogout()" style="padding:12px 24px; margin:0 8px; background-color:#6c757d; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:14px;">No</button>
        </div>
    </div>
</header>