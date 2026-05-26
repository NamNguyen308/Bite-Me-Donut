<?php
/**
 * Profile Page
 * Requires: user must be logged in (access_token in localStorage handled by JS)
 * API: GET /api/users/me (Authorization: Bearer <token>)
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Profile — Bite-Me-Donut</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../../public/assets/css/root.css" />
  <link rel="stylesheet" href="../../public/assets/css/customer_profile.css" />
</head>
<body>

  <?php include __DIR__ . '/../layouts/header.php'; ?>

  <!-- ══════════════════════════════════════════════
       MAIN LAYOUT
  ══════════════════════════════════════════════ -->
  <div class="profile-layout">

    <!-- ── SIDEBAR ── -->
    <aside class="profile-sidebar" id="profileSidebar">

      <!-- Mini avatar in sidebar -->
      <div class="sidebar-avatar-block">
        <div class="sidebar-avatar-ring">
          <img
            src="../../public/assets/img/user.jpg"
            alt="Ảnh đại diện"
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
        <a href="../../views/user/profile.php" class="sidebar-nav__item active" data-page="profile">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </span>
          <span class="sidebar-nav__label">My Profile</span>
        </a>

        <!-- My Orders -->
        <a href="../../views/user/customer_orders.php" class="sidebar-nav__item" data-page="orders">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          </span>
          <span class="sidebar-nav__label">My Orders</span>
        </a>

        <!-- Change Password -->
        <a href="../../views/auth/change_password.php" class="sidebar-nav__item" data-page="change-password">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </span>
          <span class="sidebar-nav__label">Change Password</span>
        </a>

        <div class="sidebar-nav__divider"></div>

        <!-- Logout -->
        <button class="sidebar-nav__item sidebar-nav__item--logout" id="sidebarLogoutBtn">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </span>
          <span class="sidebar-nav__label">Logout</span>
        </button>

      </nav>
    </aside><!-- /.profile-sidebar -->

    <!-- ── MAIN CONTENT ── -->
    <main class="profile-main">

      <!-- Loading state -->
      <div class="loading-overlay" id="pageLoader">
        <div class="spinner"></div>
      </div>

      <!-- Error -->
      <div class="profile-error hidden" id="profileError">
        <div class="alert alert--danger" id="profileErrorMsg">
          Cannot load profile. Please log in again.
        </div>
        <a href="../../views/auth/login.php" class="btn btn--primary" style="margin-top: var(--space-4);">Log in</a>
      </div>

      <!-- Profile Content -->
      <div class="profile-content hidden" id="profileContent">

        <!-- Breadcrumb -->
        <!-- <nav class="breadcrumb">
          <div class="breadcrumb__item">
            <a href="/index.php" class="breadcrumb__link">Trang chủ</a>
            <span class="breadcrumb__separator">›</span>
          </div>
          <div class="breadcrumb__item">
            <span class="breadcrumb__current">Hồ sơ của tôi</span>
          </div>
        </nav> -->

        <!-- ── HERO BANNER ── -->
        <div class="profile-hero">
          <div class="profile-hero__bg"></div>
          <div class="profile-hero__content">

            <div class="avatar-wrapper">
              <div class="avatar-ring">
                <img
                  src="../../public/assets/img/user.jpg"
                  alt="Ảnh đại diện"
                  class="avatar-img"
                  id="avatarImg"
                  onerror="this.src='../../public/assets/img/user-default.svg'"
                />
              </div>
              <div class="avatar-status" title="Đang hoạt động"></div>
            </div>

            <div class="profile-hero__info">
              <h1 class="profile-hero__name" id="heroName">—</h1>
              <div class="profile-hero__meta">
                <span class="badge badge--primary" id="heroRole">Customer</span>
                <span class="profile-hero__join" id="heroJoin"></span>
              </div>
              <p class="profile-hero__phone" id="heroPhone"></p>
            </div>

          </div>
        </div><!-- /.profile-hero -->

        <!-- ── TWO-COLUMN: Info + Security ── -->
        <div class="profile-grid">

          <!-- Personal Info card -->
          <section class="profile-card" id="infoCard">
            <div class="profile-card__header">
              <span class="profile-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </span>
              <h2 class="profile-card__title">Personal Information</h2>
              <button class="btn btn--outline btn--sm" id="editBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
              </button>
            </div>

            <!-- View mode -->
            <div class="profile-info-list" id="infoView">
              <div class="info-row">
                <span class="info-row__label">Full name</span>
                <span class="info-row__value" id="infoName">—</span>
              </div>
              <div class="info-row">
                <span class="info-row__label">Email</span>
                <span class="info-row__value" id="infoEmail">—</span>
              </div>
              <div class="info-row">
                <span class="info-row__label">Phone Number</span>
                <span class="info-row__value" id="infoPhone">—</span>
              </div>
              <div class="info-row">
                <span class="info-row__label">Role</span>
                <span class="info-row__value" id="infoRole">—</span>
              </div>
              <div class="info-row">
                <span class="info-row__label">Status</span>
                <span class="info-row__value" id="infoStatus">—</span>
              </div>
              <div class="info-row">
                <span class="info-row__label">Joined Date</span>
                <span class="info-row__value" id="infoCreated">—</span>
              </div>
            </div>

            <!-- Edit mode -->
            <div class="profile-edit-form hidden" id="editForm">
              <div class="form-group">
                <label class="form-label" for="editName">Full name</label>
                <input type="text" class="form-input" id="editName" placeholder="Enter your full name" />
              </div>
              <div class="form-group">
                <label class="form-label" for="editEmail">Email</label>
                <input type="email" class="form-input" id="editEmail" placeholder="Enter your email" />
                <span class="form-hint">Email is used to login and receive notifications</span>
              </div>
              <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" class="form-input" id="editPhone" disabled />
                <span class="form-hint">Phone Number cannot be changed</span>
              </div>
              <div class="edit-actions">
                <button class="btn btn--primary" id="saveBtn">
                  <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                  Save Changes
                </button>
                <button class="btn btn--ghost" id="cancelEditBtn">Cancel</button>
              </div>
              <div id="editAlert" class="hidden" style="margin-top: var(--space-4);"></div>
            </div>
          </section>

          <!-- Security card -->
          <section class="profile-card profile-card--security">
            <div class="profile-card__header">
              <span class="profile-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              </span>
              <h2 class="profile-card__title">Account Security</h2>
            </div>
            <div class="security-list">

              <!-- OTP -->
              <div class="security-item">
                <div class="security-item__left">
                  <span class="security-item__icon security-item__icon--ok">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-success)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  </span>
                  <div>
                    <p class="security-item__title">OTP Verification</p>
                    <p class="security-item__desc">Two-factor protection via call</p>
                  </div>
                </div>
                <span class="badge badge--success">Enabled</span>
              </div>

              <!-- Change password -->
              <div class="security-item">
                <div class="security-item__left">
                  <span class="security-item__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                  </span>
                  <div>
                    <p class="security-item__title">Password</p>
                    <p class="security-item__desc">Update password regularly</p>
                  </div>
                </div>
                <a href="/change-password.php" class="btn btn--outline btn--sm">Change</a>
              </div>

              <!-- Session -->
              <div class="security-item">
                <div class="security-item__left">
                  <span class="security-item__icon" id="sessionIcon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  </span>
                  <div>
                    <p class="security-item__title">Login Session</p>
                    <p class="security-item__desc" id="sessionDesc">Token is valid</p>
                  </div>
                </div>
                <button class="btn btn--danger btn--sm" id="revokeBtn">
                  <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                  Logout
                </button>
              </div>

            </div>
          </section>

        </div><!-- /.profile-grid -->

      </div><!-- /#profileContent -->

    </main><!-- /.profile-main -->

  </div><!-- /.profile-layout -->

  <!-- ══════════════════════════════════════════════
       LOGOUT MODAL
  ══════════════════════════════════════════════ -->
  <div class="modal-overlay" id="logoutModal">
    <div class="modal">
      <div class="modal__header">
        <h3 class="modal__title">Logout</h3>
        <button class="modal__close" id="logoutModalClose">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <p style="margin-bottom: var(--space-6); color: var(--color-text-muted); line-height: 1.7;">
        Are you sure you want to logout?<br/>Your token will be revoked immediately.
      </p>
      <div class="flex gap-4">
        <button class="btn btn--danger w-full" id="confirmLogoutBtn">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Logout
        </button>
        <button class="btn btn--ghost w-full" id="cancelLogoutBtn">Cancel</button>
      </div>
    </div>
  </div>

  <!-- TOAST -->
  <div class="toast-container" id="toastContainer"></div>
  <?php include __DIR__ . '/../layouts/footer.php'; ?>
  <script src="../../public/assets/js/customer_profile.js"></script>
</body>
</html>
