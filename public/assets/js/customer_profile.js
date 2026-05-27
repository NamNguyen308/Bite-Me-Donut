document.addEventListener('DOMContentLoaded', async () => {
  'use strict';

  const CONFIG = window.CUSTOMER_PROFILE_CONFIG || {};

  const PROFILE_PROXY_URL = CONFIG.profileProxyUrl || `${window.location.pathname}?profile_ajax=1`;
  const LOGIN_URL = CONFIG.loginUrl || '../auth/user-login.php';
  const HOME_URL = CONFIG.homeUrl || './home.php';

  const TOKEN_KEYS = ['access_token', 'auth_token'];
  const USER_KEYS = ['auth_user', 'current_user', 'user'];

  const pageLoader = document.getElementById('pageLoader');
  const profileError = document.getElementById('profileError');
  const profileErrorMsg = document.getElementById('profileErrorMsg');
  const profileContent = document.getElementById('profileContent');

  const sidebarLogoutBtn = document.getElementById('sidebarLogoutBtn');
  const revokeBtn = document.getElementById('revokeBtn');
  const confirmLogoutBtn = document.getElementById('confirmLogoutBtn');
  const cancelLogoutBtn = document.getElementById('cancelLogoutBtn');
  const logoutModalClose = document.getElementById('logoutModalClose');
  const logoutModal = document.getElementById('logoutModal');

  function getToken() {
    for (const key of TOKEN_KEYS) {
      const value = localStorage.getItem(key);

      if (value) {
        return value;
      }
    }

    return null;
  }

  function clearSession() {
    TOKEN_KEYS.forEach((key) => localStorage.removeItem(key));
    USER_KEYS.forEach((key) => localStorage.removeItem(key));

    localStorage.removeItem('is_logged_in');

    sessionStorage.removeItem('login_challenge_id');
    sessionStorage.removeItem('login_identifier');
    sessionStorage.removeItem('otp_expires_in');
    sessionStorage.removeItem('sms_provider');
  }

  function redirectLogin() {
    clearSession();
    window.location.href = LOGIN_URL;
  }

  function setText(id, value) {
    const el = document.getElementById(id);

    if (el) {
      el.textContent = value || '—';
    }
  }

  function showError(message) {
    if (pageLoader) pageLoader.classList.add('hidden');
    if (profileContent) profileContent.classList.add('hidden');

    if (profileErrorMsg) {
      profileErrorMsg.textContent = message || 'Cannot load profile. Please log in again.';
    }

    if (profileError) {
      profileError.classList.remove('hidden');
    }
  }

  function hideLoader() {
    if (pageLoader) {
      pageLoader.classList.add('hidden');
    }
  }

  async function postProfile(action) {
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

    const response = await fetch(PROFILE_PROXY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        action,
        access_token: token
      })
    });

    const rawText = await response.text();

    console.log('[PROFILE STATUS]', response.status);
    console.log('[PROFILE RAW]', rawText);

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

  function formatDate(value) {
    if (!value) return '—';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
      return value;
    }

    return date.toLocaleDateString('vi-VN');
  }

  function fillProfile(user) {
    const name = user.name || user.full_name || 'Donut Lover';
    const email = user.email || '—';
    const phone = user.phone || '—';
    const role = user.role || 'customer';
    const status = Number(user.is_active) === 1 || user.is_active === true ? 'Active' : 'Inactive';
    const createdAt = user.created_at || user.createdAt || '';

    setText('sidebarName', name);
    setText('sidebarRole', String(role).toUpperCase());

    setText('heroName', name);
    setText('heroRole', String(role).toUpperCase());
    setText('heroJoin', createdAt ? `Joined ${formatDate(createdAt)}` : '');
    setText('heroPhone', phone);

    setText('infoName', name);
    setText('infoEmail', email);
    setText('infoPhone', phone);
    setText('infoRole', String(role).toUpperCase());
    setText('infoStatus', status);
    setText('infoCreated', formatDate(createdAt));

    const editName = document.getElementById('editName');
    const editEmail = document.getElementById('editEmail');
    const editPhone = document.getElementById('editPhone');

    if (editName) editName.value = name;
    if (editEmail) editEmail.value = email === '—' ? '' : email;
    if (editPhone) editPhone.value = phone === '—' ? '' : phone;

    const userJson = JSON.stringify(user);

    USER_KEYS.forEach((key) => {
      localStorage.setItem(key, userJson);
    });

    if (pageLoader) pageLoader.classList.add('hidden');
    if (profileError) profileError.classList.add('hidden');
    if (profileContent) profileContent.classList.remove('hidden');
  }

  async function loadProfile() {
    const token = getToken();

    if (!token) {
      redirectLogin();
      return;
    }

    const response = await postProfile('me');

    if (!response.ok) {
      showError(response.data?.message || 'Your login session has expired. Please log in again.');

      setTimeout(() => {
        redirectLogin();
      }, 1200);

      return;
    }

    const user = extractUser(response.data);

    if (!user) {
      showError('Cannot read user profile.');
      return;
    }

    fillProfile(user);
    hideLoader();
  }

  function openLogoutModal() {
    if (logoutModal) {
      logoutModal.classList.add('is-active');
    }
  }

  function closeLogoutModal() {
    if (logoutModal) {
      logoutModal.classList.remove('is-active');
    }
  }

  async function logout() {
    try {
      await postProfile('logout');
    } catch (error) {
      console.warn('[profile:logout]', error);
    }

    clearSession();
    window.location.href = LOGIN_URL;
  }

  sidebarLogoutBtn?.addEventListener('click', openLogoutModal);
  revokeBtn?.addEventListener('click', openLogoutModal);
  cancelLogoutBtn?.addEventListener('click', closeLogoutModal);
  logoutModalClose?.addEventListener('click', closeLogoutModal);
  confirmLogoutBtn?.addEventListener('click', logout);

  logoutModal?.addEventListener('click', (event) => {
    if (event.target === logoutModal) {
      closeLogoutModal();
    }
  });

  const editBtn = document.getElementById('editBtn');
  const cancelEditBtn = document.getElementById('cancelEditBtn');
  const infoView = document.getElementById('infoView');
  const editForm = document.getElementById('editForm');

  editBtn?.addEventListener('click', () => {
    infoView?.classList.add('hidden');
    editForm?.classList.remove('hidden');
  });

  cancelEditBtn?.addEventListener('click', () => {
    editForm?.classList.add('hidden');
    infoView?.classList.remove('hidden');
  });

  await loadProfile();
});