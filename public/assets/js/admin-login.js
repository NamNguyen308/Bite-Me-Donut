document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  const form = document.getElementById('admin-login-form');
  const alertBox = document.getElementById('admin-alert');

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

  function showAlert(message) {
    if (!alertBox) return;

    alertBox.textContent = message || 'Login failed';
    alertBox.classList.remove('hidden');
  }

  function hideAlert() {
    if (!alertBox) return;

    alertBox.textContent = '';
    alertBox.classList.add('hidden');
  }

  function saveAdminSession(data) {
    const accessToken = data?.access_token;
    const user = data?.user;

    if (!accessToken) {
      throw new Error('Access token was not returned');
    }

    localStorage.setItem('access_token', accessToken);
    localStorage.setItem('auth_token', accessToken);
    localStorage.setItem('bmd_access_token', accessToken);
    localStorage.setItem('is_logged_in', '1');

    if (user) {
      const userJson = JSON.stringify(user);

      localStorage.setItem('auth_user', userJson);
      localStorage.setItem('current_user', userJson);
      localStorage.setItem('user', userJson);
      localStorage.setItem('bmd_user', userJson);
    }
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const appBasePath = getAppBasePath();

    const apiLoginUrl = `${appBasePath}/api/admin/login`;
    const dashboardUrl = `${appBasePath}/views/admin/dashboard.php`;

    const email = document.getElementById('email')?.value.trim() || '';
    const password = document.getElementById('password')?.value || '';
    const button = form.querySelector('button[type="submit"]');

    hideAlert();

    if (!email || !password) {
      showAlert('Please enter email and password');
      return;
    }

    if (button) {
      button.disabled = true;
      button.textContent = 'Logging in...';
    }

    try {
      const response = await fetch(apiLoginUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          email,
          password
        })
      });

      const rawText = await response.text();

      console.log('[ADMIN LOGIN STATUS]', response.status);
      console.log('[ADMIN LOGIN RAW]', rawText);

      let result;

      try {
        result = JSON.parse(rawText);
      } catch (error) {
        showAlert('Server returned invalid JSON response');
        return;
      }

      if (!response.ok || result.success === false) {
        showAlert(result.message || 'Invalid email or password');
        return;
      }

      saveAdminSession(result.data);

      window.location.href = dashboardUrl;
    } catch (error) {
      console.error('[admin-login]', error);
      showAlert('Cannot connect to server. Please try again.');
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = 'Login';
      }
    }
  });
});