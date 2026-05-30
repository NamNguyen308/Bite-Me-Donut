document.addEventListener('DOMContentLoaded', async () => {
  'use strict';

  const CONFIG = window.HOME_CONFIG || {};

  const HOME_PROXY_URL = CONFIG.homeProxyUrl || `${window.location.pathname}?home_ajax=1`;
  const LOGIN_URL = CONFIG.loginUrl || '../auth/user-login.php';
  const PROFILE_URL = CONFIG.profileUrl || CONFIG.accountUrl || './customer_profile.php';

  const TOKEN_KEYS = ['access_token', 'auth_token'];
  const USER_KEYS = ['auth_user', 'current_user', 'user'];

  function getToken() {
    for (const key of TOKEN_KEYS) {
      const value = localStorage.getItem(key);

      if (value) {
        return value;
      }
    }

    return null;
  }

  function saveToken(token) {
    if (!token) return;

    localStorage.setItem('access_token', token);
    localStorage.setItem('auth_token', token);
    localStorage.setItem('is_logged_in', '1');
  }

  function saveUser(user) {
    if (!user) return;

    const json = JSON.stringify(user);

    USER_KEYS.forEach((key) => {
      localStorage.setItem(key, json);
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

  function getCachedUser() {
    for (const key of USER_KEYS) {
      const raw = localStorage.getItem(key);

      if (!raw) continue;

      try {
        const user = JSON.parse(raw);

        if (user && typeof user === 'object') {
          return user;
        }
      } catch (error) {
        localStorage.removeItem(key);
      }
    }

    return null;
  }

  function getDisplayName(user) {
    return String(user?.name || user?.full_name || user?.email || user?.phone || '').trim();
  }

  function getShortName(user) {
    const displayName = getDisplayName(user);

    if (!displayName) {
      return 'Bạn';
    }

    if (displayName.includes('@')) {
      return displayName.split('@')[0];
    }

    const parts = displayName.split(/\s+/);

    return parts[parts.length - 1] || displayName;
  }

  function setText(element, value) {
    if (element) {
      element.textContent = value;
    }
  }

  async function postHome(action, payload = {}) {
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

    console.log('[HOME POST]', HOME_PROXY_URL, requestBody);

    const response = await fetch(HOME_PROXY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(requestBody)
    });

    const rawText = await response.text();

    console.log('[HOME STATUS]', response.status);
    console.log('[HOME RAW]', rawText);

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

  function updateNavbarAccount(user = null) {
    const token = getToken();

    const accountLink = document.getElementById('nav-account-link');
    const accountLabel = document.getElementById('nav-account-label');
    const cartLink = document.getElementById('nav-cart-link');

    if (accountLink) {
      const loginUrl = accountLink.dataset.loginUrl || LOGIN_URL;
      const profileUrl = accountLink.dataset.profileUrl || PROFILE_URL;

      if (token) {
        const shortName = getShortName(user || getCachedUser());

        accountLink.href = profileUrl;
        accountLink.title = 'Tài khoản của tôi';
        accountLink.dataset.authState = 'authenticated';

        if (accountLabel) {
          accountLabel.textContent = `Xin chào ${shortName}`;
        }
      } else {
        accountLink.href = loginUrl;
        accountLink.title = 'Đăng nhập';
        accountLink.dataset.authState = 'guest';

        if (accountLabel) {
          accountLabel.textContent = '';
        }
      }
    }

    if (cartLink) {
      const loginUrl = cartLink.dataset.loginUrl || LOGIN_URL;
      const cartUrl = cartLink.dataset.cartUrl || './cart.php';

      if (token) {
        cartLink.href = cartUrl;
        cartLink.dataset.authState = 'authenticated';
      } else {
        cartLink.href = loginUrl;
        cartLink.dataset.authState = 'guest';
      }
    }

    if (typeof window.updateGlobalHeaderAuth === 'function') {
      window.updateGlobalHeaderAuth(user || getCachedUser());
    }
  }

  function hideAccountPanel() {
    const accountPanel = document.getElementById('account');

    if (accountPanel) {
      accountPanel.style.display = 'none';
      accountPanel.innerHTML = '';
    }

    const possibleAccountCards = document.querySelectorAll(
      '#user-profile-email, #user-profile-role, #home-panel-logout'
    );

    possibleAccountCards.forEach((element) => {
      const section = element.closest('section');

      if (section) {
        section.style.display = 'none';
      }
    });
  }

  function renderLandingHome() {
    const welcomeName = document.getElementById('welcome-name');
    const welcomeMessage = document.getElementById('welcome-message');

    setText(welcomeName, 'Welcome to Bite Me Donut!');
    setText(welcomeMessage, 'Discover our delicious selection of fresh donuts and treats.');

    hideAccountPanel();
  }

  function updateHomePublic() {
    renderLandingHome();
    updateNavbarAccount(null);
  }

  function updateHomeLoggedIn(user) {
    renderLandingHome();
    updateNavbarAccount(user);
  }

  async function handleLogout() {
    try {
      await postHome('logout');
    } catch (error) {
      console.warn('[home:logout]', error);
    }

    clearSession();
    window.location.href = LOGIN_URL;
  }

  function bindLogoutButtons() {
    const selectors = [
      '#global-btn-logout',
      '#nav-logout-btn',
      '#header-logout-btn',
      '[data-action="logout"]'
    ];

    selectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach((button) => {
        button.addEventListener('click', handleLogout);
      });
    });
  }

  async function loadCurrentUser() {
    const token = getToken();

    if (!token) {
      clearSession();
      updateHomePublic();
      return;
    }

    const cachedUser = getCachedUser();

    if (cachedUser) {
      updateHomeLoggedIn(cachedUser);
    }

    try {
      const response = await postHome('me');

      if (!response.ok) {
        clearSession();
        updateHomePublic();
        return;
      }

      const user = extractUser(response.data);

      if (!user) {
        clearSession();
        updateHomePublic();
        return;
      }

      saveToken(token);
      saveUser(user);
      updateHomeLoggedIn(user);
    } catch (error) {
      console.error('[home:me]', error);

      if (cachedUser) {
        updateHomeLoggedIn(cachedUser);
        return;
      }

      clearSession();
      updateHomePublic();
    }
  }

  bindLogoutButtons();
  await loadCurrentUser();
});