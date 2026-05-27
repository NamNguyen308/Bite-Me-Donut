<?php
/**
 * ============================================================
 * VIEW: views/auth/user-register.php
 * Trang đăng ký tài khoản cho khách hàng (user).
 * ============================================================
 */

$pageTitle = 'Register';
require_once __DIR__ . '/../layouts/header.php';
?>

<link rel="stylesheet" href="../../public/assets/css/root.css">
<link rel="stylesheet" href="../../public/assets/css/user-register.css">

<main class="auth-page">
  <section class="auth-card" aria-labelledby="auth-title">

    <header class="auth-card__head">
      <span class="auth-card__logo" aria-hidden="true">
        <svg viewBox="0 0 48 48" width="48" height="48" role="img" aria-label="Bite-Me-Donut">
          <circle cx="24" cy="24" r="20" fill="var(--color-primary)"></circle>
          <circle cx="24" cy="24" r="7.5" fill="var(--color-bg-card)"></circle>
          <g fill="var(--color-bg-card)">
            <circle cx="24" cy="7.5" r="1.8"></circle>
            <circle cx="36" cy="13" r="1.8"></circle>
            <circle cx="40.5" cy="24" r="1.8"></circle>
            <circle cx="36" cy="35" r="1.8"></circle>
            <circle cx="12" cy="35" r="1.8"></circle>
            <circle cx="7.5" cy="24" r="1.8"></circle>
            <circle cx="12" cy="13" r="1.8"></circle>
          </g>
        </svg>
      </span>
      <h1 class="auth-card__title" id="auth-title">Register</h1>
      <p class="auth-card__subtitle">Join Bite-Me-Donut today</p>
    </header>

    <div id="auth-alert" class="alert alert--danger hidden" role="alert" aria-live="polite"></div>

    <form id="register-form" class="auth-form" novalidate autocomplete="on">

      <!-- Phone hoặc Email -->
      <div class="form-group">
        <label class="form-label" for="register-identifier">Phone Number or Email</label>
        <div class="input-wrap">
          <span class="input-wrap__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </span>
          <input
            type="text"
            id="register-identifier"
            name="identifier"
            class="form-input has-icon"
            placeholder="Enter your phone number or email"
            autocomplete="username"
            inputmode="text"
            required>
        </div>
        <p class="form-error hidden" data-error-for="identifier"></p>
      </div>

      <!-- Mật khẩu -->
      <div class="form-group">
        <label class="form-label" for="register-password">Password</label>
        <div class="input-wrap">
          <span class="input-wrap__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
          </span>
          <input
            type="password"
            id="register-password"
            name="password"
            class="form-input has-icon has-toggle"
            placeholder="Enter your password"
            autocomplete="new-password"
            required>
          <button type="button" class="input-wrap__toggle toggle-password" aria-label="Show password" aria-pressed="false">
            <svg class="icon-eye" viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <svg class="icon-eye-off hidden" viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
              <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>
          </button>
        </div>
        <p class="form-error hidden" data-error-for="password"></p>
      </div>

      <!-- Xác nhận Mật khẩu -->
      <div class="form-group">
        <label class="form-label" for="register-confirm-password">Confirm Password</label>
        <div class="input-wrap">
          <span class="input-wrap__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
          </span>
          <input
            type="password"
            id="register-confirm-password"
            name="confirm_password"
            class="form-input has-icon has-toggle"
            placeholder="Confirm your password"
            autocomplete="new-password"
            required>
          <button type="button" class="input-wrap__toggle toggle-password" aria-label="Show password" aria-pressed="false">
            <svg class="icon-eye" viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <svg class="icon-eye-off hidden" viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
              <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>
          </button>
        </div>
        <p class="form-error hidden" data-error-for="confirm_password"></p>
      </div>

      <!-- Nút submit -->
      <button type="submit" class="btn btn--primary btn--lg w-full" id="register-submit">
        <span class="btn-label">Register</span>
        <span class="spinner spinner--btn hidden" id="register-spinner" aria-hidden="true"></span>
      </button>
    </form>

    <footer class="auth-card__foot">
      <p class="text-muted text-center">
        Already have an account?
        <a class="auth-link" href="user-login.php">Login now</a>
      </p>
    </footer>

  </section>
</main>

<script src="../../public/assets/js/user-register.js" defer></script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
