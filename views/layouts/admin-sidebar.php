<?php
/**
 * Shared Admin Sidebar
 */
?>
<aside class="admin-sidebar">
    <div class="admin-sidebar__header">
        <div class="admin-sidebar__logo-wrap">
            <svg viewBox="0 0 48 48" width="24" height="24">
                <circle cx="24" cy="24" r="20" fill="var(--color-primary)"></circle>
                <circle cx="24" cy="24" r="7.5" fill="var(--color-bg-card)"></circle>
            </svg>
        </div>
        <span class="admin-sidebar__title">Admin Panel</span>
    </div>
    
    <nav class="admin-sidebar__nav">
        <a href="../../views/admin/dashboard.php" class="admin-nav__item" id="nav-dashboard">Dashboard</a>
        <a href="../../views/admin/products.php" class="admin-nav__item" id="nav-products">Products</a>
        <a href="../../views/admin/customers.php" class="admin-nav__item" id="nav-customers">Customers</a>
        <a href="../../views/admin/orders.php" class="admin-nav__item" id="nav-orders">Orders</a>
    </nav>
    
    <div class="admin-sidebar__footer">
        <button id="admin-logout-btn" class="btn btn--outline w-full">Logout</button>
    </div>
</aside>
<script>
    // Highlight active nav item
    const path = window.location.pathname;
    if (path.includes('dashboard.php')) document.getElementById('nav-dashboard').classList.add('active');
    else if (path.includes('products.php')) document.getElementById('nav-products').classList.add('active');
    else if (path.includes('customers.php')) document.getElementById('nav-customers').classList.add('active');
    else if (path.includes('orders.php')) document.getElementById('nav-orders').classList.add('active');

    document.getElementById('admin-logout-btn').addEventListener('click', async () => {
        if(window.Api && window.Api.auth) {
            await window.Api.auth.logout();
        } else {
            localStorage.removeItem('bmd_access_token');
            localStorage.removeItem('bmd_user');
        }
        window.location.href = '../../views/admin/admin-login.php';
    });
</script>
