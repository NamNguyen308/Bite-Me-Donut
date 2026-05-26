<?php
/**
 * Customer Orders Page
 * Requires: user must be logged in (access_token handled by JS)
 * API: GET /api/orders          — list all orders for current user
 *      GET /api/orders/{id}     — order detail (used inline via dropdown)
 *      GET /api/users/me        — load sidebar user info
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Orders — Bite-Me-Donut</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../public/assets/css/root.css" />
  <!-- Reuse the exact same sidebar styles from profile -->
  <link rel="stylesheet" href="../../public/assets/css/customer_profile.css" />
  <link rel="stylesheet" href="../../public/assets/css/customer_orders.css" />
</head>
<body>

  <?php include __DIR__ . '/../layouts/header.php'; ?>

  <!-- ══════════════════════════════════════════════
       MAIN LAYOUT  (same flex wrapper as profile)
  ══════════════════════════════════════════════ -->
  <div class="profile-layout">

    <!-- ── SIDEBAR (identical structure to profile) ── -->
    <aside class="profile-sidebar" id="profileSidebar">

      <div class="sidebar-avatar-block">
        <div class="sidebar-avatar-ring">
          <img
            src="../../public/assets/img/user.jpg"
            alt="User avatar"
            class="sidebar-avatar-img"
            id="sidebarAvatar"
            onerror="this.src='../../public/assets/img/user-default.svg'"
          />
        </div>
        <p class="sidebar-avatar-name" id="sidebarName">—</p>
        <span class="badge badge--primary sidebar-role" id="sidebarRole">Customer</span>
      </div>

      <nav class="sidebar-nav">

        <!-- My Profile -->
        <a href="../../views/user/customer_profile.php" class="sidebar-nav__item" data-page="profile">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </span>
          <span class="sidebar-nav__label">My Profile</span>
        </a>

        <!-- My Orders — active -->
        <a href="../../views/user/customer_orders.php" class="sidebar-nav__item active" data-page="orders">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
              <line x1="3" y1="6" x2="21" y2="6"/>
              <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
          </span>
          <span class="sidebar-nav__label">My Orders</span>
        </a>

        <!-- Change Password -->
        <a href="../../views/auth/change_password.php" class="sidebar-nav__item" data-page="change-password">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>
          <span class="sidebar-nav__label">Change Password</span>
        </a>

        <div class="sidebar-nav__divider"></div>

        <!-- Logout -->
        <button class="sidebar-nav__item sidebar-nav__item--logout" id="sidebarLogoutBtn">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </span>
          <span class="sidebar-nav__label">Logout</span>
        </button>

      </nav>
    </aside><!-- /.profile-sidebar -->

    <!-- ── MAIN CONTENT ── -->
    <main class="profile-main orders-main">

      <!-- Loading -->
      <div class="loading-overlay" id="pageLoader">
        <div class="spinner"></div>
      </div>

      <!-- Auth error -->
      <div class="profile-error hidden" id="profileError">
        <div class="alert alert--danger" id="profileErrorMsg">
          Unable to load orders. Please log in again.
        </div>
        <a href="../../views/auth/login.php" class="btn btn--primary" style="margin-top: var(--space-4);">Log in</a>
      </div>

      <!-- Orders Content -->
      <div class="orders-content hidden" id="ordersContent">

        <!-- ── PAGE HEADER ── -->
        <div class="orders-page-header">
          <div class="orders-page-header__left">
            <div class="orders-page-header__icon">
              <!-- Shopping bag icon -->
              <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24"
                   fill="none" stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
              </svg>
            </div>
            <div>
              <h1 class="orders-page-header__title">Order History</h1>
              <p class="orders-page-header__subtitle">Track and review all your past purchases</p>
            </div>
          </div>

          <!-- Summary chips -->
          <div class="orders-summary-chips" id="ordersSummaryChips">
            <div class="summary-chip">
              <span class="summary-chip__value" id="chipTotal">—</span>
              <span class="summary-chip__label">Total Orders</span>
            </div>
            <div class="summary-chip summary-chip--pink">
              <span class="summary-chip__value" id="chipSpent">—</span>
              <span class="summary-chip__label">Total Spent</span>
            </div>
          </div>
        </div><!-- /.orders-page-header -->

        <!-- ── FILTER BAR ── -->
        <div class="orders-filter-bar">
          <div class="orders-search-wrap">
            <span class="orders-search-wrap__icon">
              <!-- Search icon -->
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                   fill="none" stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
              </svg>
            </span>
            <input
              type="text"
              class="orders-search-input"
              id="ordersSearch"
              placeholder="Search by order ID or note…"
              autocomplete="off"
            />
          </div>

          <div class="orders-status-tabs" id="ordersStatusTabs">
            <button class="status-tab active" data-status="all">All</button>
            <button class="status-tab" data-status="pending">Pending</button>
            <button class="status-tab" data-status="processing">Processing</button>
            <button class="status-tab" data-status="shipping">Shipping</button>
            <button class="status-tab" data-status="completed">Completed</button>
            <button class="status-tab" data-status="cancelled">Cancelled</button>
          </div>
        </div><!-- /.orders-filter-bar -->

        <!-- ── ORDER LIST ── -->
        <div class="orders-list" id="ordersList">
          <!-- Populated by JS -->
        </div>

        <!-- ── EMPTY STATE ── -->
        <div class="orders-empty hidden" id="ordersEmpty">
          <div class="orders-empty__illustration">
            <!-- Empty box / bag icon -->
            <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="1.2"
                 stroke-linecap="round" stroke-linejoin="round"
                 style="opacity:0.25;">
              <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
              <line x1="3" y1="6" x2="21" y2="6"/>
              <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
          </div>
          <h3 class="orders-empty__title" id="ordersEmptyTitle">No orders found</h3>
          <p class="orders-empty__desc" id="ordersEmptyDesc">You haven't placed any orders yet. Start shopping!</p>
          <a href="../../views/products/index.php" class="btn btn--primary">
            <!-- Store icon -->
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Browse Products
          </a>
        </div>

      </div><!-- /#ordersContent -->

    </main><!-- /.orders-main -->

  </div><!-- /.profile-layout -->

  <!-- ══════════════════════════════════════════════
       LOGOUT MODAL  (same as profile page)
  ══════════════════════════════════════════════ -->
  <div class="modal-overlay" id="logoutModal">
    <div class="modal">
      <div class="modal__header">
        <h3 class="modal__title">Logout</h3>
        <button class="modal__close" id="logoutModalClose">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2.5"
               stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>
      <p style="margin-bottom: var(--space-6); color: var(--color-text-muted); line-height: 1.7;">
        Are you sure you want to logout?<br/>Your token will be revoked immediately.
      </p>
      <div class="flex gap-4">
        <button class="btn btn--danger w-full" id="confirmLogoutBtn">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2.5"
               stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
          Logout
        </button>
        <button class="btn btn--ghost w-full" id="cancelLogoutBtn">Cancel</button>
      </div>
    </div>
  </div>

  <!-- TOAST -->
  <div class="toast-container" id="toastContainer"></div>

  <?php include __DIR__ . '/../layouts/footer.php'; ?>
  <script src="../../public/assets/js/customer_orders.js"></script>
</body>
</html>