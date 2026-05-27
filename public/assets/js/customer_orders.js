/**
 * customer_orders.js
 * Order History Page
 *
 * Flow:
 * - Read access_token from localStorage
 * - POST to customer_orders.php?orders_ajax=1
 * - PHP proxy calls backend:
 *   + GET  /api/users/me
 *   + GET  /api/orders
 *   + POST /api/auth/logout
 */

document.addEventListener('DOMContentLoaded', async () => {
  'use strict';

  const CONFIG = window.CUSTOMER_ORDERS_CONFIG || {};

  const ORDERS_PROXY_URL = CONFIG.ordersProxyUrl || `${window.location.pathname}?orders_ajax=1`;
  const LOGIN_URL = CONFIG.loginUrl || '../auth/user-login.php';
  const PROFILE_URL = CONFIG.profileUrl || './customer_profile.php';
  const CHANGE_PASSWORD_URL = CONFIG.changePasswordUrl || '../auth/change_password.php';

  const TOKEN_KEYS = ['access_token', 'auth_token'];
  const USER_KEYS = ['auth_user', 'current_user', 'user'];

  let allOrders = [];
  let filteredOrders = [];
  let activeStatus = 'all';
  let searchQuery = '';

  const detailCache = {};

  const pageLoader = document.getElementById('pageLoader');
  const profileError = document.getElementById('profileError');
  const profileErrorMsg = document.getElementById('profileErrorMsg');
  const ordersContent = document.getElementById('ordersContent');
  const ordersList = document.getElementById('ordersList');
  const ordersEmpty = document.getElementById('ordersEmpty');
  const ordersSearch = document.getElementById('ordersSearch');
  const ordersStatusTabs = document.getElementById('ordersStatusTabs');
  const toastContainer = document.getElementById('toastContainer');

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

  function redirectLogin() {
    clearSession();
    window.location.href = LOGIN_URL;
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

    const requestBody = {
      action,
      access_token: token,
      ...payload
    };

    console.log('[ORDERS POST]', ORDERS_PROXY_URL, requestBody);

    const response = await fetch(ORDERS_PROXY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(requestBody)
    });

    const rawText = await response.text();

    console.log('[ORDERS STATUS]', response.status);
    console.log('[ORDERS RAW]', rawText);

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

  function extractOrders(responseData) {
    if (!responseData) return [];

    if (Array.isArray(responseData.data)) {
      return responseData.data;
    }

    if (responseData.data && Array.isArray(responseData.data.orders)) {
      return responseData.data.orders;
    }

    if (responseData.data && Array.isArray(responseData.data.items)) {
      return responseData.data.items;
    }

    if (Array.isArray(responseData.orders)) {
      return responseData.orders;
    }

    if (Array.isArray(responseData.items)) {
      return responseData.items;
    }

    return [];
  }

  function saveUserToStorage(user) {
    if (!user) return;

    const userJson = JSON.stringify(user);

    USER_KEYS.forEach((key) => {
      localStorage.setItem(key, userJson);
    });
  }

  function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
      element.textContent = value || '—';
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

  function formatDate(value) {
    if (!value) return '—';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
      return String(value);
    }

    return date.toLocaleDateString('vi-VN');
  }

  function formatDateTime(value) {
    if (!value) return '—';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
      return String(value);
    }

    return date.toLocaleString('vi-VN');
  }

  function formatCurrency(value) {
    const number = Number(value || 0);

    return number.toLocaleString('vi-VN') + ' đ';
  }

  function normalizeStatus(status) {
    return String(status || 'pending').toLowerCase();
  }

  function getOrderTotal(order) {
    return Number(
      order.total ??
      order.total_price ??
      order.total_amount ??
      order.grand_total ??
      0
    );
  }

  function getOrderId(order) {
    return order.id ?? order.order_id ?? order.orderId ?? '';
  }

  function getOrderCreatedAt(order) {
    return order.created_at ?? order.createdAt ?? order.order_date ?? '';
  }

  const STATUS_CONFIG = {
    pending: { label: 'Pending', cls: 'order-status--pending' },
    processing: { label: 'Processing', cls: 'order-status--processing' },
    shipping: { label: 'Shipping', cls: 'order-status--shipping' },
    completed: { label: 'Completed', cls: 'order-status--completed' },
    cancelled: { label: 'Cancelled', cls: 'order-status--cancelled' },
    paid: { label: 'Paid', cls: 'order-status--completed' },
    delivered: { label: 'Delivered', cls: 'order-status--completed' }
  };

  function getStatusConfig(status) {
    const key = normalizeStatus(status);

    return STATUS_CONFIG[key] || {
      label: status || 'Unknown',
      cls: 'order-status--pending'
    };
  }

  function renderStatusBadge(status) {
    const config = getStatusConfig(status);

    return `
      <span class="order-status-badge ${config.cls}">
        <span class="order-status-badge__dot"></span>
        ${escapeHtml(config.label)}
      </span>
    `;
  }

  function showToast(message, type = 'success') {
    if (!toastContainer) return;

    const iconMap = {
      success: `
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
             viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      `,
      danger: `
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
             viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
      `,
      warning: `
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
             viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      `
    };

    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.innerHTML = `
      <span class="toast__icon">${iconMap[type] || iconMap.success}</span>
      <span>${escapeHtml(message)}</span>
    `;

    toastContainer.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('toast--out');
      toast.addEventListener('animationend', () => toast.remove(), { once: true });
      setTimeout(() => toast.remove(), 500);
    }, 3500);
  }

  function showLoader(visible) {
    if (pageLoader) {
      pageLoader.style.display = visible ? 'flex' : 'none';
    }

    if (ordersContent && visible) {
      ordersContent.classList.add('hidden');
    }
  }

  function showContent() {
    if (pageLoader) {
      pageLoader.style.display = 'none';
    }

    if (profileError) {
      profileError.classList.add('hidden');
    }

    if (ordersContent) {
      ordersContent.classList.remove('hidden');
    }
  }

  function showError(message) {
    if (pageLoader) {
      pageLoader.style.display = 'none';
    }

    if (ordersContent) {
      ordersContent.classList.add('hidden');
    }

    if (profileErrorMsg) {
      profileErrorMsg.textContent = message || 'Unable to load orders. Please log in again.';
    }

    if (profileError) {
      profileError.classList.remove('hidden');
    }
  }

  async function loadSidebarUser() {
    const response = await postOrders('me');

    if (!response.ok) {
      throw new Error(response.data?.message || 'Cannot load current user');
    }

    const user = extractUser(response.data);

    if (!user) {
      throw new Error('Cannot read current user data');
    }

    const name = user.name || user.full_name || 'Donut Lover';
    const role = user.role || 'customer';

    setText('sidebarName', name);
    setText('sidebarRole', String(role).toUpperCase());

    saveUserToStorage(user);
  }

  async function loadOrders() {
    showLoader(true);

    const response = await postOrders('orders');

    if (!response.ok) {
      throw new Error(response.data?.message || 'Failed to load orders');
    }

    allOrders = extractOrders(response.data);
    filteredOrders = allOrders;

    renderSummaryChips(allOrders);
    applyFilters();
    showContent();
  }

  function renderSummaryChips(orders) {
    const totalElement = document.getElementById('chipTotal');
    const spentElement = document.getElementById('chipSpent');

    if (totalElement) {
      totalElement.textContent = String(orders.length);
    }

    if (spentElement) {
      const total = orders.reduce((sum, order) => {
        return sum + getOrderTotal(order);
      }, 0);

      spentElement.textContent = formatCurrency(total);
    }
  }

  function applyFilters() {
    let result = [...allOrders];

    if (activeStatus !== 'all') {
      result = result.filter((order) => normalizeStatus(order.status) === activeStatus);
    }

    if (searchQuery) {
      const query = searchQuery.toLowerCase();

      result = result.filter((order) => {
        const orderId = String(getOrderId(order)).toLowerCase();
        const note = String(order.note || '').toLowerCase();
        const address = String(order.shipping_address || '').toLowerCase();
        const phone = String(order.shipping_phone || '').toLowerCase();
        const name = String(order.shipping_name || '').toLowerCase();

        return (
          orderId.includes(query) ||
          note.includes(query) ||
          address.includes(query) ||
          phone.includes(query) ||
          name.includes(query)
        );
      });
    }

    filteredOrders = result;
    renderOrderList();
  }

  function renderOrderList() {
    if (!ordersList || !ordersEmpty) return;

    if (!filteredOrders.length) {
      ordersList.innerHTML = '';
      ordersEmpty.classList.remove('hidden');

      const titleElement = document.getElementById('ordersEmptyTitle');
      const descElement = document.getElementById('ordersEmptyDesc');

      if (!allOrders.length) {
        if (titleElement) titleElement.textContent = 'No orders yet';
        if (descElement) descElement.textContent = "You haven't placed any orders. Start shopping now!";
      } else {
        if (titleElement) titleElement.textContent = 'No matching orders';
        if (descElement) descElement.textContent = 'Try changing the filter or search term.';
      }

      return;
    }

    ordersEmpty.classList.add('hidden');

    ordersList.innerHTML = filteredOrders
      .map((order, index) => renderOrderCard(order, index))
      .join('');

    ordersList.querySelectorAll('.order-card__header').forEach((header) => {
      header.addEventListener('click', () => {
        const card = header.closest('.order-card');

        if (!card) return;

        const orderId = card.dataset.orderId;

        toggleOrderDetail(card, orderId);
      });
    });
  }

  function renderOrderCard(order, index) {
    const id = getOrderId(order);
    const createdAt = getOrderCreatedAt(order);
    const total = getOrderTotal(order);
    const delay = Math.min(index * 0.05, 0.3);

    return `
      <div class="order-card"
           data-order-id="${escapeHtml(id)}"
           style="animation-delay: ${delay}s;">

        <div class="order-card__header">
          <div class="order-card__header-left">
            <div class="order-card__thumb">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22"
                   viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
              </svg>
            </div>

            <div class="order-card__meta">
              <div class="order-card__id">Order #${escapeHtml(id)}</div>
              <div class="order-card__date">${escapeHtml(formatDate(createdAt))}</div>
            </div>
          </div>

          <div class="order-card__header-right">
            ${renderStatusBadge(order.status)}

            <div class="order-card__total">
              <div class="order-card__total-value">${escapeHtml(formatCurrency(total))}</div>
              <div class="order-card__total-label">Total</div>
            </div>

            <div class="order-card__toggle" aria-label="Toggle order detail">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                   viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </div>
          </div>
        </div>

        <div class="order-card__detail" id="detail-${escapeHtml(id)}">
          <div class="order-card__detail-inner">
            ${renderDetailContent(order)}
          </div>
        </div>
      </div>
    `;
  }

  function toggleOrderDetail(card, orderId) {
    const isOpen = card.classList.contains('is-open');

    document.querySelectorAll('.order-card.is-open').forEach((item) => {
      item.classList.remove('is-open');
    });

    if (isOpen) {
      card.classList.remove('is-open');
      return;
    }

    card.classList.add('is-open');

    const detailElement = document.getElementById(`detail-${orderId}`);

    if (!detailElement || detailCache[orderId]) {
      return;
    }

    const order = allOrders.find((item) => String(getOrderId(item)) === String(orderId));

    if (order) {
      detailCache[orderId] = order;
      detailElement.querySelector('.order-card__detail-inner').innerHTML = renderDetailContent(order);
    }
  }

  function getOrderItems(order) {
    if (Array.isArray(order.items)) return order.items;
    if (Array.isArray(order.order_items)) return order.order_items;
    if (Array.isArray(order.products)) return order.products;

    return [];
  }

  function renderDetailContent(order) {
    const items = getOrderItems(order);

    const itemRows = items.length
      ? items.map((item) => {
          const productName = item.product_name || item.name || 'Product';
          const quantity = Number(item.quantity || 1);
          const unitPrice = Number(item.unit_price ?? item.price ?? 0);
          const subtotal = Number(item.subtotal ?? unitPrice * quantity);

          return `
            <tr>
              <td>
                <div class="order-item__name">${escapeHtml(productName)}</div>
                <div class="order-item__qty">x${escapeHtml(quantity)}</div>
              </td>
              <td>${escapeHtml(formatCurrency(unitPrice))}</td>
              <td>${escapeHtml(formatCurrency(subtotal))}</td>
            </tr>
          `;
        }).join('')
      : `
        <tr>
          <td colspan="3" style="text-align:center; color: var(--color-text-muted); padding: var(--space-5);">
            Order item details are not available in this response.
          </td>
        </tr>
      `;

    const subtotal = Number(order.subtotal ?? order.total ?? order.total_price ?? 0);
    const shipping = Number(order.shipping_fee ?? 0);
    const discount = Number(order.discount ?? 0);
    const total = getOrderTotal(order);

    const shippingRow = shipping > 0
      ? `
        <div class="price-row">
          <span class="price-row__label">Shipping Fee</span>
          <span class="price-row__value">${escapeHtml(formatCurrency(shipping))}</span>
        </div>
      `
      : '';

    const discountRow = discount > 0
      ? `
        <div class="price-row">
          <span class="price-row__label">Discount</span>
          <span class="price-row__value" style="color: var(--color-success);">
            -${escapeHtml(formatCurrency(discount))}
          </span>
        </div>
      `
      : '';

    const noteHtml = order.note
      ? `
        <div class="detail-section-title" style="margin-top: var(--space-5);">
          Order Note
        </div>

        <div class="detail-info-block">
          <div class="detail-info-block__value">${escapeHtml(order.note)}</div>
        </div>
      `
      : '';

    return `
      <div class="detail-section-title">Ordered Items</div>

      <table class="order-items-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Unit Price</th>
            <th>Subtotal</th>
          </tr>
        </thead>
        <tbody>${itemRows}</tbody>
      </table>

      <div class="detail-info-grid">
        <div class="detail-info-block">
          <div class="detail-info-block__label">Shipping Address</div>
          <div class="detail-info-block__value">
            ${escapeHtml(order.shipping_address || '—')}
          </div>
        </div>

        <div class="detail-info-block">
          <div class="detail-info-block__label">Order Details</div>
          <div class="detail-info-block__value">
            <strong>Placed:</strong> ${escapeHtml(formatDateTime(getOrderCreatedAt(order)))}<br/>
            <strong>Status:</strong> ${escapeHtml(getStatusConfig(order.status).label)}<br/>
            ${order.updated_at ? `<strong>Updated:</strong> ${escapeHtml(formatDateTime(order.updated_at))}` : ''}
          </div>
        </div>
      </div>

      <div class="detail-price-summary">
        <div class="price-row">
          <span class="price-row__label">Subtotal</span>
          <span class="price-row__value">${escapeHtml(formatCurrency(subtotal))}</span>
        </div>

        ${shippingRow}
        ${discountRow}

        <div class="price-row price-row--total">
          <span class="price-row__label">Total</span>
          <span class="price-row__value">${escapeHtml(formatCurrency(total))}</span>
        </div>
      </div>

      ${noteHtml}
    `;
  }

  function initStatusTabs() {
    if (!ordersStatusTabs) return;

    ordersStatusTabs.addEventListener('click', (event) => {
      const tab = event.target.closest('.status-tab');

      if (!tab) return;

      ordersStatusTabs.querySelectorAll('.status-tab').forEach((item) => {
        item.classList.remove('active');
      });

      tab.classList.add('active');

      activeStatus = tab.dataset.status || 'all';

      applyFilters();
    });
  }

  function initSearch() {
    if (!ordersSearch) return;

    let debounceTimer = null;

    ordersSearch.addEventListener('input', () => {
      clearTimeout(debounceTimer);

      debounceTimer = setTimeout(() => {
        searchQuery = ordersSearch.value.trim();
        applyFilters();
      }, 250);
    });
  }

  function openLogoutModal() {
    if (!logoutModal) return;

    logoutModal.classList.add('is-active');
    logoutModal.classList.add('is-open');
  }

  function closeLogoutModal() {
    if (!logoutModal) return;

    logoutModal.classList.remove('is-active');
    logoutModal.classList.remove('is-open');
  }

  async function logout() {
    if (confirmLogoutBtn) {
      confirmLogoutBtn.disabled = true;
      confirmLogoutBtn.textContent = 'Logging out...';
    }

    try {
      await postOrders('logout');
    } catch (error) {
      console.warn('[orders:logout]', error);
    }

    clearSession();
    window.location.href = LOGIN_URL;
  }

  function initLogout() {
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

  function wireSidebarLinks() {
    const profileLink = document.querySelector('[data-page="profile"]');
    const ordersLink = document.querySelector('[data-page="orders"]');
    const changePasswordLink = document.querySelector('[data-page="change-password"]');

    if (profileLink) profileLink.href = PROFILE_URL;
    if (ordersLink) ordersLink.href = CONFIG.ordersUrl || window.location.pathname;
    if (changePasswordLink) changePasswordLink.href = CHANGE_PASSWORD_URL;
  }

  async function init() {
    if (!getToken()) {
      redirectLogin();
      return;
    }

    wireSidebarLinks();
    initStatusTabs();
    initSearch();
    initLogout();

    try {
      await loadSidebarUser();
      await loadOrders();
    } catch (error) {
      console.error('[orders:init]', error);
      showError(error.message || 'A network error occurred. Please try again.');
    }
  }

  await init();
});