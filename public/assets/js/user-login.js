(function () {
  'use strict';

  const CONFIG = window.USER_LOGIN_CONFIG || {};

  const LOGIN_PROXY_URL = CONFIG.loginProxyUrl || `${window.location.pathname}?login_ajax=1`;
  const OTP_PAGE_URL = CONFIG.otpPageUrl || './otp.php';
  const ADMIN_DASHBOARD_URL = CONFIG.adminDashboardUrl || `${getAppBasePath()}/views/admin/dashboard.php`;

  const STORAGE_KEYS = {
    challengeId: 'login_challenge_id',
    identifier: 'login_identifier'
  };

  const TOKEN_KEYS = ['access_token', 'auth_token', 'bmd_access_token'];
  const USER_KEYS = ['auth_user', 'current_user', 'user', 'bmd_user'];

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

  function getAppBasePath() {
    const path = window.location.pathname;

    if (path.includes('/views/')) {
      return path.split('/views/')[0];
    }

    if (path.includes('/public/')) {
      return path.split('/public/')[0];
    }

    return '';
  }

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

    const text = submitBtn.querySelector('[data-submit-text]');

    if (text) {
      text.textContent = isLoading ? 'Logging in...' : 'Login';
    }
  }

  function normalizeIdentifier(identifier) {
    const value = String(identifier || '').trim();

    if (value.includes('@')) {
      return value;
    }

    return value.replace(/[\s\-\(\)]/g, '');
  }

  function buildLoginPayload(identifier, password) {
    const payload = {
      identifier,
      password
    };

    if (identifier.includes('@')) {
      payload.email = identifier;
      payload.phone = '';
    } else {
      payload.phone = identifier;
      payload.email = '';
    }

    return payload;
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

  function clearLoginChallenge() {
    sessionStorage.removeItem(STORAGE_KEYS.challengeId);
    sessionStorage.removeItem(STORAGE_KEYS.identifier);
    sessionStorage.removeItem('otp_expires_in');
    sessionStorage.removeItem('sms_provider');
  }

  function saveAuthenticatedSession(accessToken, user) {
    if (!accessToken) {
      throw {
        code: '_DEFAULT',
        message: 'Access token was not returned by server.'
      };
    }

    TOKEN_KEYS.forEach(function (key) {
      localStorage.setItem(key, accessToken);
    });

    localStorage.setItem('is_logged_in', '1');

    if (user) {
      const userJson = JSON.stringify(user);

      USER_KEYS.forEach(function (key) {
        localStorage.setItem(key, userJson);
      });
    }
  }

  function extractResponseData(response) {
    return response?.data || response || {};
  }

  function extractUser(responseData) {
    if (!responseData) return null;

    if (responseData.user) {
      return responseData.user;
    }

    if (responseData.data && responseData.data.user) {
      return responseData.data.user;
    }

    return null;
  }

  function extractAccessToken(responseData) {
    return (
      responseData.access_token ||
      responseData.token ||
      responseData.accessToken ||
      responseData.data?.access_token ||
      responseData.data?.token ||
      null
    );
  }

  function pickChallengeId(responseData) {
    if (!responseData) return null;

    return (
      responseData.login_challenge_id ||
      responseData.challenge_id ||
      responseData.data?.login_challenge_id ||
      responseData.data?.challenge_id ||
      responseData.challenge?.id ||
      null
    );
  }

  function shouldUseOtp(responseData) {
    if (responseData.requires_otp === true || responseData.requiresOtp === true) {
      return true;
    }

    return !!pickChallengeId(responseData);
  }

  async function postLogin(identifier, password) {
    const payload = buildLoginPayload(identifier, password);

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
      const response = await postLogin(identifier, password);
      const responseData = extractResponseData(response);
      const user = extractUser(responseData);
      const accessToken = extractAccessToken(responseData);

      /*
       * ADMIN FLOW:
       * Admin login thành công sẽ có role admin + access_token.
       * Không OTP.
       */
      if (user && user.role === 'admin') {
        saveAuthenticatedSession(accessToken, user);
        clearLoginChallenge();

        window.location.href = ADMIN_DASHBOARD_URL;
        return;
      }

      /*
       * CUSTOMER FLOW:
       * Customer phải có login_challenge_id để qua OTP.
       */
      if (!shouldUseOtp(responseData)) {
        throw {
          code: '_DEFAULT',
          message: 'Login response is missing OTP challenge data.'
        };
      }

      const challengeId = pickChallengeId(responseData);

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
    input?.addEventListener('input', hideAlert);
  });
})();