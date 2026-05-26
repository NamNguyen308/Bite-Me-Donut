/* ============================================================
 * USER-REGISTER.JS — Xử lý đăng ký khách hàng
 * ============================================================ */

(function () {
  'use strict';

  /* --------------------------------------------------------
   * CẤU HÌNH
   * -------------------------------------------------------- */
  const API_BASE = '';

  const ENDPOINTS = {
    register: API_BASE + '/api/auth/register',
  };

  const LOGIN_PAGE_URL = 'user-login.php';

  /* --------------------------------------------------------
   * BẢN ĐỒ error_code -> thông báo tiếng Việt
   * -------------------------------------------------------- */
  const ERROR_MESSAGES = {
    VALIDATION_ERROR: 'Information is invalid. Please try again.',
    INTERNAL_SERVER_ERROR: 'Server error. Please try again later.',
    PHONE_ALREADY_EXISTS: 'Phone number already exists.',
    EMAIL_ALREADY_EXISTS: 'Email already exists.',
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
  const form = document.getElementById('register-form');
  const identifierInput = document.getElementById('register-identifier');
  const passwordInput = document.getElementById('register-password');
  const confirmPasswordInput = document.getElementById('register-confirm-password');
  const submitBtn = document.getElementById('register-submit');
  const alertBox = document.getElementById('auth-alert');
  const togglePasswordBtns = document.querySelectorAll('.toggle-password');

  if (!form) return;

  /* --------------------------------------------------------
   * HÀM TIỆN ÍCH UI
   * -------------------------------------------------------- */
  function showAlert(message, isSuccess = false) {
    alertBox.textContent = message;
    alertBox.classList.remove('hidden', 'alert--danger', 'alert--success');
    alertBox.classList.add(isSuccess ? 'alert--success' : 'alert--danger');
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
    const spinner = document.getElementById('register-spinner');
    if (spinner) spinner.classList.toggle('hidden', !isLoading);
  }

  /* --------------------------------------------------------
   * NÚT HIỆN / ẨN MẬT KHẨU
   * -------------------------------------------------------- */
  togglePasswordBtns.forEach(btn => {
    btn.addEventListener('click', function () {
      const input = this.previousElementSibling;
      const isHidden = input.type === 'password';
      input.type = isHidden ? 'text' : 'password';
      this.setAttribute('aria-pressed', String(isHidden));
      this.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
      this.querySelector('.icon-eye').classList.toggle('hidden', isHidden);
      this.querySelector('.icon-eye-off').classList.toggle('hidden', !isHidden);
    });
  });

  /* --------------------------------------------------------
   * VALIDATE PHÍA CLIENT
   * -------------------------------------------------------- */
  function validateInput(identifier, password, confirmPassword) {
    let ok = true;
    if (!identifier) {
      setFieldError('identifier', 'Vui lòng nhập số điện thoại hoặc email.');
      ok = false;
    }
    if (!password) {
      setFieldError('password', 'Vui lòng nhập mật khẩu.');
      ok = false;
    } else if (password.length < 6) {
      setFieldError('password', 'Mật khẩu phải có ít nhất 6 ký tự.');
      ok = false;
    }
    if (!confirmPassword) {
      setFieldError('confirm_password', 'Vui lòng xác nhận mật khẩu.');
      ok = false;
    } else if (password !== confirmPassword) {
      setFieldError('confirm_password', 'Mật khẩu xác nhận không khớp.');
      ok = false;
    }
    return ok;
  }

  /* --------------------------------------------------------
   * GỌI API (POST JSON)
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
      data = null;
    }
    return { httpStatus: res.status, data: data };
  }

  /* --------------------------------------------------------
   * BƯỚC ĐĂNG KÝ: POST /api/auth/register
   * -------------------------------------------------------- */
  async function doRegister(identifier, password) {
    // Parser email/phone từ identifier
    const isEmail = identifier.includes('@');

    // API yêu cầu trường name, ta tạo name mặc định từ identifier
    const defaultName = identifier.split('@')[0];

    const payload = {
      name: defaultName,
      password: password
    };

    if (isEmail) {
      payload.email = identifier;
      // Tuỳ thuộc API, nếu bắt buộc phone, ta truyền default phone hoặc mong backend cho phép email null phone
    } else {
      payload.phone = identifier;
    }

    const { data } = await postJson(ENDPOINTS.register, payload);

    if (!data) {
      throw { code: '_NETWORK' };
    }

    if (data.success === false) {
      if (data.error_code === 'VALIDATION_ERROR' && data.errors) {
        Object.keys(data.errors).forEach(function (field) {
          // Map error về đúng field giao diện
          if (field === 'phone' || field === 'email') {
            setFieldError('identifier', data.errors[field]);
          } else {
            setFieldError(field, data.errors[field]);
          }
        });
      }
      throw { code: data.error_code, message: data.message };
    }

    return true;
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
    const confirmPassword = confirmPasswordInput.value;

    if (!validateInput(identifier, password, confirmPassword)) {
      return;
    }

    setLoading(true);

    try {
      await doRegister(identifier, password);
      showAlert('Đăng ký thành công! Đang chuyển hướng...', true);
      setTimeout(() => {
        window.location.href = LOGIN_PAGE_URL;
      }, 1500);
    } catch (err) {
      const code = err && err.code ? err.code : '_DEFAULT';
      showAlert(messageFor(code, err && err.message));
      setLoading(false);
    }
  });

  [identifierInput, passwordInput, confirmPasswordInput].forEach(function (input) {
    input.addEventListener('input', hideAlert);
  });
})();
