/**
 * customer_orders.js — Order History Page
 *
 * Responsibilities:
 *  - Load sidebar user info via GET /api/users/me
 *  - Load order list via GET /api/orders
 *  - Load order detail inline via GET /api/orders/{id} (dropdown toggle)
 *  - Filter orders by status tab and search input
 *  - Logout modal + token revoke via POST /api/auth/logout
 *  - Toast notifications
 *
 * Auth:   Authorization: Bearer <access_token> (localStorage / sessionStorage)
 * Errors: TOKEN_MISSING | TOKEN_INVALID | TOKEN_EXPIRED | TOKEN_REVOKED → redirect login
 *         ORDER_ACCESS_DENIED | IDOR_BLOCKED → show toast
 */

(function () {
    'use strict';

    /* ============================================================
       CONFIG
       ============================================================ */
    const API_BASE = '/api';
    const LOGIN_PAGE = '../../views/auth/login.php';

    /* ============================================================
       STATE
       ============================================================ */
    let allOrders = [];   // raw list from API
    let filteredOrders = [];  // after status + search filter
    let activeStatus = 'all';
    let searchQuery = '';
    // Cache fetched detail objects keyed by order id
    const detailCache = {};

    /* ============================================================
       TOKEN HELPERS
       ============================================================ */
    function getToken() {
        return localStorage.getItem('access_token')
            || sessionStorage.getItem('access_token')
            || null;
    }

    function clearToken() {
        localStorage.removeItem('access_token');
        sessionStorage.removeItem('access_token');
    }

    function redirectLogin() {
        window.location.href = LOGIN_PAGE;
    }

    /* ============================================================
       API FETCH WRAPPER
       ============================================================ */
    async function apiFetch(path, options = {}) {
        const token = getToken();
        const headers = {
            'Content-Type': 'application/json',
            ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
            ...(options.headers || {}),
        };

        let response;
        try {
            response = await fetch(`${API_BASE}${path}`, { ...options, headers });
        } catch (networkErr) {
            throw new Error('Network error. Please check your connection.');
        }

        const data = await response.json();

        // Auth failures → redirect immediately
        const authErrors = ['TOKEN_MISSING', 'TOKEN_INVALID', 'TOKEN_EXPIRED', 'TOKEN_REVOKED'];
        if (!data.success && authErrors.includes(data.error_code)) {
            clearToken();
            redirectLogin();
            return null;
        }

        return data;
    }

    /* ============================================================
       TOAST
       ============================================================ */
    function showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const iconMap = {
            success: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                  fill="none" stroke="currentColor" stroke-width="2.5"
                  stroke-linecap="round" stroke-linejoin="round">
                  <polyline points="20 6 9 17 4 12"/>
                </svg>`,
            danger: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                  fill="none" stroke="currentColor" stroke-width="2.5"
                  stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/>
                  <line x1="12" y1="8" x2="12" y2="12"/>
                  <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>`,
            warning: `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                  fill="none" stroke="currentColor" stroke-width="2.5"
                  stroke-linecap="round" stroke-linejoin="round">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                  <line x1="12" y1="9" x2="12" y2="13"/>
                  <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>`,
        };

        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.innerHTML = `
      <span class="toast__icon">${iconMap[type] || iconMap.success}</span>
      <span>${escapeHtml(message)}</span>`;

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('toast--out');
            toast.addEventListener('animationend', () => toast.remove(), { once: true });
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    }

    /* ============================================================
       HELPERS
       ============================================================ */
    function escapeHtml(str) {
        if (str == null) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        try {
            return new Date(dateStr).toLocaleDateString('en-GB', {
                day: '2-digit', month: 'short', year: 'numeric',
            });
        } catch { return dateStr; }
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '—';
        try {
            return new Date(dateStr).toLocaleString('en-GB', {
                day: '2-digit', month: 'short', year: 'numeric',
                hour: '2-digit', minute: '2-digit',
            });
        } catch { return dateStr; }
    }

    function formatCurrency(amount) {
        const num = parseFloat(amount) || 0;
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency', currency: 'VND',
        }).format(num);
    }

    /* Status config */
    const STATUS_CONFIG = {
        pending: { label: 'Pending', cls: 'order-status--pending' },
        processing: { label: 'Processing', cls: 'order-status--processing' },
        shipping: { label: 'Shipping', cls: 'order-status--shipping' },
        completed: { label: 'Completed', cls: 'order-status--completed' },
        cancelled: { label: 'Cancelled', cls: 'order-status--cancelled' },
    };

    function getStatusConfig(status) {
        return STATUS_CONFIG[status?.toLowerCase()]
            || { label: status || 'Unknown', cls: 'order-status--pending' };
    }

    function renderStatusBadge(status) {
        const cfg = getStatusConfig(status);
        return `<span class="order-status-badge ${cfg.cls}">
              <span class="order-status-badge__dot"></span>
              ${cfg.label}
            </span>`;
    }

    /* ============================================================
       LOAD USER (for sidebar)
       ============================================================ */
    async function loadSidebarUser() {
        try {
            const data = await apiFetch('/users/me');
            if (!data || !data.success) return;

            const user = data.user;
            const nameEl = document.getElementById('sidebarName');
            const roleEl = document.getElementById('sidebarRole');

            if (nameEl) nameEl.textContent = user.full_name || '—';
            if (roleEl) roleEl.textContent = user.role === 'admin' ? 'Admin' : 'Customer';
        } catch (err) {
            console.error('Sidebar user load error:', err);
        }
    }

    /* ============================================================
       LOAD ORDERS LIST
       GET /api/orders
       ============================================================ */
    async function loadOrders() {
        showLoader(true);

        try {
            const data = await apiFetch('/orders');
            if (!data) return; // redirected

            if (!data.success) {
                showError(data.message || 'Failed to load orders.');
                return;
            }

            allOrders = Array.isArray(data.orders) ? data.orders : [];
            showContent();
            renderSummaryChips(allOrders);
            applyFilters();

        } catch (err) {
            console.error('Orders load error:', err);
            showError('A network error occurred. Please try again.');
        } finally {
            showLoader(false);
        }
    }

    /* ============================================================
       RENDER SUMMARY CHIPS
       ============================================================ */
    function renderSummaryChips(orders) {
        const totalEl = document.getElementById('chipTotal');
        const spentEl = document.getElementById('chipSpent');

        if (totalEl) totalEl.textContent = orders.length;

        if (spentEl) {
            const total = orders.reduce((sum, o) => sum + (parseFloat(o.total_price) || 0), 0);
            spentEl.textContent = formatCurrency(total);
        }
    }

    /* ============================================================
       FILTER LOGIC
       ============================================================ */
    function applyFilters() {
        let result = allOrders;

        // Status filter
        if (activeStatus !== 'all') {
            result = result.filter(o => o.status?.toLowerCase() === activeStatus);
        }

        // Search filter (order id or note)
        if (searchQuery) {
            const q = searchQuery.toLowerCase();
            result = result.filter(o =>
                String(o.id).includes(q) ||
                (o.note && o.note.toLowerCase().includes(q)) ||
                (o.shipping_address && o.shipping_address.toLowerCase().includes(q))
            );
        }

        filteredOrders = result;
        renderOrderList();
    }

    /* ============================================================
       RENDER ORDER LIST
       ============================================================ */
    function renderOrderList() {
        const listEl = document.getElementById('ordersList');
        const emptyEl = document.getElementById('ordersEmpty');

        if (!listEl || !emptyEl) return;

        if (filteredOrders.length === 0) {
            listEl.innerHTML = '';
            emptyEl.classList.remove('hidden');

            // Tweak empty message based on filter state
            const titleEl = document.getElementById('ordersEmptyTitle');
            const descEl = document.getElementById('ordersEmptyDesc');

            if (allOrders.length === 0) {
                if (titleEl) titleEl.textContent = 'No orders yet';
                if (descEl) descEl.textContent = "You haven't placed any orders. Start shopping now!";
            } else {
                if (titleEl) titleEl.textContent = 'No matching orders';
                if (descEl) descEl.textContent = 'Try changing the filter or search term.';
            }
            return;
        }

        emptyEl.classList.add('hidden');
        listEl.innerHTML = filteredOrders.map((order, idx) =>
            renderOrderCard(order, idx)
        ).join('');

        // Attach toggle listeners
        listEl.querySelectorAll('.order-card__header').forEach(header => {
            header.addEventListener('click', () => {
                const card = header.closest('.order-card');
                const orderId = card.dataset.orderId;
                toggleOrderDetail(card, orderId);
            });
        });
    }

    /* ── Single order card HTML ── */
    function renderOrderCard(order, idx) {
        const delay = Math.min(idx * 0.05, 0.3);
        const created = formatDate(order.created_at);

        return `
      <div class="order-card" data-order-id="${escapeHtml(order.id)}"
           style="animation-delay: ${delay}s;">

        <!-- Clickable header row -->
        <div class="order-card__header">
          <div class="order-card__header-left">
            <div class="order-card__thumb">
              <!-- Donut / bag SVG icon -->
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22"
                   viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
              </svg>
            </div>
            <div class="order-card__meta">
              <div class="order-card__id">Order #${escapeHtml(order.id)}</div>
              <div class="order-card__date">${created}</div>
            </div>
          </div>

          <div class="order-card__header-right">
            ${renderStatusBadge(order.status)}
            <div class="order-card__total">
              <div class="order-card__total-value">${formatCurrency(order.total_price)}</div>
              <div class="order-card__total-label">Total</div>
            </div>
            <div class="order-card__toggle" aria-label="Toggle order detail">
              <!-- Chevron down -->
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                   viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </div>
          </div>
        </div><!-- /.order-card__header -->

        <!-- Detail dropdown (hidden by default) -->
        <div class="order-card__detail" id="detail-${escapeHtml(order.id)}">
          <div class="order-card__detail-inner">
            <div class="order-detail-loading">
              <div class="spinner" style="width:20px;height:20px;border-width:2px;"></div>
              <span>Loading details…</span>
            </div>
          </div>
        </div>

      </div>`;
    }

    /* ============================================================
       TOGGLE ORDER DETAIL DROPDOWN
       GET /api/orders/{id}
       ============================================================ */
    async function toggleOrderDetail(card, orderId) {
        const isOpen = card.classList.contains('is-open');
        const detailEl = document.getElementById(`detail-${orderId}`);

        if (!detailEl) return;

        // Close this card if already open
        if (isOpen) {
            card.classList.remove('is-open');
            return;
        }

        // Close any other open card
        document.querySelectorAll('.order-card.is-open').forEach(c => c.classList.remove('is-open'));

        // Open this card
        card.classList.add('is-open');

        // If detail already cached, render immediately
        if (detailCache[orderId]) {
            renderDetailPanel(detailEl, detailCache[orderId]);
            return;
        }

        // Fetch from API
        detailEl.querySelector('.order-card__detail-inner').innerHTML = `
      <div class="order-detail-loading">
        <div class="spinner" style="width:20px;height:20px;border-width:2px;"></div>
        <span>Loading details…</span>
      </div>`;

        try {
            const data = await apiFetch(`/orders/${encodeURIComponent(orderId)}`);

            if (!data) return; // redirected

            if (!data.success) {
                // Handle IDOR / access denied gracefully
                const denied = ['ORDER_ACCESS_DENIED', 'IDOR_BLOCKED'];
                if (denied.includes(data.error_code)) {
                    showToast('You are not authorized to view this order.', 'danger');
                } else {
                    showToast(data.message || 'Failed to load order details.', 'danger');
                }
                card.classList.remove('is-open');
                return;
            }

            detailCache[orderId] = data.order;
            renderDetailPanel(detailEl, data.order);

        } catch (err) {
            console.error('Order detail error:', err);
            detailEl.querySelector('.order-card__detail-inner').innerHTML = `
        <div class="order-detail-error">
          <div class="alert alert--danger">Could not load order details. Please try again.</div>
        </div>`;
        }
    }

    /* ── Render the detail panel content ── */
    function renderDetailPanel(detailEl, order) {
        const items = Array.isArray(order.items) ? order.items : [];

        /* Items table rows */
        const itemRows = items.length > 0
            ? items.map(item => `
          <tr>
            <td>
              <div class="order-item__name">${escapeHtml(item.product_name || item.name || 'Product')}</div>
              <div class="order-item__qty">x${escapeHtml(item.quantity)}</div>
            </td>
            <td>${formatCurrency(item.unit_price || item.price)}</td>
            <td>${formatCurrency((parseFloat(item.unit_price || item.price) || 0) * (parseInt(item.quantity) || 1))}</td>
          </tr>`).join('')
            : `<tr><td colspan="3" style="text-align:center; color: var(--color-text-muted); padding: var(--space-5);">
           No items found.
         </td></tr>`;

        /* Price breakdown */
        const subtotal = parseFloat(order.subtotal || order.total_price || 0);
        const shipping = parseFloat(order.shipping_fee || 0);
        const discount = parseFloat(order.discount || 0);
        const total = parseFloat(order.total_price || subtotal);

        const shippingRow = shipping > 0
            ? `<div class="price-row">
           <span class="price-row__label">Shipping Fee</span>
           <span class="price-row__value">${formatCurrency(shipping)}</span>
         </div>` : '';

        const discountRow = discount > 0
            ? `<div class="price-row">
           <span class="price-row__label">Discount</span>
           <span class="price-row__value" style="color: var(--color-success);">
             &minus;${formatCurrency(discount)}
           </span>
         </div>` : '';

        /* Note */
        const noteHtml = order.note
            ? `<div class="detail-section-title" style="margin-top: var(--space-5);">
           <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
             <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
           </svg>
           Order Note
         </div>
         <div class="detail-info-block">
           <div class="detail-info-block__value">${escapeHtml(order.note)}</div>
         </div>` : '';

        detailEl.querySelector('.order-card__detail-inner').innerHTML = `

      <!-- ── Items ── -->
      <div class="detail-section-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <line x1="8" y1="6" x2="21" y2="6"/>
          <line x1="8" y1="12" x2="21" y2="12"/>
          <line x1="8" y1="18" x2="21" y2="18"/>
          <line x1="3" y1="6" x2="3.01" y2="6"/>
          <line x1="3" y1="12" x2="3.01" y2="12"/>
          <line x1="3" y1="18" x2="3.01" y2="18"/>
        </svg>
        Ordered Items
      </div>

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

      <!-- ── Info + Totals ── -->
      <div class="detail-info-grid">

        <!-- Shipping info -->
        <div class="detail-info-block">
          <div class="detail-info-block__label">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
              <circle cx="12" cy="10" r="3"/>
            </svg>
            Shipping Address
          </div>
          <div class="detail-info-block__value">
            ${escapeHtml(order.shipping_address || '—')}
          </div>
        </div>

        <!-- Order meta -->
        <div class="detail-info-block">
          <div class="detail-info-block__label">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            Order Details
          </div>
          <div class="detail-info-block__value">
            <strong>Placed:</strong> ${formatDateTime(order.created_at)}<br/>
            <strong>Status:</strong> ${getStatusConfig(order.status).label}<br/>
            ${order.updated_at ? `<strong>Updated:</strong> ${formatDateTime(order.updated_at)}` : ''}
          </div>
        </div>

      </div><!-- /.detail-info-grid -->

      <!-- ── Price summary ── -->
      <div class="detail-price-summary">
        <div class="price-row">
          <span class="price-row__label">Subtotal</span>
          <span class="price-row__value">${formatCurrency(subtotal)}</span>
        </div>
        ${shippingRow}
        ${discountRow}
        <div class="price-row price-row--total">
          <span class="price-row__label">Total</span>
          <span class="price-row__value">${formatCurrency(total)}</span>
        </div>
      </div>

      ${noteHtml}`;
    }

    /* ============================================================
       FILTER UI — Status Tabs
       ============================================================ */
    function initStatusTabs() {
        const tabsEl = document.getElementById('ordersStatusTabs');
        if (!tabsEl) return;

        tabsEl.addEventListener('click', (e) => {
            const tab = e.target.closest('.status-tab');
            if (!tab) return;

            tabsEl.querySelectorAll('.status-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeStatus = tab.dataset.status || 'all';
            applyFilters();
        });
    }

    /* ============================================================
       FILTER UI — Search
       ============================================================ */
    function initSearch() {
        const searchEl = document.getElementById('ordersSearch');
        if (!searchEl) return;

        let debounceTimer;
        searchEl.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                searchQuery = searchEl.value.trim();
                applyFilters();
            }, 280);
        });
    }

    /* ============================================================
       LOGOUT
       ============================================================ */
    function initLogout() {
        const logoutBtn = document.getElementById('sidebarLogoutBtn');
        const modal = document.getElementById('logoutModal');
        const modalClose = document.getElementById('logoutModalClose');
        const confirmBtn = document.getElementById('confirmLogoutBtn');
        const cancelBtn = document.getElementById('cancelLogoutBtn');

        function openModal() { if (modal) modal.classList.add('is-open'); }
        function closeModal() { if (modal) modal.classList.remove('is-open'); }

        if (logoutBtn) logoutBtn.addEventListener('click', openModal);
        if (modalClose) modalClose.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

        // Close modal on overlay click
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModal();
            });
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', async () => {
                confirmBtn.disabled = true;
                confirmBtn.textContent = 'Logging out…';

                try {
                    await apiFetch('/auth/logout', { method: 'POST' });
                } catch (err) {
                    console.error('Logout error:', err);
                } finally {
                    clearToken();
                    redirectLogin();
                }
            });
        }
    }

    /* ============================================================
       PAGE STATE HELPERS
       ============================================================ */
    function showLoader(visible) {
        const loader = document.getElementById('pageLoader');
        const content = document.getElementById('ordersContent');

        if (loader) loader.style.display = visible ? 'flex' : 'none';
        if (content) {
            if (visible) content.classList.add('hidden');
            // content shown via showContent()
        }
    }

    function showContent() {
        const errorEl = document.getElementById('profileError');
        const contentEl = document.getElementById('ordersContent');
        if (errorEl) errorEl.classList.add('hidden');
        if (contentEl) contentEl.classList.remove('hidden');
    }

    function showError(message) {
        const loader = document.getElementById('pageLoader');
        const errorEl = document.getElementById('profileError');
        const msgEl = document.getElementById('profileErrorMsg');
        const contentEl = document.getElementById('ordersContent');

        if (loader) loader.style.display = 'none';
        if (contentEl) contentEl.classList.add('hidden');
        if (msgEl) msgEl.textContent = message;
        if (errorEl) errorEl.classList.remove('hidden');
    }

    /* ============================================================
       INIT
       ============================================================ */
    function init() {
        // Guard: must have a token to be on this page
        if (!getToken()) {
            redirectLogin();
            return;
        }

        initStatusTabs();
        initSearch();
        initLogout();
        loadSidebarUser();
        loadOrders();
    }

    document.addEventListener('DOMContentLoaded', init);

})();