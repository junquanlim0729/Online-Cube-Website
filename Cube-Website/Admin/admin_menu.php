<ul style="list-style-type: none; padding: 0; margin: 0;">
    <li><a href="?page=admin_dashboard.php" style="display: block; margin-top: 20px; padding: 10px; text-decoration: none; color: #333; transition: background-color 0.3s;">Dashboard</a></li>
    <li><a href="?page=admin_profile.php" style="display: block; padding: 10px; text-decoration: none; color: #333; transition: background-color 0.3s;">My Profile</a></li>
    <li><a href="?page=admin_manage_staff.php" style="display: block; padding: 10px; text-decoration: none; color: #333; transition: background-color 0.3s;">Manage Staffs</a></li>
    <li><a href="?page=admin_manage_customer.php" style="display: block; padding: 10px; text-decoration: none; color: #333; transition: background-color 0.3s;">Manage Customers</a></li>

    <!-- Product dropdown -->
    <li style="position: relative;">
        <a href="#" class="menu-dropdown-toggle" style="display: block; padding: 10px; text-decoration: none; color: #333; transition: background-color 0.3s;">Products ▾</a>
        <ul class="submenu" style="list-style: none; padding-left: 0; margin: 0; display: none;">
            <li><a href="?page=admin_manage_category.php" style="display: block; padding: 10px 10px 10px 20px; text-decoration: none; color: #333; transition: background-color 0.3s;">Categories</a></li>
            <li><a href="?page=admin_manage_product.php" style="display: block; padding: 10px 10px 10px 20px; text-decoration: none; color: #333; transition: background-color 0.3s;">Products</a></li>
            <li><a href="?page=admin_manage_color.php" style="display: block; padding: 10px 10px 10px 20px; text-decoration: none; color: #333; transition: background-color 0.3s;">Colors</a></li>
        </ul>
    </li>

    <li><a href="?page=admin_manage_order.php" style="display: block; padding: 10px; text-decoration: none; color: #333; transition: background-color 0.3s;">Manage Orders</a></li>
    <li><a href="?page=admin_manage_report.php" style="display: block; padding: 10px; text-decoration: none; color: #333; transition: background-color 0.3s;">Generate Report</a></li>

    <!-- Keep Logout at the very bottom -->
    <li style="margin-top: 20px;"><a href="admin_logout.php" style="display: block; padding: 10px; text-decoration: none; color: #333; transition: background-color 0.3s;"><img src="https://cdn-icons-png.flaticon.com/512/660/660350.png" alt="Logout" style="width:16px;height:16px;margin-right:6px;vertical-align:middle;">Logout</a></li>
</ul>
<style>
    ul li a:hover { background-color: #ddd; }
    .menu-dropdown-toggle:hover + .submenu,
    .submenu:hover { display: block; }
</style>