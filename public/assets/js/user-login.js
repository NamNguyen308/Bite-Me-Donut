/* ============================================================
 * USER-LOGIN.JS — Xử lý đăng nhập khách hàng
 *
 * Luồng (theo architecture.md, mục 5–7):
 *   1. POST /api/auth/login            -> { login_challenge_id, status: PENDING_OTP }
 *   2. Lưu login_challenge_id (sessionStorage)
 *   3. POST /api/otp/request           -> { status: OTP_SENT, ... }
 *   4. Chuyển sang trang OTP (otp.php)
 *
 * Nguyên tắc:
 *   - Bước này KHÔNG nhận và KHÔNG lưu access_token.
 *   - Không lưu password ở bất kỳ đâu.
 *   - error_code lấy đúng theo error-codes.md.
 * ============================================================ */

(function () {
  'use strict';

  /* --------------------------------------------------------
   * CẤU HÌNH
   * -------------------------------------------------------- */
  // Base URL rỗng = cùng origin với trang (Laragon virtual host).
  const API_BASE = '';

  const ENDPOINTS = {
    login: API_BASE + '/api/auth/login',
    otpRequest: API_BASE + '/api/otp/request',
  };

  // Trang OTP — hiện chưa có, team sẽ thêm sau. Đổi đường dẫn này khi có otp.php.
  const OTP_PAGE_URL = '/otp.php';

  // Khóa lưu trong sessionStorage để trang otp.php đọc lại.
  const STORAGE_KEYS = {
    challengeId: 'login_challenge_id',
    identifier: 'login_identifier',     // để otp.php hiển thị "OTP gửi tới ..."
    otpExpiresIn: 'otp_expires_in',
    smsProvider: 'sms_provider',
  };

  /* --------------------------------------------------------
   * BẢN ĐỒ error_code -> thông báo tiếng Việt (theo error-codes.md)
   * -------------------------------------------------------- */
  const ERROR_MESSAGES = {
    // General
    VALIDATION_ERROR: 'Information is invalid. Please try again.',
    INTERNAL_SERVER_ERROR: 'Server error. Please try again later.',
    NOT_FOUND: 'Resource not found.',
    METHOD_NOT_ALLOWED: 'Method not allowed.',

    // Auth
    INVALID_CREDENTIALS: 'Invalid credentials.',
    PASSWORD_NOT_VERIFIED: 'Password not verified.',
    ACCOUNT_INACTIVE: 'Account is inactive. Please contact support.',

    // Login challenge
    LOGIN_CHALLENGE_NOT_FOUND: 'Login session not found. Please log in again.',
    LOGIN_CHALLENGE_EXPIRED: 'Login session has expired. Please log in again.',
    LOGIN_CHALLENGE_BLOCKED: 'Login session is temporarily blocked due to suspicious activity.',

    // OTP (ở bước request)
    OTP_RESEND_LIMIT_EXCEEDED: 'You have requested OTP too many times. Please try again later.',
    SMS_SEND_FAILED: 'Failed to send OTP. Please try again.',
    OTP_PROVIDER_VERIFY_FAILED: 'Failed to connect to OTP service. Please try again later.',

    // Risk
    RISK_LEVEL_MEDIUM: 'System requires additional verification. Please try again.',
    RISK_LEVEL_HIGH: 'Unusual activity detected. Login operation temporarily blocked.',

    // Mặc định
    _DEFAULT: 'An error occurred. Please try again.',
    _NETWORK: 'Cannot connect to server. Check your internet connection and try again.',
  };

  function messageFor(errorCode, fallbackMessage) {
    if (errorCode && ERROR_MESSAGES[errorCode]) return ERROR_MESSAGES[errorCode];
    if (fallbackMessage) return fallbackMessage;
    return ERROR_MESSAGES._DEFAULT;
  }

  /* --------------------------------------------------------
   * THAM CHIẾU DOM
   * -------------------------------------------------------- */
  const form = document.getElementById('login-form');
  const identifierInput = document.getElementById('login-identifier');
  const passwordInput = document.getElementById('login-password');
  const submitBtn = document.getElementById('login-submit');
  const alertBox = document.getElementById('auth-alert');
  const togglePasswordBtn = document.getElementById('toggle-password');

  if (!form) return; // an toàn nếu script nạp nhầm trang

  /* --------------------------------------------------------
   * HÀM TIỆN ÍCH UI
   * -------------------------------------------------------- */
  function showAlert(message) {
    alertBox.textContent = message;
    alertBox.classList.remove('hidden');
  }

  function hideAlert() {
    alertBox.textContent = '';
    alertBox.classList.add('hidden');
  }

  function clearFieldErrors() {
    form.querySelectorAll('.form-error').forEach(function (el) {
      el.textContent = '';
      el.classList.add('hidden');
    });
  }

  function setFieldError(fieldName, message) {
    const el = form.querySelector('[data-error-for="' + fieldName + '"]');
    if (el) {
      el.textContent = message;
      el.classList.remove('hidden');
    }
  }

  function setLoading(isLoading) {
    if (isLoading) {
      submitBtn.classList.add('is-loading');
      submitBtn.disabled = true;
    } else {
      submitBtn.classList.remove('is-loading');
      submitBtn.disabled = false;
    }
    const spinner = document.getElementById('login-spinner');
    if (spinner) spinner.classList.toggle('hidden', !isLoading);
  }

  /* --------------------------------------------------------
   * NÚT HIỆN / ẨN MẬT KHẨU
   * -------------------------------------------------------- */
  if (togglePasswordBtn) {
    togglePasswordBtn.addEventListener('click', function () {
      const isHidden = passwordInput.type === 'password';
      passwordInput.type = isHidden ? 'text' : 'password';
      togglePasswordBtn.setAttribute('aria-pressed', String(isHidden));
      togglePasswordBtn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
      togglePasswordBtn.querySelector('.icon-eye').classList.toggle('hidden', isHidden);
      togglePasswordBtn.querySelector('.icon-eye-off').classList.toggle('hidden', !isHidden);
    });
  }

  /* --------------------------------------------------------
   * VALIDATE PHÍA CLIENT (nhẹ, server vẫn validate lại)
   * -------------------------------------------------------- */
  function validateInput(identifier, password) {
    let ok = true;
    if (!identifier) {
      setFieldError('login', 'Please enter your phone number or email.');
      ok = false;
    }
    if (!password) {
      setFieldError('password', 'Please enter your password.');
      ok = false;
    }
    return ok;
  }

  /* --------------------------------------------------------
   * GỌI API (POST JSON), trả về object đã parse
   * -------------------------------------------------------- */
  async function postJson(url, payload) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });

    let data = null;
    try {
      data = await res.json();
    } catch (e) {
      // Server không trả JSON hợp lệ
      data = null;
    }
    return { httpStatus: res.status, data: data };
  }

  // Lấy login_challenge_id dù server bọc trong "data" hay để ở cấp ngoài.
  function pickChallengeId(data) {
    if (!data) return null;
    if (data.login_challenge_id) return data.login_challenge_id;
    if (data.data && data.data.login_challenge_id) return data.data.login_challenge_id;
    return null;
  }

  function pickField(data, key) {
    if (!data) return null;
    if (data[key] != null) return data[key];
    if (data.data && data.data[key] != null) return data.data[key];
    return null;
  }

  /* --------------------------------------------------------
   * BƯỚC 1: POST /api/auth/login
   * -------------------------------------------------------- */
  async function doLogin(identifier, password) {
    // NOTE: tên field "login" tùy theo AuthController của bạn.
    // Nếu controller mong đợi field khác (vd: phone, email, identifier),
    // hãy đổi key bên dưới cho khớp.
    const payload = {
      login: identifier,
      password: password,
    };

    const { data } = await postJson(ENDPOINTS.login, payload);

    if (!data) {
      throw { code: '_NETWORK' };
    }

    if (data.success === false) {
      // Hiển thị lỗi từng field nếu có (VALIDATION_ERROR)
      if (data.error_code === 'VALIDATION_ERROR' && data.errors) {
        Object.keys(data.errors).forEach(function (field) {
          setFieldError(field, data.errors[field]);
        });
      }
      throw { code: data.error_code, message: data.message };
    }

    const challengeId = pickChallengeId(data);
    if (!challengeId) {
      // Đăng nhập "thành công" nhưng thiếu challenge id -> không thể đi tiếp
      throw { code: '_DEFAULT', message: 'Không nhận được mã phiên đăng nhập từ máy chủ.' };
    }

    return challengeId;
  }

  /* --------------------------------------------------------
   * BƯỚC 3: POST /api/otp/request
   * -------------------------------------------------------- */
  async function requestOtp(challengeId) {
    const payload = { login_challenge_id: challengeId };

    const { data } = await postJson(ENDPOINTS.otpRequest, payload);

    if (!data) {
      throw { code: '_NETWORK' };
    }

    if (data.success === false) {
      throw { code: data.error_code, message: data.message };
    }

    return {
      expiresIn: pickField(data, 'expires_in'),
      smsProvider: pickField(data, 'sms_provider'),
    };
  }

  /* --------------------------------------------------------
   * SUBMIT FORM
   * -------------------------------------------------------- */
  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    hideAlert();
    clearFieldErrors();

    const identifier = identifierInput.value.trim();
    const password = passwordInput.value;

    if (!validateInput(identifier, password)) {
      return;
    }

    setLoading(true);

    try {
      // Bước 1 + 2: login & lưu challenge id
      const challengeId = await doLogin(identifier, password);
      sessionStorage.setItem(STORAGE_KEYS.challengeId, challengeId);
      sessionStorage.setItem(STORAGE_KEYS.identifier, identifier);

      // Bước 3: request OTP
      const otpInfo = await requestOtp(challengeId);
      if (otpInfo.expiresIn != null) {
        sessionStorage.setItem(STORAGE_KEYS.otpExpiresIn, String(otpInfo.expiresIn));
      }
      if (otpInfo.smsProvider) {
        sessionStorage.setItem(STORAGE_KEYS.smsProvider, otpInfo.smsProvider);
      }

      // Bước 4: chuyển sang trang OTP
      window.location.href = OTP_PAGE_URL;
    } catch (err) {
      const code = err && err.code ? err.code : '_DEFAULT';
      showAlert(messageFor(code, err && err.message));
      setLoading(false);
    }
  });

  // Khi người dùng gõ lại -> ẩn alert tổng cho đỡ rối.
  [identifierInput, passwordInput].forEach(function (input) {
    input.addEventListener('input', hideAlert);
  });
})();