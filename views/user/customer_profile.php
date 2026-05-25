<?php
/**
 * Profile Page
 * Requires: user must be logged in (access_token in session or localStorage handled by JS)
 * API: GET /api/users/me (Authorization: Bearer <token>)
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Profile — Bite-Me-Donut</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  <!-- Design System -->
  <link rel="stylesheet" href="../../public/assets/css/root.css" />

  <!-- Profile Page Styles -->
  <link rel="stylesheet" href="../../public/assets/css/customer_profile.css" />
</head>
<body>
  <?php include __DIR__ . '/../layouts/header.php'; ?>
  <!-- ══════════════════════════════════════════════
       MAIN CONTENT
  ══════════════════════════════════════════════ -->
  <main class="profile-page">

    <!-- Loading state -->
    <div class="loading-overlay" id="pageLoader">
      <div class="spinner"></div>
    </div>

    <!-- Error state -->
    <div class="profile-error hidden" id="profileError">
      <div class="container container--sm">
        <div class="alert alert--danger" id="profileErrorMsg">
          Cannot load profile. Please log in again.
        </div>
        <a href="../../views/auth/login.php" class="btn btn--primary">Log in again</a>
      </div>
    </div>

    <!-- Profile Content -->
    <div class="profile-content hidden" id="profileContent">
      <div class="container">

        <!-- ── HERO BANNER ── -->
        <div class="profile-hero">
          <div class="profile-hero__bg"></div>
          <div class="profile-hero__content">

            <!-- Avatar block -->
            <div class="avatar-wrapper">
              <div class="avatar-ring">
                <img
                  src="/public/assets/img/user.jpg"
                  alt="Ảnh đại diện"
                  class="avatar-img"
                  id="avatarImg"
                  onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'%3E%3Ccircle cx=\'50\' cy=\'50\' r=\'50\' fill=\'%23fce4f3\'/%3E%3Ctext x=\'50\' y=\'62\' text-anchor=\'middle\' font-size=\'40\'%3E🍩%3C/text%3E%3C/svg%3E'"
                />
              </div>
              <div class="avatar-status" title="Online"></div>
            </div>

            <!-- Name & role -->
            <div class="profile-hero__info">
              <h1 class="profile-hero__name" id="heroName">—</h1>
              <div class="profile-hero__meta">
                <span class="badge badge--primary" id="heroRole">Customer</span>
                <span class="profile-hero__join" id="heroJoin"></span>
              </div>
              <p class="profile-hero__phone" id="heroPhone"></p>
            </div>

          </div><!-- /.profile-hero__content -->
        </div><!-- /.profile-hero -->

        <!-- ── GRID: INFO + STATS ── -->
        <div class="profile-grid">

          <!-- LEFT — Personal information card -->
          <section class="profile-card" id="infoCard">
            <div class="profile-card__header">
              <span class="profile-card__icon">👤</span>
              <h2 class="profile-card__title">Personal Information</h2>
              <button class="btn btn--outline btn--sm" id="editBtn">✏️ Edit</button>
            </div>

            <!-- View mode -->
            <div class="profile-info-list" id="infoView">
              <div class="info-row">
                <span class="info-row__label">Full Name</span>
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

            <!-- Edit mode (hidden by default) -->
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
                <button class="btn btn--primary" id="saveBtn">💾 Save changes</button>
                <button class="btn btn--ghost"   id="cancelEditBtn">Cancel</button>
              </div>
              <div id="editAlert" class="hidden" style="margin-top: var(--space-4);"></div>
            </div>
          </section><!-- /.profile-card#infoCard -->

          <!-- RIGHT — Stats + Security -->
          <div class="profile-right-col">

            <!-- Stats card -->
            <section class="profile-card profile-card--stats">
              <div class="profile-card__header">
                <span class="profile-card__icon">📊</span>
                <h2 class="profile-card__title">Statistics</h2>
              </div>
              <div class="stats-grid">
                <div class="stat-item">
                  <span class="stat-item__value" id="statOrders">—</span>
                  <span class="stat-item__label">Orders</span>
                </div>
                <div class="stat-item">
                  <span class="stat-item__value" id="statCartItems">—</span>
                  <span class="stat-item__label">Cart</span>
                </div>
                <div class="stat-item stat-item--wide">
                  <span class="stat-item__value" id="statTotal">—</span>
                  <span class="stat-item__label">Total spending</span>
                </div>
              </div>
            </section>

            <!-- Security card -->
            <section class="profile-card profile-card--security">
              <div class="profile-card__header">
                <span class="profile-card__icon">🔒</span>
                <h2 class="profile-card__title">Security</h2>
              </div>
              <div class="security-list">
                <div class="security-item">
                  <div class="security-item__left">
                    <span class="security-item__icon security-item__icon--ok">✅</span>
                    <div>
                      <p class="security-item__title">OTP Verification</p>
                      <p class="security-item__desc">Enable 2-step verification via call</p>
                    </div>
                  </div>
                  <span class="badge badge--success">Enabled</span>
                </div>
                <div class="security-item">
                  <div class="security-item__left">
                    <span class="security-item__icon">🔑</span>
                    <div>
                      <p class="security-item__title">Change Password</p>
                      <p class="security-item__desc">Update password regularly</p>
                    </div>
                  </div>
                  <a href="/change-password.php" class="btn btn--outline btn--sm">Change</a>
                </div>
                <div class="security-item">
                  <div class="security-item__left">
                    <span class="security-item__icon" id="sessionIcon">🟢</span>
                    <div>
                      <p class="security-item__title">Login session</p>
                      <p class="security-item__desc" id="sessionDesc">Token is valid</p>
                    </div>
                  </div>
                  <button class="btn btn--danger btn--sm" id="revokeBtn">Log out</button>
                </div>
              </div>
            </section>

            <!-- Quick links card -->
            <!-- <section class="profile-card profile-card--quick">
              <div class="profile-card__header">
                <span class="profile-card__icon">⚡</span>
                <h2 class="profile-card__title">Quick links</h2>
              </div>
              <div class="quick-links">
                <a href="/orders.php"   class="quick-link">My orders</a>
                <a href="/cart.php"     class="quick-link">Cart</a>
                <a href="/products.php" class="quick-link">Menu</a>
              </div>
            </section> -->

          </div><!-- /.profile-right-col -->

        </div><!-- /.profile-grid -->

      </div><!-- /.container -->
    </div><!-- /#profileContent -->

  </main>

  <!-- ══════════════════════════════════════════════
       LOGOUT MODAL
  ══════════════════════════════════════════════ -->
  <div class="modal-overlay" id="logoutModal">
    <div class="modal">
      <div class="modal__header">
        <h3 class="modal__title">Log out</h3>
        <button class="modal__close" id="logoutModalClose">✕</button>
      </div>
      <p style="margin-bottom: var(--space-6); color: var(--color-text-muted);">
        Are you sure you want to log out?<br/>Your token will be revoked immediately.
      </p>
      <div class="flex gap-4">
        <button class="btn btn--danger w-full" id="confirmLogoutBtn">Log out</button>
        <button class="btn btn--ghost w-full"  id="cancelLogoutBtn">Cancel</button>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════
       TOAST NOTIFICATION
  ══════════════════════════════════════════════ -->
  <div class="toast-container" id="toastContainer"></div>
  <?php include __DIR__ . '/../layouts/footer.php'; ?>

  <!-- JS -->
  <script src="../../public/assets/js/profile.js"></script>
</body>
</html>