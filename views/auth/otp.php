<?php
/**
 * ============================================================
 * VIEW: views/auth/otp.php
 * OTP Verification page — step 3 of 4 in the login flow.
 *
 * Flow (architecture.md):
 *   1. User arrives here after POST /api/auth/login succeeded.
 *      JS stored login_challenge_id in sessionStorage.
 *   2. JS auto-calls POST /api/otp/request on page load
 *      (triggers Twilio voice call with OTP).
 *   3. User enters the 6-digit OTP they received via phone call.
 *   4. JS calls POST /api/otp/verify  → status = OTP_VERIFIED.
 *   5. JS calls POST /api/auth/complete-login → receives access_token.
 *   6. JS stores access_token in localStorage, redirects to home.php.
 *
 * Security notes:
 *   - login_challenge_id is read from sessionStorage (set by user-login.js).
 *   - access_token is stored in localStorage after complete-login.
 *   - No token is issued on this page before OTP_VERIFIED.
 * ============================================================
 */

$pageTitle = 'Verify OTP';

require_once __DIR__ . '/../layouts/header.php';
?>

<link rel="stylesheet" href="../../public/assets/css/root.css">
<link rel="stylesheet" href="../../public/assets/css/otp.css">

<main class="auth-page">
  <section class="auth-card otp-card" aria-labelledby="otp-title">

    <!-- Card header -->
    <header class="auth-card__head">
      <span class="auth-card__logo" aria-hidden="true">
        <!-- Phone ringing SVG icon -->
        <svg viewBox="0 0 48 48" width="48" height="48" role="img" aria-label="Phone call">
          <circle cx="24" cy="24" r="22" fill="var(--color-primary-light)"></circle>
          <path d="M17 14h-3a2 2 0 0 0-2 2c0 10.5 8.5 19 19 19a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2c-1.3 0-2.5-.2-3.7-.6a2 2 0 0 0-2 .5l-1.8 1.8a15.1 15.1 0 0 1-6.6-6.6l1.8-1.8a2 2 0 0 0 .5-2A11.4 11.4 0 0 1 19 16a2 2 0 0 0-2-2z"
            fill="var(--color-primary)" stroke="none"/>
          <!-- Sound waves -->
          <path d="M30 18a6 6 0 0 1 0 12" stroke="var(--color-primary)" stroke-width="2" fill="none" stroke-linecap="round"/>
          <path d="M33 15a10 10 0 0 1 0 18" stroke="var(--color-primary)" stroke-width="1.5" fill="none" stroke-linecap="round" opacity="0.5"/>
        </svg>
      </span>

      <h1 class="auth-card__title" id="otp-title">Enter Verification Code</h1>
      <p class="auth-card__subtitle" id="otp-subtitle">
        We're calling your registered phone number.<br>
        Please listen and enter the 6-digit code.
      </p>
    </header>

    <!-- Status banner: shown while Twilio call is being initiated -->
    <div id="call-status-banner" class="call-status-banner call-status-banner--calling" role="status" aria-live="polite">
      <span class="call-status-banner__icon" aria-hidden="true">
        <!-- Animated phone SVG -->
        <svg class="icon-phone-ring" viewBox="0 0 24 24" width="18" height="18" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.27h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9a16 16 0 0 0 6.06 6.06l1.8-1.8a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
      </span>
      <span id="call-status-text">Initiating voice call…</span>
    </div>

    <!-- Alert area (errors, warnings) -->
    <div id="otp-alert" class="alert alert--danger hidden" role="alert" aria-live="assertive"></div>

    <!-- OTP form -->
    <form id="otp-form" class="auth-form otp-form" novalidate autocomplete="off">

      <!-- 6-digit input boxes -->
      <div class="otp-inputs" role="group" aria-label="Enter the 6-digit verification code">
        <input class="otp-digit" type="text" inputmode="numeric" pattern="[0-9]"
               maxlength="1" aria-label="Digit 1" autocomplete="one-time-code" data-index="0">
        <input class="otp-digit" type="text" inputmode="numeric" pattern="[0-9]"
               maxlength="1" aria-label="Digit 2" data-index="1">
        <input class="otp-digit" type="text" inputmode="numeric" pattern="[0-9]"
               maxlength="1" aria-label="Digit 3" data-index="2">

        <!-- Visual separator dot -->
        <span class="otp-separator" aria-hidden="true">
          <svg viewBox="0 0 8 24" width="8" height="24">
            <circle cx="4" cy="12" r="3" fill="var(--color-border-dark)"/>
          </svg>
        </span>

        <input class="otp-digit" type="text" inputmode="numeric" pattern="[0-9]"
               maxlength="1" aria-label="Digit 4" data-index="3">
        <input class="otp-digit" type="text" inputmode="numeric" pattern="[0-9]"
               maxlength="1" aria-label="Digit 5" data-index="4">
        <input class="otp-digit" type="text" inputmode="numeric" pattern="[0-9]"
               maxlength="1" aria-label="Digit 6" data-index="5">
      </div>

      <!-- Countdown + resend -->
      <div class="otp-timer-row">
        <span id="otp-countdown-wrap" class="otp-timer">
          <!-- Clock SVG -->
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
               aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
          </svg>
          Code expires in <strong id="otp-countdown">05:00</strong>
        </span>

        <button type="button" id="resend-btn" class="auth-link otp-resend hidden" disabled>
          <!-- Refresh SVG -->
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
               aria-hidden="true">
            <polyline points="1 4 1 10 7 10"/>
            <path d="M3.51 15a9 9 0 1 0 .49-3.54"/>
          </svg>
          Request a new call
        </button>
      </div>

      <!-- Submit -->
      <button type="submit" class="btn btn--primary btn--lg w-full" id="otp-submit" disabled>
        <span class="btn-label">Verify &amp; Sign In</span>
        <span class="spinner spinner--btn hidden" id="otp-spinner" aria-hidden="true"></span>
      </button>
    </form>

    <!-- Card footer -->
    <footer class="auth-card__foot">
      <p class="text-muted text-center otp-help">
        <!-- Info SVG -->
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none"
             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             aria-hidden="true" style="vertical-align:-2px; margin-right:4px;">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        Didn't receive the call? Wait a moment, then request a new one.
        <br>
        <a class="auth-link" href="../../views/auth/user-login.php">Back to Login</a>
      </p>
    </footer>

  </section>
</main>

<script src="/assets/js/otp.js" defer></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>