document.addEventListener('DOMContentLoaded', async () => {
  'use strict';

  const CONFIG = window.HOME_CONFIG || {};

  const HOME_PROXY_URL = CONFIG.homeProxyUrl || `${window.location.pathname}?home_ajax=1`;
  const LOGIN_URL = CONFIG.loginUrl || '../auth/user-login.php';
  const ACCOUNT_URL = CONFIG.accountUrl || '#account';

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

  function setText(element, value) {
    if (element) {
      element.textContent = value;
    }
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

  function displayNameOf(user) {
    return user?.name || user?.full_name || user?.email || user?.phone || 'Donut Lover';
  }

  function findHeaderRoot() {
    return document.querySelector('header') || document.body || document;
  }

  function findLoginLinks() {
    const root = findHeaderRoot();

    const selectors = [
      '#nav-login-link',
      '#header-login-link',
      '#global-login-link',
      'a[href*="user-login.php"]',
      'a[href*="login.php"]',
      'a[href*="login"]'
    ];

    const links = [];

    selectors.forEach((selector) => {
      root.querySelectorAll(selector).forEach((element) => {
        if (element.tagName === 'A' && !links.includes(element)) {
          links.push(element);
        }
      });
    });

    return links;
  }

  function findLogoutButtons() {
    const root = findHeaderRoot();

    const selectors = [
      '#global-btn-logout',
      '#nav-logout-btn',
      '#header-logout-btn',
      '[data-action="logout"]'
    ];

    const buttons = [];

    selectors.forEach((selector) => {
      root.querySelectorAll(selector).forEach((element) => {
        if (!buttons.includes(element)) {
          buttons.push(element);
        }
      });
    });

    return buttons;
  }

  function updateHeaderPublic() {
    const loginLinks = findLoginLinks();
    const logoutButtons = findLogoutButtons();

    loginLinks.forEach((link) => {
      link.href = LOGIN_URL;
      link.title = 'Login';
      link.dataset.authState = 'guest';

      const text = link.textContent.trim();

      if (text && !link.querySelector('svg') && !link.querySelector('i')) {
        link.textContent = 'Login';
      }

      link.style.display = '';
    });

    logoutButtons.forEach((button) => {
      button.style.display = 'none';
    });

    const navUserName = document.getElementById('nav-user-name');

    if (navUserName) {
      navUserName.textContent = '';
    }
  }

  function updateHeaderLoggedIn(user) {
    const name = displayNameOf(user);
    const loginLinks = findLoginLinks();
    const logoutButtons = findLogoutButtons();

    loginLinks.forEach((link) => {
      link.href = ACCOUNT_URL;
      link.title = `Logged in as ${name}`;
      link.dataset.authState = 'authenticated';

      const text = link.textContent.trim();

      if (text && !link.querySelector('svg') && !link.querySelector('i')) {
        link.textContent = `Hi, ${name}`;
      }

      link.style.display = '';
    });

    logoutButtons.forEach((button) => {
      button.style.display = 'inline-flex';
    });

    const navUserName = document.getElementById('nav-user-name');

    if (navUserName) {
      navUserName.textContent = `Hi, ${name}`;
    }
  }

  function ensureAccountPanel(user) {
    let panel = document.getElementById('account');

    if (!panel) {
      const hero = document.querySelector('.home-hero');
      panel = document.createElement('section');
      panel.id = 'account';
      panel.className = 'section container';
      panel.style.marginTop = '24px';

      if (hero && hero.parentNode) {
        hero.parentNode.insertBefore(panel, hero.nextSibling);
      } else {
        document.body.prepend(panel);
      }
    }

    const name = displayNameOf(user);
    const contact = user?.email || user?.phone || 'Not provided';
    const role = user?.role || 'customer';

    panel.innerHTML = `
      <div class="card" style="padding: 20px;">
        <h2 id="welcome-name">Welcome back, ${escapeHtml(name)}!</h2>
        <p id="welcome-message">Ready for some sweet, freshly baked treats today?</p>
        <p id="user-profile-email">${escapeHtml(contact)}</p>
        <p id="user-profile-role">Role: ${escapeHtml(String(role).toUpperCase())}</p>
        <button type="button" id="home-panel-logout" class="btn btn--secondary">
          Logout
        </button>
      </div>
    `;

    document.getElementById('home-panel-logout')?.addEventListener('click', handleLogout);
  }

  function updateHomePublic() {
    updateHeaderPublic();

    const welcomeName = document.getElementById('welcome-name');
    const welcomeMessage = document.getElementById('welcome-message');
    const accountPanel = document.getElementById('account');

    setText(welcomeName, 'Welcome to Bite Me Donut!');
    setText(welcomeMessage, 'Discover our delicious selection of fresh donuts and treats.');

    if (accountPanel) {
      accountPanel.style.display = 'none';
    }
  }

  function updateHomeLoggedIn(user) {
    updateHeaderLoggedIn(user);
    ensureAccountPanel(user);

    const accountPanel = document.getElementById('account');

    if (accountPanel) {
      accountPanel.style.display = '';
    }
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
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

  async function handleLogout() {
    try {
      await postHome('logout');
    } catch (error) {
      console.warn('[home:logout]', error);
    }

    clearSession();
    window.location.href = LOGIN_URL;
  }

  findLogoutButtons().forEach((button) => {
    button.addEventListener('click', handleLogout);
  });

  await loadCurrentUser();
});