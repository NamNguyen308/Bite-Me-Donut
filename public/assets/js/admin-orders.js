document.addEventListener('DOMContentLoaded', async () => {
  'use strict';

  console.log('[ADMIN ORDERS JS] loaded');

  const CONFIG = window.ADMIN_ORDERS_CONFIG || {};

  const ORDERS_PROXY_URL =
    CONFIG.ordersProxyUrl || `${window.location.pathname}?admin_orders_ajax=1`;

  const LOGIN_URL =
    CONFIG.loginUrl || '../auth/user-login.php';

  const TOKEN_KEYS = ['access_token', 'auth_token', 'bmd_access_token'];
  const USER_KEYS = ['auth_user', 'current_user', 'user', 'bmd_user'];

  const tbody = document.getElementById('orders-tbody');

  const modal = document.getElementById('status-modal');
  const form = document.getElementById('status-form');
  const inputOrderId = document.getElementById('order-id');
  const inputStatus = document.getElementById('o-status');

  let orders = [];

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

  function formatMoney(value) {
    return Number(value || 0).toLocaleString('vi-VN') + ' VND';
  }

  function formatDate(value) {
    if (!value) return '-';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
      return value;
    }

    return date.toLocaleDateString('vi-VN');
  }

  function normalizeStatus(status) {
    return String(status || 'PENDING').trim().toUpperCase();
  }

  function getStatusColor(status) {
    switch (normalizeStatus(status)) {
      case 'COMPLETED':
        return 'success';
      case 'CANCELLED':
        return 'danger';
      case 'PROCESSING':
        return 'info';
      case 'SHIPPING':
        return 'info';
      case 'PENDING':
      default:
        return 'warning';
    }
  }

  async function postOrders(action, payload = {}) {
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

    console.log('[ADMIN ORDERS POST]', ORDERS_PROXY_URL, body);

    const response = await fetch(ORDERS_PROXY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(body)
    });

    const rawText = await response.text();

    console.log('[ADMIN ORDERS STATUS]', response.status);
    console.log('[ADMIN ORDERS RAW]', rawText);

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

  function extractOrders(responseData) {
    if (!responseData) return [];

    if (responseData.data && Array.isArray(responseData.data.orders)) {
      return responseData.data.orders;
    }

    if (Array.isArray(responseData.orders)) {
      return responseData.orders;
    }

    if (Array.isArray(responseData.data)) {
      return responseData.data;
    }

    return [];
  }

  function findOrder(id) {
    return orders.find((order) => Number(order.id) === Number(id)) || null;
  }

  function renderOrders() {
    if (!tbody) return;

    if (!orders.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="8" style="text-align:center;">No orders found</td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = orders.map((order) => {
      const status = normalizeStatus(order.status);
      const statusColor = getStatusColor(status);

      return `
        <tr>
          <td>#${escapeHtml(order.id)}</td>

          <td>
            ${escapeHtml(order.user_id || '-')}
            ${
              order.customer_name
                ? `<div class="order-items-small">${escapeHtml(order.customer_name)}</div>`
                : ''
            }
          </td>

          <td>
            <strong>${escapeHtml(order.shipping_name || '-')}</strong><br>
            <small style="color:var(--color-text-muted);">${escapeHtml(order.shipping_phone || '-')}</small>
            ${
              order.shipping_address
                ? `<div class="order-items-small">${escapeHtml(order.shipping_address)}</div>`
                : ''
            }
          </td>

          <td>${escapeHtml(formatMoney(order.total))}</td>

          <td>${escapeHtml(String(order.payment_method || 'COD').toUpperCase())}</td>

          <td>
            <span class="status-badge status-badge--${statusColor}">
              ${escapeHtml(status)}
            </span>
          </td>

          <td>${escapeHtml(formatDate(order.created_at))}</td>

          <td>
            <button
              type="button"
              class="btn btn--outline btn-update-order"
              data-id="${escapeHtml(order.id)}"
              data-status="${escapeHtml(status)}"
            >
              Update Status
            </button>
          </td>
        </tr>
      `;
    }).join('');
  }

  function openModal(id, status) {
    console.log('[ADMIN ORDERS] Update clicked', id, status);

    if (!modal || !form) {
      console.error('[ADMIN ORDERS] Modal or form not found');
      return;
    }

    const normalizedStatus = normalizeStatus(status);

    inputOrderId.value = id;
    inputStatus.value = normalizedStatus;

    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.removeAttribute('aria-hidden');

    modal.classList.add('active');
    modal.classList.add('is-open');

    modal.style.setProperty('display', 'flex', 'important');
    modal.style.setProperty('pointer-events', 'auto', 'important');
    modal.style.setProperty('visibility', 'visible', 'important');
    modal.style.setProperty('opacity', '1', 'important');

    setTimeout(() => {
      inputStatus?.focus();
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

  async function loadOrders() {
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

    const response = await postOrders('list');

    if (!response.ok) {
      console.error('[admin-orders:list]', response.data);

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
              ${escapeHtml(response.data?.message || 'Error loading orders')}
            </td>
          </tr>
        `;
      }

      return;
    }

    orders = extractOrders(response.data);
    renderOrders();
  }

  document.addEventListener('click', async (event) => {
    const updateButton = event.target.closest('.btn-update-order');
    const closeButton = event.target.closest('#modal-close');
    const cancelButton = event.target.closest('#modal-cancel');

    if (updateButton) {
      event.preventDefault();

      const id = Number(updateButton.dataset.id || 0);
      const status = updateButton.dataset.status || 'PENDING';

      const order = findOrder(id);

      if (!order) {
        alert('Order not found');
        return;
      }

      openModal(id, status);
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

    const id = Number(inputOrderId.value || 0);
    const status = normalizeStatus(inputStatus.value);

    if (!id) {
      alert('Invalid order id');
      return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Updating...';
    }

    try {
      const response = await postOrders('update_status', {
        id,
        status
      });

      if (!response.ok) {
        alert(response.data?.message || 'Cannot update order status');
        return;
      }

      closeModal();
      await loadOrders();
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Update';
      }
    }
  });

  closeModal();
  await loadOrders();
});