<?php
/**
 * ============================================================
 * VIEW: views/auth/user-login.php
 * Trang đăng nhập cho khách hàng (user).
 *
 * Luồng (theo architecture.md):
 *   1. User nhập phone/email + password.
 *   2. JS gọi POST /api/auth/login  -> nhận login_challenge_id (status = PENDING_OTP).
 *   3. JS lưu login_challenge_id.
 *   4. JS gọi POST /api/otp/request -> gửi OTP (status = OTP_SENT).
 *   5. Chuyển sang trang otp.php (trang này team sẽ thêm sau).
 *
 * Ghi chú:
 *   - Trang KHÔNG nhận access_token ở bước này (đúng nguyên tắc bảo mật:
 *     token chỉ được cấp sau khi OTP_VERIFIED + complete-login).
 *   - Toàn bộ xử lý gọi API nằm ở /assets/js/user-login.js.
 * ============================================================
 */

// Tiêu đề trang (header partial có thể dùng biến này nếu cần).
$pageTitle = 'Login';

/**
 * Header dùng chung của dự án.
 * >>> Chỉnh lại đường dẫn cho khớp cấu trúc thật của bạn nếu khác,
 *     ví dụ: '/../layouts/header.php' hoặc '/../partials/header.php'.
 */
require_once __DIR__ . '/../partials/header.php';
?>

<!--
  Link 2 file CSS cho trang này (root.css + css riêng của trang).
  Base URL không dùng /public (Laragon virtual host), nên đường dẫn web là /assets/...
  Nếu header của bạn có khu vực <head> hỗ trợ nạp CSS theo từng trang,
  bạn có thể chuyển 2 thẻ <link> này lên đó.
-->
<link rel="stylesheet" href="../../public/assets/css/root.css">
<link rel="stylesheet" href="../../public/assets/css/user-login.css">

<main class="auth-page">
  <section class="auth-card" aria-labelledby="auth-title">

    <!-- Header của card: logo + tiêu đề -->
    <header class="auth-card__head">
      <span class="auth-card__logo" aria-hidden="true">
        <!-- Logo donut (SVG, không dùng emoji) -->
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
      <h1 class="auth-card__title" id="auth-title">Login</h1>
      <p class="auth-card__subtitle">Welcome back to Bite-Me-Donut</p>
    </header>

    <!-- Vùng hiển thị thông báo lỗi/cảnh báo (JS điều khiển) -->
    <div id="auth-alert" class="alert alert--danger hidden" role="alert" aria-live="polite"></div>

    <!-- Form đăng nhập -->
    <form id="login-form" class="auth-form" novalidate autocomplete="on">

      <!-- Phone hoặc Email -->
      <div class="form-group">
        <label class="form-label" for="login-identifier">Số điện thoại hoặc Email</label>
        <div class="input-wrap">
          <span class="input-wrap__icon" aria-hidden="true">
            <!-- Icon người dùng (SVG) -->
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </span>
          <input
            type="text"
            id="login-identifier"
            name="login"
            class="form-input has-icon"
            placeholder="Nhập số điện thoại hoặc email"
            autocomplete="username"
            inputmode="text"
            required>
        </div>
        <p class="form-error hidden" data-error-for="login"></p>
      </div>

      <!-- Mật khẩu -->
      <div class="form-group">
        <label class="form-label" for="login-password">Password</label>
        <div class="input-wrap">
          <span class="input-wrap__icon" aria-hidden="true">
            <!-- Icon ổ khóa (SVG) -->
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
          </span>
          <input
            type="password"
            id="login-password"
            name="password"
            class="form-input has-icon has-toggle"
            placeholder="Enter your password"
            autocomplete="current-password"
            required>
          <button type="button" class="input-wrap__toggle" id="toggle-password"
                  aria-label="Show password" aria-pressed="false">
            <!-- Icon con mắt: hiện -->
            <svg class="icon-eye" viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <!-- Icon con mắt: ẩn (mặc định ẩn) -->
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

      <!-- Hàng phụ: quên mật khẩu -->
      <div class="auth-form__row">
        <a class="auth-link" href="/forgot-password.php">Forgot your password?</a>
      </div>

      <!-- Nút submit -->
      <button type="submit" class="btn btn--primary btn--lg w-full" id="login-submit">
        <span class="btn-label">Login</span>
        <span class="spinner spinner--btn hidden" id="login-spinner" aria-hidden="true"></span>
      </button>
    </form>

    <!-- Footer của card -->
    <footer class="auth-card__foot">
      <p class="text-muted text-center">
        Don't have an account?
        <a class="auth-link" href="/register-user.php">Register now</a>
      </p>
    </footer>

  </section>
</main>

<!--
  JS xử lý gọi API đăng nhập + request OTP.
  Đặt ở cuối body để DOM đã sẵn sàng.
-->
<script src="/assets/js/user-login.js" defer></script>

<?php
/**
 * Footer dùng chung của dự án.
 * >>> Chỉnh lại đường dẫn cho khớp cấu trúc thật của bạn nếu khác.
 */
require_once __DIR__ . '/../partials/footer.php';
?>