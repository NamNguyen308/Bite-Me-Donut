document.addEventListener('DOMContentLoaded', async () => {
  'use strict';

  const CONFIG = window.ADMIN_DASHBOARD_CONFIG || {};

  const DASHBOARD_PROXY_URL =
    CONFIG.dashboardProxyUrl || `${window.location.pathname}?dashboard_ajax=1`;

  const LOGIN_URL =
    CONFIG.loginUrl || '../auth/user-login.php';

  const TOKEN_KEYS = ['access_token', 'auth_token', 'bmd_access_token'];
  const USER_KEYS = ['auth_user', 'current_user', 'user', 'bmd_user'];

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
    sessionStorage.removeItem('is_logged_in');
  }

  async function postDashboard(action, payload = {}) {
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

    const body = {
      action,
      access_token: token,
      ...payload
    };

    console.log('[ADMIN DASHBOARD POST]', DASHBOARD_PROXY_URL, body);

    const response = await fetch(DASHBOARD_PROXY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(body)
    });

    const rawText = await response.text();

    console.log('[ADMIN DASHBOARD STATUS]', response.status);
    console.log('[ADMIN DASHBOARD RAW]', rawText);

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

  function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
      element.textContent = value ?? '0';
    }
  }

  function formatMoney(value) {
    const number = Number(value || 0);

    return number.toLocaleString('vi-VN') + ' VND';
  }

  function extractDashboard(responseData) {
    if (!responseData) return {};

    if (responseData.data && responseData.data.dashboard) {
      return responseData.data.dashboard;
    }

    if (responseData.dashboard) {
      return responseData.dashboard;
    }

    return {};
  }

  function extractAdmin(responseData) {
    if (!responseData) return null;

    if (responseData.data && responseData.data.admin) {
      return responseData.data.admin;
    }

    if (responseData.admin) {
      return responseData.admin;
    }

    return null;
  }

  function renderDashboard(dashboard) {
    setText('kpi-orders', dashboard.total_orders ?? 0);
    setText('kpi-revenue', formatMoney(dashboard.total_revenue ?? 0));
    setText('kpi-products', dashboard.total_products ?? 0);
    setText('kpi-customers', dashboard.total_users ?? 0);

    // Fallback nếu sau này HTML đổi về id cũ
    setText('totalOrders', dashboard.total_orders ?? 0);
    setText('totalRevenue', formatMoney(dashboard.total_revenue ?? 0));
    setText('totalProducts', dashboard.total_products ?? 0);
    setText('totalUsers', dashboard.total_users ?? 0);
    setText('riskEventsToday', dashboard.risk_events_today ?? 0);
  }

  function renderAdminInfo(admin) {
    if (!admin) return;

    const adminInfo = document.getElementById('admin-user-info');

    if (adminInfo) {
      adminInfo.textContent = admin.name || admin.email || 'Admin';
    }
  }

  async function loadDashboard() {
    const token = getToken();

    if (!token) {
      clearSession();
      window.location.href = LOGIN_URL;
      return;
    }

    const response = await postDashboard('dashboard');

    if (!response.ok) {
      console.error('[admin-dashboard]', response.data);

      if (
        [
          'UNAUTHENTICATED',
          'TOKEN_INVALID',
          'TOKEN_EXPIRED',
          'TOKEN_REVOKED',
          'ADMIN_REQUIRED'
        ].includes(response.data?.error_code)
      ) {
        clearSession();
        window.location.href = LOGIN_URL;
        return;
      }

      alert(response.data?.message || 'Cannot load dashboard data');
      return;
    }

    const dashboard = extractDashboard(response.data);
    const admin = extractAdmin(response.data);

    renderDashboard(dashboard);
    renderAdminInfo(admin);
  }

  await loadDashboard();
});