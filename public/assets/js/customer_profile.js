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

  const editBtn = document.getElementById('editBtn');
  const cancelEditBtn = document.getElementById('cancelEditBtn');
  const infoView = document.getElementById('infoView');
  const editForm = document.getElementById('editForm');

  const editName = document.getElementById('editName');
  const editEmail = document.getElementById('editEmail');
  const editPhone = document.getElementById('editPhone');

  const saveProfileBtn =
    document.getElementById('saveProfileBtn') ||
    editForm?.querySelector('button[type="submit"]');

  let currentUser = null;

  function getToken() {
    for (const key of TOKEN_KEYS) {
      const value = localStorage.getItem(key);

      if (value) {
        return value;
      }
    }

    return null;
  }

  function saveUserToStorage(user) {
    if (!user) return;

    const userJson = JSON.stringify(user);

    USER_KEYS.forEach((key) => {
      localStorage.setItem(key, userJson);
    });
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

  function setValue(id, value) {
    const el = document.getElementById(id);

    if (el) {
      el.value = value || '';
    }
  }

  function hideElement(el) {
    if (el) {
      el.classList.add('hidden');
    }
  }

  function showElement(el) {
    if (el) {
      el.classList.remove('hidden');
    }
  }

  function showError(message) {
    hideElement(pageLoader);
    hideElement(profileContent);

    if (profileErrorMsg) {
      profileErrorMsg.textContent = message || 'Cannot load profile. Please log in again.';
    }

    showElement(profileError);
  }

  function hideError() {
    hideElement(profileError);
  }

  function showContent() {
    hideElement(pageLoader);
    hideElement(profileError);
    showElement(profileContent);
  }

  function setButtonLoading(button, loading, loadingText = 'Saving...') {
    if (!button) return;

    if (loading) {
      button.dataset.originalText = button.textContent;
      button.textContent = loadingText;
      button.disabled = true;
      button.classList.add('is-loading');
    } else {
      button.textContent = button.dataset.originalText || button.textContent;
      button.disabled = false;
      button.classList.remove('is-loading');
    }
  }

  function showToast(message, type = 'success') {
    const existingToast = document.getElementById('profileToast');

    if (existingToast) {
      existingToast.remove();
    }

    const toast = document.createElement('div');
    toast.id = 'profileToast';
    toast.textContent = message;
    toast.style.position = 'fixed';
    toast.style.right = '24px';
    toast.style.bottom = '24px';
    toast.style.zIndex = '9999';
    toast.style.padding = '14px 18px';
    toast.style.borderRadius = '10px';
    toast.style.fontWeight = '600';
    toast.style.boxShadow = '0 8px 24px rgba(0,0,0,0.16)';

    if (type === 'success') {
      toast.style.background = '#e8f7ee';
      toast.style.color = '#137333';
      toast.style.border = '1px solid #b7e3c5';
    } else {
      toast.style.background = '#fdeaea';
      toast.style.color = '#b42318';
      toast.style.border = '1px solid #f2b8b5';
    }

    document.body.appendChild(toast);

    setTimeout(() => {
      toast.remove();
    }, 2500);
  }

  async function postProfile(action, payload = {}) {
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

    console.log('[PROFILE POST]', PROFILE_PROXY_URL, requestBody);

    const response = await fetch(PROFILE_PROXY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(requestBody)
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

  function normalizeUser(user) {
    return {
      ...user,
      name: user.name || user.full_name || 'Donut Lover',
      email: user.email || '',
      phone: user.phone || '',
      role: user.role || 'customer',
      is_active: user.is_active,
      created_at: user.created_at || user.createdAt || ''
    };
  }

  function fillProfile(user) {
    if (!user) return;

    const normalizedUser = normalizeUser(user);

    currentUser = normalizedUser;

    const name = normalizedUser.name;
    const email = normalizedUser.email || '—';
    const phone = normalizedUser.phone || '—';
    const role = normalizedUser.role || 'customer';
    const status =
      Number(normalizedUser.is_active) === 1 || normalizedUser.is_active === true
        ? 'Active'
        : 'Inactive';
    const createdAt = normalizedUser.created_at || '';

    setText('sidebarName', name);
    setText('sidebarRole', String(role).toUpperCase());

    setText('heroName', name);
    setText('heroRole', String(role).toUpperCase());
    setText('heroJoin', createdAt ? `Joined ${formatDate(createdAt)}` : '—');
    setText('heroPhone', phone);

    setText('infoName', name);
    setText('infoEmail', email);
    setText('infoPhone', phone);
    setText('infoRole', String(role).toUpperCase());
    setText('infoStatus', status);
    setText('infoCreated', formatDate(createdAt));

    setValue('editName', name);
    setValue('editEmail', email === '—' ? '' : email);
    setValue('editPhone', phone === '—' ? '' : phone);

    saveUserToStorage(normalizedUser);
    showContent();
  }

  async function loadProfile() {
    const token = getToken();

    if (!token) {
      redirectLogin();
      return;
    }

    try {
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
    } catch (error) {
      console.error('[profile:load]', error);
      showError('A network error occurred. Please try again.');
    }
  }

  function openEditMode() {
    if (currentUser) {
      const user = normalizeUser(currentUser);

      setValue('editName', user.name);
      setValue('editEmail', user.email);
      setValue('editPhone', user.phone);
    }

    hideElement(infoView);
    showElement(editForm);
  }

  function closeEditMode() {
    hideElement(editForm);
    showElement(infoView);
  }

  function validateProfileForm(name, email) {
    if (!name || name.length < 2) {
      showToast('Full name is required.', 'danger');
      editName?.focus();
      return false;
    }

    if (!email) {
      showToast('Email is required.', 'danger');
      editEmail?.focus();
      return false;
    }

    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailPattern.test(email)) {
      showToast('Please enter a valid email address.', 'danger');
      editEmail?.focus();
      return false;
    }

    return true;
  }

  async function handleSaveProfile(event) {
    event.preventDefault();

    const name = editName?.value.trim() || '';
    const email = editEmail?.value.trim() || '';

    if (!validateProfileForm(name, email)) {
      return;
    }

    setButtonLoading(saveProfileBtn, true, 'Saving...');

    try {
      const response = await postProfile('update_profile', {
        name,
        email
      });

      if (!response.ok) {
        showToast(response.data?.message || 'Cannot update profile.', 'danger');
        return;
      }

      const updatedUser =
        response.data?.data?.user ||
        response.data?.user ||
        {
          ...(currentUser || {}),
          name,
          email
        };

      fillProfile(updatedUser);
      closeEditMode();

      showToast('Profile updated successfully.', 'success');
    } catch (error) {
      console.error('[profile:update]', error);
      showToast('A network error occurred. Please try again.', 'danger');
    } finally {
      setButtonLoading(saveProfileBtn, false);
    }
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

  function bindEvents() {
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

    editBtn?.addEventListener('click', openEditMode);
    cancelEditBtn?.addEventListener('click', closeEditMode);
    editForm?.addEventListener('submit', handleSaveProfile);

    const backHomeBtn = document.getElementById('backHomeBtn');

    backHomeBtn?.addEventListener('click', () => {
      window.location.href = HOME_URL;
    });
  }

  bindEvents();
  await loadProfile();
});