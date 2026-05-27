/**
 * otp.js
 * Flow:
 * 1. Read login_challenge_id from sessionStorage.
 * 2. Auto request OTP call through otp.php proxy.
 * 3. User enters OTP.
 * 4. Verify OTP through proxy.
 * 5. Complete login through proxy.
 * 6. Store access_token and redirect home.
 */

(() => {
  'use strict';

  const CONFIG = window.OTP_CONFIG || {};

  const OTP_PROXY_URL = CONFIG.proxyUrl || `${window.location.pathname}?otp_ajax=1`;
  const REDIRECT_LOGIN = CONFIG.loginUrl || './user-login.php';
  const REDIRECT_HOME = CONFIG.homeUrl || '../user/home.php';

  const OTP_EXPIRE_SECONDS = 5 * 60;

  const SESSION_KEY_CHALLENGE = 'login_challenge_id';
  const SESSION_KEY_IDENTIFIER = 'login_identifier';

  const STORAGE_KEY_TOKEN = 'access_token';
  const STORAGE_KEY_USER = 'auth_user';

  const otpForm = document.getElementById('otp-form');
  const otpDigits = Array.from(document.querySelectorAll('.otp-digit'));
  const otpSubmitBtn = document.getElementById('otp-submit');
  const otpSpinner = document.getElementById('otp-spinner');
  const submitLabel = otpSubmitBtn?.querySelector('.btn-label');

  const otpAlert = document.getElementById('otp-alert');
  const callBanner = document.getElementById('call-status-banner');
  const callStatusText = document.getElementById('call-status-text');

  const countdownEl = document.getElementById('otp-countdown');
  const countdownWrap = document.getElementById('otp-countdown-wrap');
  const resendBtn = document.getElementById('resend-btn');
  const otpCard = document.querySelector('.otp-card');

  let challengeId = sessionStorage.getItem(SESSION_KEY_CHALLENGE);
  let identifier = sessionStorage.getItem(SESSION_KEY_IDENTIFIER);

  let countdownTimer = null;
  let secondsLeft = OTP_EXPIRE_SECONDS;
  let isSubmitting = false;
  let resendCount = 0;
  const MAX_RESEND = 3;

  if (!otpForm || !challengeId) {
    window.location.replace(REDIRECT_LOGIN);
    return;
  }

  function showAlert(message, type = 'danger') {
    if (!otpAlert) return;

    otpAlert.className = `alert alert--${type}`;
    otpAlert.textContent = message;
    otpAlert.classList.remove('hidden');
  }

  function hideAlert() {
    otpAlert?.classList.add('hidden');
  }

  function setCallBanner(state, text) {
    if (!callBanner || !callStatusText) return;

    callBanner.className = `call-status-banner call-status-banner--${state}`;
    callStatusText.textContent = text;
  }

  function getOtpCode() {
    return otpDigits.map((digit) => digit.value).join('');
  }

  function syncSubmitState() {
    const code = getOtpCode();

    if (otpSubmitBtn) {
      otpSubmitBtn.disabled = code.length < 6 || isSubmitting;
    }
  }

  function setSubmitLoading(loading) {
    isSubmitting = loading;

    if (!otpSubmitBtn) return;

    if (loading) {
      otpSubmitBtn.classList.add('is-loading');
      otpSpinner?.classList.remove('hidden');

      if (submitLabel) {
        submitLabel.style.visibility = 'hidden';
      }

      otpSubmitBtn.disabled = true;
    } else {
      otpSubmitBtn.classList.remove('is-loading');
      otpSpinner?.classList.add('hidden');

      if (submitLabel) {
        submitLabel.style.visibility = '';
      }

      syncSubmitState();
    }
  }

  function shakeDigits() {
    otpDigits.forEach((digit) => {
      digit.classList.remove('is-error');
      void digit.offsetWidth;
      digit.classList.add('is-error');
    });

    setTimeout(() => {
      otpDigits.forEach((digit) => digit.classList.remove('is-error'));
    }, 600);
  }

  function clearDigits() {
    otpDigits.forEach((digit) => {
      digit.value = '';
      digit.classList.remove('is-filled', 'is-error');
    });

    otpDigits[0]?.focus();
    syncSubmitState();
  }

  async function postOtpAction(action, payload = {}) {
    const requestBody = {
      action,
      login_challenge_id: challengeId,
      ...payload
    };

    console.log('[OTP POST]', OTP_PROXY_URL, requestBody);

    const response = await fetch(OTP_PROXY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(requestBody)
    });

    const rawText = await response.text();

    console.log('[OTP STATUS]', response.status);
    console.log('[OTP RAW]', rawText);

    let data;

    try {
      data = JSON.parse(rawText);
    } catch (error) {
      return {
        ok: false,
        error_code: 'INVALID_JSON_RESPONSE',
        message: `Server did not return JSON. Status: ${response.status}`,
        data: {
          raw_response: rawText
        }
      };
    }

    if (!response.ok || data.success === false) {
      return {
        ok: false,
        error_code: data.error_code || 'REQUEST_FAILED',
        message: data.message || 'Request failed',
        data
      };
    }

    return {
      ok: true,
      data
    };
  }

  function formatTime(seconds) {
    const minutes = Math.floor(seconds / 60).toString().padStart(2, '0');
    const remainingSeconds = (seconds % 60).toString().padStart(2, '0');

    return `${minutes}:${remainingSeconds}`;
  }

  function startCountdown(durationSeconds = OTP_EXPIRE_SECONDS) {
    clearInterval(countdownTimer);

    secondsLeft = Number(durationSeconds) || OTP_EXPIRE_SECONDS;

    if (countdownEl) {
      countdownEl.textContent = formatTime(secondsLeft);
    }

    countdownWrap?.classList.remove('hidden', 'is-urgent');
    resendBtn?.classList.add('hidden');

    countdownTimer = setInterval(() => {
      secondsLeft--;

      if (secondsLeft <= 0) {
        clearInterval(countdownTimer);

        countdownWrap?.classList.add('hidden');
        showResendButton();

        if (otpSubmitBtn) {
          otpSubmitBtn.disabled = true;
        }

        showAlert('Your verification code has expired. Please request a new call.', 'warning');
        return;
      }

      if (countdownEl) {
        countdownEl.textContent = formatTime(secondsLeft);
      }

      if (secondsLeft < 60) {
        countdownWrap?.classList.add('is-urgent');
      }
    }, 1000);
  }

  function showResendButton() {
    if (!resendBtn) return;

    if (resendCount >= MAX_RESEND) {
      resendBtn.textContent = 'Maximum resend limit reached';
      resendBtn.disabled = true;
      resendBtn.classList.remove('hidden');
      return;
    }

    resendBtn.textContent = 'Request another call';
    resendBtn.classList.remove('hidden');
    resendBtn.disabled = false;
  }

  async function requestOtp() {
    setCallBanner('calling', 'Initiating voice call…');
    hideAlert();

    const result = await postOtpAction('request_otp');

    if (!result.ok) {
      handleRequestError(result.error_code, result.message);
      return;
    }

    resendCount++;

    const responseData = result.data?.data || {};
    const expiresIn = responseData.expires_in || OTP_EXPIRE_SECONDS;
    const provider = responseData.sms_provider || '';

    sessionStorage.setItem('otp_expires_in', String(expiresIn));

    if (provider) {
      sessionStorage.setItem('sms_provider', provider);
    }

    setCallBanner('sent', 'Voice call placed! Listen for your 6-digit code.');
    startCountdown(expiresIn);
    otpDigits[0]?.focus();
  }

  function handleRequestError(code, message) {
    setCallBanner('error', 'Could not place the call.');

    switch (code) {
      case 'RISK_LEVEL_HIGH':
      case 'LOGIN_CHALLENGE_BLOCKED':
        showAlert('Your session has been blocked due to suspicious activity. Please log in again.', 'danger');
        lockFormPermanently();
        scheduleLoginRedirect(4000);
        break;

      case 'OTP_RESEND_LIMIT_EXCEEDED':
        showAlert('You have requested too many codes. Please log in again.', 'danger');
        lockFormPermanently();
        scheduleLoginRedirect(4000);
        break;

      case 'LOGIN_CHALLENGE_EXPIRED':
        showAlert('Your login session has expired. Please log in again.', 'warning');
        scheduleLoginRedirect(3000);
        break;

      case 'SMS_SEND_FAILED':
        showAlert('We could not place the call right now. Please try requesting a new one.', 'danger');
        showResendButton();
        break;

      default:
        showAlert(message || 'Failed to send verification call. Please try again.', 'danger');
        showResendButton();
    }
  }

  async function verifyOtp(otpCode) {
    const result = await postOtpAction('verify_otp', {
      otp_code: otpCode
    });

    if (!result.ok) {
      handleVerifyError(result.error_code, result.message);
      return false;
    }

    return true;
  }

  function handleVerifyError(code, message) {
    shakeDigits();
    clearDigits();
    setSubmitLoading(false);

    switch (code) {
      case 'OTP_INVALID':
        showAlert('Incorrect code. Please check the code you heard and try again.', 'danger');
        break;

      case 'OTP_EXPIRED':
      case 'OTP_USED':
        showAlert('This code is no longer valid. Please request a new call.', 'warning');
        clearInterval(countdownTimer);
        countdownWrap?.classList.add('hidden');
        showResendButton();
        break;

      case 'OTP_TOO_MANY_ATTEMPTS':
      case 'RISK_LEVEL_HIGH':
      case 'LOGIN_CHALLENGE_BLOCKED':
        showAlert('Too many incorrect attempts. Your session has been locked. Please log in again.', 'danger');
        lockFormPermanently();
        scheduleLoginRedirect(4000);
        break;

      case 'LOGIN_CHALLENGE_EXPIRED':
        showAlert('Your session has expired. Please log in again.', 'warning');
        scheduleLoginRedirect(3000);
        break;

      case 'OTP_PROVIDER_VERIFY_FAILED':
        showAlert('Verification service is temporarily unavailable. Please try again.', 'danger');
        break;

      default:
        showAlert(message || 'Verification failed. Please try again.', 'danger');
    }
  }

  async function completeLogin() {
  const result = await postOtpAction('complete_login');

  if (!result.ok) {
    setSubmitLoading(false);
    showAlert(result.message || 'Login could not be completed. Please try again.', 'danger');
    return false;
  }

  const response = result.data || {};
  const payload = response.data || response;

  const accessToken =
    payload.access_token ||
    payload.token ||
    payload.accessToken ||
    response.access_token ||
    response.token ||
    response.accessToken ||
    null;

  const user =
    payload.user ||
    payload.current_user ||
    response.user ||
    response.current_user ||
    null;

  if (!accessToken) {
    setSubmitLoading(false);
    console.error('[complete-login response missing access_token]', result);
    showAlert('Login completed, but access token was not returned.', 'danger');
    return false;
  }

  localStorage.setItem('access_token', accessToken);
  localStorage.setItem('auth_token', accessToken);
  localStorage.setItem('is_logged_in', '1');

  if (user) {
    localStorage.setItem('auth_user', JSON.stringify(user));
    localStorage.setItem('current_user', JSON.stringify(user));
    localStorage.setItem('user', JSON.stringify(user));
  }

  sessionStorage.removeItem(SESSION_KEY_CHALLENGE);
  sessionStorage.removeItem(SESSION_KEY_IDENTIFIER);
  sessionStorage.removeItem('login_challenge_id');
  sessionStorage.removeItem('login_identifier');
  sessionStorage.removeItem('otp_expires_in');
  sessionStorage.removeItem('sms_provider');

  return true;
}

  otpForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (isSubmitting) return;

    const otpCode = getOtpCode();

    if (otpCode.length < 6) return;

    hideAlert();
    setSubmitLoading(true);

    const verified = await verifyOtp(otpCode);

    if (!verified) {
      return;
    }

    const loggedIn = await completeLogin();

    if (!loggedIn) {
      return;
    }

    clearInterval(countdownTimer);
    otpCard?.classList.add('is-success');

    setTimeout(() => {
      window.location.replace(REDIRECT_HOME);
    }, 700);
  });

  resendBtn?.addEventListener('click', async () => {
    if (resendCount >= MAX_RESEND) return;

    clearDigits();
    hideAlert();

    resendBtn.disabled = true;
    resendBtn.classList.add('hidden');

    await requestOtp();
  });

  otpDigits.forEach((input, index) => {
    input.addEventListener('keydown', (event) => {
      const allowed = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Enter'];

      if (!allowed.includes(event.key) && !/^\d$/.test(event.key)) {
        event.preventDefault();
      }
    });

    input.addEventListener('input', () => {
      const value = input.value.replace(/\D/g, '');

      input.value = value ? value[value.length - 1] : '';

      if (input.value) {
        input.classList.add('is-filled');

        if (index < otpDigits.length - 1) {
          otpDigits[index + 1].focus();
        }
      } else {
        input.classList.remove('is-filled');
      }

      syncSubmitState();
    });

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Backspace' && !input.value && index > 0) {
        otpDigits[index - 1].focus();
        otpDigits[index - 1].value = '';
        otpDigits[index - 1].classList.remove('is-filled');
        syncSubmitState();
      }
    });

    input.addEventListener('focus', () => {
      setTimeout(() => {
        input.setSelectionRange(input.value.length, input.value.length);
      }, 0);
    });
  });

  otpDigits[0]?.addEventListener('paste', (event) => {
    event.preventDefault();

    const pasted = (event.clipboardData || window.clipboardData)
      .getData('text')
      .replace(/\D/g, '')
      .slice(0, 6);

    pasted.split('').forEach((char, index) => {
      if (otpDigits[index]) {
        otpDigits[index].value = char;
        otpDigits[index].classList.add('is-filled');
      }
    });

    const nextEmpty = otpDigits.find((digit) => !digit.value);

    (nextEmpty || otpDigits[otpDigits.length - 1]).focus();

    syncSubmitState();
  });

  function lockFormPermanently() {
    clearInterval(countdownTimer);

    otpDigits.forEach((digit) => {
      digit.disabled = true;
    });

    if (otpSubmitBtn) {
      otpSubmitBtn.disabled = true;
    }

    if (resendBtn) {
      resendBtn.disabled = true;
      resendBtn.classList.add('hidden');
    }

    countdownWrap?.classList.add('hidden');
  }

  function scheduleLoginRedirect(ms = 3000) {
    setTimeout(() => {
      window.location.replace(REDIRECT_LOGIN);
    }, ms);
  }

  requestOtp();
})();