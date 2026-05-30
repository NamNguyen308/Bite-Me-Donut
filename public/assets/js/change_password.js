document.addEventListener('DOMContentLoaded', async () => {
  'use strict';

  const CONFIG = window.CHANGE_PASSWORD_CONFIG || {};

  const CHANGE_PROXY_URL = CONFIG.changeProxyUrl || `${window.location.pathname}?change_ajax=1`;
  const LOGIN_URL = CONFIG.loginUrl || './user-login.php';
  const PROFILE_URL = CONFIG.profileUrl || '../user/customer_profile.php';
  const ORDERS_URL = CONFIG.ordersUrl || '../user/customer_orders.php';
  const CHANGE_PASSWORD_URL = CONFIG.changePasswordUrl || './change_password.php';

  const TOKEN_KEYS = ['access_token', 'auth_token'];
  const USER_KEYS = ['auth_user', 'current_user', 'user'];

  const pageLoader = document.getElementById('pageLoader');
  const authError = document.getElementById('authError');
  const authErrorMsg = document.getElementById('authErrorMsg');
  const cpContent = document.getElementById('cpContent');

  const cpForm = document.getElementById('cpForm');
  const cpSuccess = document.getElementById('cpSuccess');

  const currentPasswordInput = document.getElementById('currentPassword');
  const newPasswordInput = document.getElementById('newPassword');
  const confirmPasswordInput = document.getElementById('confirmPassword');

  const currentPasswordError = document.getElementById('currentPasswordError');
  const newPasswordError = document.getElementById('newPasswordError');
  const confirmPasswordError = document.getElementById('confirmPasswordError');

  const formAlert = document.getElementById('formAlert');

  const submitBtn = document.getElementById('submitBtn');
  const submitBtnText = document.getElementById('submitBtnText');
  const submitSpinner = document.getElementById('submitSpinner');

  const strengthFill = document.getElementById('strengthFill');
  const strengthLabel = document.getElementById('strengthLabel');

  const sidebarLogoutBtn = document.getElementById('sidebarLogoutBtn');
  const logoutModal = document.getElementById('logoutModal');
  const logoutModalClose = document.getElementById('logoutModalClose');
  const confirmLogoutBtn = document.getElementById('confirmLogoutBtn');
  const cancelLogoutBtn = document.getElementById('cancelLogoutBtn');

  function getToken() {
    for (const key of TOKEN_KEYS) {
      const value = localStorage.getItem(key) || sessionStorage.getItem(key);

      if (value) {
        return value;
      }
    }

    return null;
  }

  function clearSession() {
    TOKEN_KEYS.forEach((key) => {
      localStorage.removeItem(key);
      sessionStorage.removeItem(key);
    });

    USER_KEYS.forEach((key) => {
      localStorage.removeItem(key);
      sessionStorage.removeItem(key);
    });

    localStorage.removeItem('is_logged_in');

    sessionStorage.removeItem('login_challenge_id');
    sessionStorage.removeItem('login_identifier');
    sessionStorage.removeItem('otp_expires_in');
    sessionStorage.removeItem('sms_provider');
  }

  function saveUserToStorage(user) {
    if (!user) return;

    const userJson = JSON.stringify(user);

    USER_KEYS.forEach((key) => {
      localStorage.setItem(key, userJson);
    });
  }

  function redirectLogin() {
    clearSession();
    window.location.href = LOGIN_URL;
  }

  function hideElement(element) {
    element?.classList.add('hidden');
  }

  function showElement(element) {
    element?.classList.remove('hidden');
  }

  function showPageContent() {
    hideElement(pageLoader);
    hideElement(authError);
    showElement(cpContent);
  }

  function showAuthError(message) {
    hideElement(pageLoader);
    hideElement(cpContent);

    if (authErrorMsg) {
      authErrorMsg.textContent = message || 'You must be logged in to change your password.';
    }

    showElement(authError);
  }

  function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
      element.textContent = value || '—';
    }
  }

  function showFieldError(element, message) {
    if (!element) return;

    element.textContent = message;
    element.classList.remove('hidden');
  }

  function clearFieldError(element) {
    if (!element) return;

    element.textContent = '';
    element.classList.add('hidden');
  }

  function clearErrors() {
    clearFieldError(currentPasswordError);
    clearFieldError(newPasswordError);
    clearFieldError(confirmPasswordError);

    if (formAlert) {
      formAlert.textContent = '';
      formAlert.className = 'hidden';
    }
  }

  function showFormAlert(message, type = 'danger') {
    if (!formAlert) return;

    formAlert.textContent = message;
    formAlert.className = `alert alert--${type}`;
  }

  function setLoading(loading) {
    if (!submitBtn) return;

    submitBtn.disabled = loading;
    submitBtn.classList.toggle('is-loading', loading);

    if (submitBtnText) {
      submitBtnText.textContent = loading ? 'Updating...' : 'Update Password';
    }

    submitSpinner?.classList.toggle('hidden', !loading);
  }

  async function postChange(action, payload = {}) {
    const token = getToken();

    if (!token) {
      return {
        ok: false,
        status: 401,
        data: {
          success: false,
          error_code: 'UNAUTHENTICATED',
          message: 'Missing access token'
        }
      };
    }

    const requestBody = {
      action,
      access_token: token,
      ...payload
    };

    console.log('[CHANGE PASSWORD POST]', CHANGE_PROXY_URL, requestBody);

    const response = await fetch(CHANGE_PROXY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(requestBody)
    });

    const rawText = await response.text();

    console.log('[CHANGE PASSWORD STATUS]', response.status);
    console.log('[CHANGE PASSWORD RAW]', rawText);

    let data;

    try {
      data = JSON.parse(rawText);
    } catch (error) {
      return {
        ok: false,
        status: response.status,
        data: {
          success: false,
          error_code: 'INVALID_JSON_RESPONSE',
          message: 'Server returned invalid JSON response',
          raw_response: rawText
        }
      };
    }

    return {
      ok: response.ok && data.success !== false,
      status: response.status,
      data
    };
  }

  function extractUser(responseData) {
    if (!responseData) return null;

    if (responseData.data && responseData.data.user) {
      return responseData.data.user;
    }

    if (responseData.data && responseData.data.id) {
      return responseData.data;
    }

    if (responseData.user) {
      return responseData.user;
    }

    if (responseData.id) {
      return responseData;
    }

    return null;
  }

  function fillSidebarUser(user) {
    if (!user) return;

    const name = user.name || user.full_name || 'Donut Lover';
    const role = user.role || 'customer';

    setText('sidebarName', name);
    setText('sidebarRole', String(role).toUpperCase());

    saveUserToStorage(user);
  }

  async function loadCurrentUser() {
    const token = getToken();

    if (!token) {
      redirectLogin();
      return;
    }

    try {
      const response = await postChange('me');

      if (!response.ok) {
        showAuthError(response.data?.message || 'Your login session has expired. Please log in again.');

        setTimeout(() => {
          redirectLogin();
        }, 1200);

        return;
      }

      const user = extractUser(response.data);

      if (user) {
        fillSidebarUser(user);
      }

      showPageContent();
    } catch (error) {
      console.error('[change-password:me]', error);
      showAuthError('Cannot load account data. Please try again.');
    }
  }

  function passwordScore(password) {
    let score = 0;

    if (password.length >= 8) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    return score;
  }

  function updateTip(id, ok) {
    const item = document.getElementById(id);
    const dot = item?.querySelector('.cp-tip-dot');

    if (!item || !dot) return;

    dot.classList.remove('cp-tip-dot--neutral', 'cp-tip-dot--success', 'cp-tip-dot--danger');
    dot.classList.add(ok ? 'cp-tip-dot--success' : 'cp-tip-dot--neutral');
  }

  function updateStrength() {
    const password = newPasswordInput?.value || '';
    const confirm = confirmPasswordInput?.value || '';

    const score = passwordScore(password);

    const labels = ['Weak', 'Weak', 'Medium', 'Strong', 'Very strong'];
    const percents = ['0%', '25%', '50%', '75%', '100%'];

    if (strengthFill) {
      strengthFill.style.width = password ? percents[score] : '0%';
      strengthFill.dataset.level = String(score);
    }

    if (strengthLabel) {
      strengthLabel.textContent = password ? labels[score] : '';
      strengthLabel.dataset.level = String(score);
    }

    updateTip('tip-length', password.length >= 8);
    updateTip('tip-upper', /[A-Z]/.test(password));
    updateTip('tip-number', /[0-9]/.test(password));
    updateTip('tip-match', password !== '' && confirm !== '' && password === confirm);
  }

  function validateForm() {
    clearErrors();

    const currentPassword = currentPasswordInput?.value || '';
    const newPassword = newPasswordInput?.value || '';
    const confirmPassword = confirmPasswordInput?.value || '';

    let valid = true;

    if (!currentPassword) {
      showFieldError(currentPasswordError, 'Current password is required.');
      valid = false;
    }

    if (!newPassword || newPassword.length < 8) {
      showFieldError(newPasswordError, 'New password must be at least 8 characters.');
      valid = false;
    }

    if (!/[A-Z]/.test(newPassword)) {
      showFieldError(newPasswordError, 'New password must contain at least one uppercase letter.');
      valid = false;
    }

    if (!/[0-9]/.test(newPassword)) {
      showFieldError(newPasswordError, 'New password must contain at least one number.');
      valid = false;
    }

    if (!confirmPassword || newPassword !== confirmPassword) {
      showFieldError(confirmPasswordError, 'New passwords do not match.');
      valid = false;
    }

    if (currentPassword && newPassword && currentPassword === newPassword) {
      showFieldError(newPasswordError, 'New password must be different from current password.');
      valid = false;
    }

    return valid;
  }

  async function handleSubmit(event) {
    event.preventDefault();

    if (!validateForm()) {
      return;
    }

    setLoading(true);

    try {
      const response = await postChange('change_password', {
        current_password: currentPasswordInput.value,
        new_password: newPasswordInput.value,
        confirm_password: confirmPasswordInput.value
      });

      if (!response.ok) {
        const field = response.data?.field || null;
        const message = response.data?.message || 'Cannot change password.';

        if (field === 'current_password') {
          showFieldError(currentPasswordError, message);
        } else if (field === 'new_password') {
          showFieldError(newPasswordError, message);
        } else if (field === 'confirm_password') {
          showFieldError(confirmPasswordError, message);
        } else if (['TOKEN_INVALID', 'UNAUTHENTICATED', 'TOKEN_EXPIRED', 'TOKEN_REVOKED'].includes(response.data?.error_code)) {
          showAuthError(message);

          setTimeout(() => {
            redirectLogin();
          }, 1200);
        } else {
          showFormAlert(message, 'danger');
        }

        return;
      }

      /*
       * Important:
       * Do NOT clear localStorage.
       * Do NOT redirect login.
       * Current token remains valid.
       */

      cpForm?.reset();
      updateStrength();

      hideElement(cpForm);
      showElement(cpSuccess);

      showFormAlert('Password updated successfully. Your current session is still active.', 'success');
    } catch (error) {
      console.error('[change-password:submit]', error);
      showFormAlert('A network error occurred. Please try again.', 'danger');
    } finally {
      setLoading(false);
    }
  }

  function togglePassword(button) {
    const targetId = button.dataset.target;
    const input = document.getElementById(targetId);

    if (!input) return;

    const isHidden = input.type === 'password';

    input.type = isHidden ? 'text' : 'password';

    button.querySelector('.icon-eye')?.classList.toggle('hidden', isHidden);
    button.querySelector('.icon-eye-off')?.classList.toggle('hidden', !isHidden);
  }

  function openLogoutModal() {
    logoutModal?.classList.add('is-active');
    logoutModal?.classList.add('is-open');
  }

  function closeLogoutModal() {
    logoutModal?.classList.remove('is-active');
    logoutModal?.classList.remove('is-open');
  }

  async function logout() {
    if (confirmLogoutBtn) {
      confirmLogoutBtn.disabled = true;
      confirmLogoutBtn.textContent = 'Logging out...';
    }

    try {
      await postChange('logout');
    } catch (error) {
      console.warn('[change-password:logout]', error);
    }

    clearSession();
    window.location.href = LOGIN_URL;
  }

  function wireSidebarLinks() {
    const profileLink = document.querySelector('[data-page="profile"]');
    const ordersLink = document.querySelector('[data-page="orders"]');
    const changePasswordLink = document.querySelector('[data-page="change-password"]');

    if (profileLink) profileLink.href = PROFILE_URL;
    if (ordersLink) ordersLink.href = ORDERS_URL;
    if (changePasswordLink) changePasswordLink.href = CHANGE_PASSWORD_URL;
  }

  function bindEvents() {
    cpForm?.addEventListener('submit', handleSubmit);

    newPasswordInput?.addEventListener('input', updateStrength);
    confirmPasswordInput?.addEventListener('input', updateStrength);

    document.querySelectorAll('.cp-toggle-btn').forEach((button) => {
      button.addEventListener('click', () => togglePassword(button));
    });

    sidebarLogoutBtn?.addEventListener('click', openLogoutModal);
    logoutModalClose?.addEventListener('click', closeLogoutModal);
    cancelLogoutBtn?.addEventListener('click', closeLogoutModal);
    confirmLogoutBtn?.addEventListener('click', logout);

    logoutModal?.addEventListener('click', (event) => {
      if (event.target === logoutModal) {
        closeLogoutModal();
      }
    });
  }

  wireSidebarLinks();
  bindEvents();
  await loadCurrentUser();
});