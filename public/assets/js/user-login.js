(function () {
  'use strict';

  const CONFIG = window.USER_LOGIN_CONFIG || {};

  const LOGIN_PROXY_URL = CONFIG.loginProxyUrl || `${window.location.pathname}?login_ajax=1`;
  const OTP_PAGE_URL = CONFIG.otpPageUrl || './otp.php';

  const STORAGE_KEYS = {
    challengeId: 'login_challenge_id',
    identifier: 'login_identifier'
  };

  const ERROR_MESSAGES = {
    VALIDATION_ERROR: 'Information is invalid. Please try again.',
    INVALID_CREDENTIALS: 'Invalid phone/email or password.',
    ACCOUNT_INACTIVE: 'Account is inactive. Please contact support.',
    LOGIN_CHALLENGE_BLOCKED: 'Login session is temporarily blocked due to suspicious activity.',
    RISK_LEVEL_HIGH: 'Unusual activity detected. Login operation temporarily blocked.',
    API_PROXY_CURL_ERROR: 'Cannot connect to backend API.',
    API_PROXY_INVALID_JSON: 'Backend API returned invalid response.',
    _DEFAULT: 'An error occurred. Please try again.',
    _NETWORK: 'Cannot connect to server. Please try again.'
  };

  const form = document.getElementById('login-form');
  const identifierInput = document.getElementById('login-identifier');
  const passwordInput = document.getElementById('login-password');
  const submitBtn = document.getElementById('login-submit');
  const alertBox = document.getElementById('auth-alert');
  const togglePasswordBtn = document.getElementById('toggle-password');

  if (!form) return;

  function messageFor(errorCode, fallbackMessage) {
    if (errorCode && ERROR_MESSAGES[errorCode]) {
      return ERROR_MESSAGES[errorCode];
    }

    return fallbackMessage || ERROR_MESSAGES._DEFAULT;
  }

  function showAlert(message) {
    if (!alertBox) return;

    alertBox.textContent = message;
    alertBox.classList.remove('hidden');
  }

  function hideAlert() {
    if (!alertBox) return;

    alertBox.textContent = '';
    alertBox.classList.add('hidden');
  }

  function clearFieldErrors() {
    form.querySelectorAll('.form-error').forEach(function (element) {
      element.textContent = '';
      element.classList.add('hidden');
    });
  }

  function setFieldError(fieldName, message) {
    const element = form.querySelector('[data-error-for="' + fieldName + '"]');

    if (element) {
      element.textContent = message;
      element.classList.remove('hidden');
    }
  }

  function setLoading(isLoading) {
    if (!submitBtn) return;

    submitBtn.disabled = isLoading;
    submitBtn.classList.toggle('is-loading', isLoading);

    const spinner = document.getElementById('login-spinner');

    if (spinner) {
      spinner.classList.toggle('hidden', !isLoading);
    }
  }

  if (togglePasswordBtn) {
    togglePasswordBtn.addEventListener('click', function () {
      const isHidden = passwordInput.type === 'password';

      passwordInput.type = isHidden ? 'text' : 'password';

      togglePasswordBtn.setAttribute('aria-pressed', String(isHidden));
      togglePasswordBtn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');

      togglePasswordBtn.querySelector('.icon-eye')?.classList.toggle('hidden', isHidden);
      togglePasswordBtn.querySelector('.icon-eye-off')?.classList.toggle('hidden', !isHidden);
    });
  }

  function normalizeIdentifier(identifier) {
    const value = String(identifier || '').trim();

    if (value.includes('@')) {
      return value;
    }

    return value.replace(/[\s\-\(\)]/g, '');
  }

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

  async function postLogin(identifier, password) {
    const payload = {
      identifier,
      password
    };

    console.log('[LOGIN POST]', LOGIN_PROXY_URL, payload);

    const response = await fetch(LOGIN_PROXY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    const rawText = await response.text();

    console.log('[LOGIN STATUS]', response.status);
    console.log('[LOGIN RAW]', rawText);

    let data;

    try {
      data = JSON.parse(rawText);
    } catch (error) {
      throw {
        code: 'API_PROXY_INVALID_JSON',
        message: 'Server returned invalid JSON response.'
      };
    }

    if (!response.ok || data.success === false) {
      throw {
        code: data.error_code || '_DEFAULT',
        message: data.message || ERROR_MESSAGES._DEFAULT,
        errors: data.errors || {}
      };
    }

    return data;
  }

  function pickChallengeId(data) {
    if (!data) return null;
    if (data.login_challenge_id) return data.login_challenge_id;
    if (data.data && data.data.login_challenge_id) return data.data.login_challenge_id;
    return null;
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();

    hideAlert();
    clearFieldErrors();

    const identifier = normalizeIdentifier(identifierInput.value);
    const password = passwordInput.value;

    if (!validateInput(identifier, password)) {
      return;
    }

    setLoading(true);

    try {
      const data = await postLogin(identifier, password);
      const challengeId = pickChallengeId(data);

      if (!challengeId) {
        throw {
          code: '_DEFAULT',
          message: 'Login challenge ID was not returned by server.'
        };
      }

      sessionStorage.setItem(STORAGE_KEYS.challengeId, challengeId);
      sessionStorage.setItem(STORAGE_KEYS.identifier, identifier);

      window.location.href = OTP_PAGE_URL;
    } catch (error) {
      if (error.errors) {
        Object.keys(error.errors).forEach(function (field) {
          setFieldError(field, error.errors[field]);
        });
      }

      showAlert(messageFor(error.code, error.message));
      setLoading(false);
    }
  });

  [identifierInput, passwordInput].forEach(function (input) {
    input.addEventListener('input', hideAlert);
  });
})();