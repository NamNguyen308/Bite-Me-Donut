document.addEventListener('DOMContentLoaded', async () => {
  'use strict';

  console.log('[ADMIN CUSTOMERS JS] loaded');

  const CONFIG = window.ADMIN_CUSTOMERS_CONFIG || {};

  const CUSTOMERS_PROXY_URL =
    CONFIG.customersProxyUrl || `${window.location.pathname}?admin_customers_ajax=1`;

  const LOGIN_URL =
    CONFIG.loginUrl || '../auth/user-login.php';

  const TOKEN_KEYS = ['access_token', 'auth_token', 'bmd_access_token'];
  const USER_KEYS = ['auth_user', 'current_user', 'user', 'bmd_user'];

  const tbody = document.getElementById('customers-tbody');

  const modal = document.getElementById('customer-modal');
  const form = document.getElementById('customer-form');

  const inputName = document.getElementById('c-name');
  const inputPhone = document.getElementById('c-phone');
  const inputEmail = document.getElementById('c-email');
  const inputPassword = document.getElementById('c-password');
  const inputRole = document.getElementById('c-role');
  const inputIsActive = document.getElementById('c-is_active');

  let customers = [];

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

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function formatDate(value) {
    if (!value) return '-';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
      return value;
    }

    return date.toLocaleDateString('vi-VN');
  }

  async function postCustomers(action, payload = {}) {
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

    console.log('[ADMIN CUSTOMERS POST]', CUSTOMERS_PROXY_URL, body);

    const response = await fetch(CUSTOMERS_PROXY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(body)
    });

    const rawText = await response.text();

    console.log('[ADMIN CUSTOMERS STATUS]', response.status);
    console.log('[ADMIN CUSTOMERS RAW]', rawText);

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

  function extractCustomers(responseData) {
    if (!responseData) return [];

    if (responseData.data && Array.isArray(responseData.data.customers)) {
      return responseData.data.customers;
    }

    if (Array.isArray(responseData.customers)) {
      return responseData.customers;
    }

    if (Array.isArray(responseData.data)) {
      return responseData.data;
    }

    return [];
  }

  function renderCustomers() {
    if (!tbody) return;

    if (!customers.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" style="text-align:center;">No customers found</td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = customers.map((user) => {
      const active = Number(user.is_active ?? 0) === 1;
      const role = user.role || 'customer';

      return `
        <tr>
          <td>${escapeHtml(user.id)}</td>
          <td><strong>${escapeHtml(user.name || '-')}</strong></td>
          <td>${escapeHtml(user.email || '-')}</td>
          <td>${escapeHtml(user.phone || '-')}</td>
          <td>
            <span class="status-badge ${role === 'admin' ? 'status-badge--info' : 'status-badge--warning'}">
              ${escapeHtml(role)}
            </span>
          </td>
          <td>
            <span class="status-badge ${active ? 'status-badge--success' : 'status-badge--danger'}">
              ${active ? 'Active' : 'Inactive'}
            </span>
          </td>
          <td>${escapeHtml(formatDate(user.created_at))}</td>
          <td>
            <button
              type="button"
              class="btn btn--outline btn-delete-customer"
              data-id="${escapeHtml(user.id)}"
              ${!active ? 'disabled' : ''}
              style="color: var(--color-danger); border-color: var(--color-danger);"
            >
              ${active ? 'Deactivate' : 'Inactive'}
            </button>
          </td>
        </tr>
      `;
    }).join('');
  }

  function openModal() {
  console.log('[ADMIN CUSTOMERS] Add clicked');

  if (!modal) {
    console.error('[ADMIN CUSTOMERS] #customer-modal not found');
    return;
  }

  if (!form) {
    console.error('[ADMIN CUSTOMERS] #customer-form not found');
    return;
  }

  form.reset();

  if (inputRole) inputRole.value = 'customer';
  if (inputIsActive) inputIsActive.value = '1';

  modal.hidden = false;
  modal.removeAttribute('hidden');
  modal.removeAttribute('aria-hidden');

  modal.classList.add('active');
  modal.classList.add('is-open');

  modal.style.setProperty('display', 'flex', 'important');
  modal.style.setProperty('pointer-events', 'auto', 'important');
  modal.style.setProperty('visibility', 'visible', 'important');
  modal.style.setProperty('opacity', '1', 'important');
  modal.style.setProperty('position', 'fixed', 'important');
  modal.style.setProperty('inset', '0', 'important');
  modal.style.setProperty('z-index', '999999', 'important');

  setTimeout(() => {
    inputName?.focus();
  }, 0);
}

function closeModal() {
  if (!modal) return;

  modal.classList.remove('active');
  modal.classList.remove('is-open');

  modal.hidden = true;
  modal.setAttribute('hidden', 'hidden');
  modal.setAttribute('aria-hidden', 'true');

  modal.style.setProperty('display', 'none', 'important');
  modal.style.setProperty('pointer-events', 'none', 'important');
  modal.style.setProperty('visibility', 'hidden', 'important');
  modal.style.setProperty('opacity', '0', 'important');
}

  async function loadCustomers() {
    if (!getToken()) {
      clearSession();
      window.location.href = LOGIN_URL;
      return;
    }

    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" style="text-align:center;">Loading...</td>
        </tr>
      `;
    }

    const response = await postCustomers('list');

    if (!response.ok) {
      console.error('[admin-customers:list]', response.data);

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

      if (tbody) {
        tbody.innerHTML = `
          <tr>
            <td colspan="8" style="text-align:center;">
              ${escapeHtml(response.data?.message || 'Error loading customers')}
            </td>
          </tr>
        `;
      }

      return;
    }

    customers = extractCustomers(response.data);
    renderCustomers();
  }

  document.addEventListener('click', async (event) => {
    const addButton = event.target.closest('#btn-add-customer');
    const deleteButton = event.target.closest('.btn-delete-customer');
    const closeButton = event.target.closest('#modal-close');
    const cancelButton = event.target.closest('#modal-cancel');

    if (addButton) {
      event.preventDefault();
      openModal();
      return;
    }

    if (deleteButton) {
      event.preventDefault();

      const id = Number(deleteButton.dataset.id || 0);

      console.log('[ADMIN CUSTOMERS] Delete clicked', id);

      if (!id) {
        alert('Invalid user id');
        return;
      }

      if (!confirm('Deactivate this user?')) {
        return;
      }

      deleteButton.disabled = true;

      try {
        const response = await postCustomers('delete', { id });

        if (!response.ok) {
          alert(response.data?.message || 'Cannot deactivate user');
          return;
        }

        await loadCustomers();
      } finally {
        deleteButton.disabled = false;
      }

      return;
    }

    if (closeButton || cancelButton) {
      event.preventDefault();
      closeModal();
      return;
    }

    if (modal && event.target === modal) {
      closeModal();
    }
  });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const payload = {
      name: inputName.value.trim(),
      phone: inputPhone.value.trim(),
      email: inputEmail.value.trim(),
      password: inputPassword.value,
      role: inputRole.value,
      is_active: Number(inputIsActive.value || 1)
    };

    const submitBtn = form.querySelector('button[type="submit"]');

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving...';
    }

    try {
      const response = await postCustomers('create', payload);

      if (!response.ok) {
        alert(response.data?.message || 'Cannot create customer');
        return;
      }

      closeModal();
      await loadCustomers();
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save';
      }
    }
  });

  closeModal();
  await loadCustomers();
});