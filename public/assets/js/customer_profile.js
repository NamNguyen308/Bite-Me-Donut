/**
 * profile.js — Bite-Me-Donut
 * Handles: load user profile, edit mode, logout modal, toast notifications
 * Auth:    Bearer token from localStorage (key: "access_token")
 * API:     GET  /api/users/me
 *          POST /api/auth/logout
 */

'use strict';

/* ─────────────────────────────────────────────────────────────
   CONFIG
───────────────────────────────────────────────────────────── */
const API_BASE = '/api';
const TOKEN_KEY = 'access_token';

/* ─────────────────────────────────────────────────────────────
   DOM REFERENCES
───────────────────────────────────────────────────────────── */
const $ = (id) => document.getElementById(id);

const DOM = {
    // Layout states
    pageLoader: $('pageLoader'),
    profileError: $('profileError'),
    profileErrorMsg: $('profileErrorMsg'),
    profileContent: $('profileContent'),

    // Hero
    avatarImg: $('avatarImg'),
    heroName: $('heroName'),
    heroRole: $('heroRole'),
    heroJoin: $('heroJoin'),
    heroPhone: $('heroPhone'),

    // Info view
    infoName: $('infoName'),
    infoEmail: $('infoEmail'),
    infoPhone: $('infoPhone'),
    infoRole: $('infoRole'),
    infoStatus: $('infoStatus'),
    infoCreated: $('infoCreated'),

    // Edit form
    infoView: $('infoView'),
    editForm: $('editForm'),
    editName: $('editName'),
    editEmail: $('editEmail'),
    editPhone: $('editPhone'),
    editBtn: $('editBtn'),
    saveBtn: $('saveBtn'),
    cancelEditBtn: $('cancelEditBtn'),
    editAlert: $('editAlert'),

    // Stats
    statOrders: $('statOrders'),
    statCartItems: $('statCartItems'),
    statTotal: $('statTotal'),

    // Security / session
    sessionDesc: $('sessionDesc'),

    // Logout
    logoutBtn: $('logoutBtn'),
    revokeBtn: $('revokeBtn'),
    logoutModal: $('logoutModal'),
    logoutModalClose: $('logoutModalClose'),
    confirmLogoutBtn: $('confirmLogoutBtn'),
    cancelLogoutBtn: $('cancelLogoutBtn'),

    // Toast
    toastContainer: $('toastContainer'),
};

/* ─────────────────────────────────────────────────────────────
   UTILITIES
───────────────────────────────────────────────────────────── */

/** Get stored access token */
function getToken() {
    return localStorage.getItem(TOKEN_KEY) || sessionStorage.getItem(TOKEN_KEY);
}

/** Remove token from all storages */
function clearToken() {
    localStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(TOKEN_KEY);
}

/** Authenticated fetch wrapper */
async function apiFetch(path, options = {}) {
    const token = getToken();
    const headers = {
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(options.headers || {}),
    };
    const response = await fetch(`${API_BASE}${path}`, { ...options, headers });
    const data = await response.json();
    return { ok: response.ok, status: response.status, data };
}

/**
 * Format Vietnamese date
 * @param {string} dateStr — ISO string from API
 */
function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
        return new Intl.DateTimeFormat('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        }).format(new Date(dateStr));
    } catch {
        return dateStr;
    }
}

/** Format VND currency */
function formatVND(amount) {
    if (amount === null || amount === undefined) return '—';
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount);
}

/** Translate role */
function translateRole(role) {
    const map = { admin: 'Quản trị viên', customer: 'Khách hàng' };
    return map[role] || role || '—';
}

/** Translate active status */
function translateStatus(isActive) {
    if (isActive === 1 || isActive === true) {
        return '<span class="badge badge--success">Đang hoạt động</span>';
    }
    return '<span class="badge badge--danger">Tạm khóa</span>';
}

/* ─────────────────────────────────────────────────────────────
   TOAST
───────────────────────────────────────────────────────────── */
/**
 * Show a toast notification
 * @param {string} message
 * @param {'success'|'danger'|'warning'|'info'} type
 * @param {number} duration  ms
 */
function showToast(message, type = 'success', duration = 3500) {
    const icons = { success: '✅', danger: '❌', warning: '⚠️', info: 'ℹ️' };
    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.innerHTML = `<span>${icons[type] || '🍩'}</span> <span>${message}</span>`;
    DOM.toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('toast--out');
        toast.addEventListener('animationend', () => toast.remove());
    }, duration);
}

/* ─────────────────────────────────────────────────────────────
   PAGE STATE HELPERS
───────────────────────────────────────────────────────────── */
function showLoader() { DOM.pageLoader.classList.remove('hidden'); }
function hideLoader() { DOM.pageLoader.classList.add('hidden'); }

function showError(msg) {
    if (msg) DOM.profileErrorMsg.textContent = msg;
    DOM.profileError.classList.remove('hidden');
}

function showContent() {
    DOM.profileContent.classList.remove('hidden');
}

/* ─────────────────────────────────────────────────────────────
   POPULATE PROFILE
───────────────────────────────────────────────────────────── */
function populateProfile(user) {
    const name = user.name || user.full_name || '(Chưa cập nhật)';
    const email = user.email || '—';
    const phone = user.phone || '—';
    const role = translateRole(user.role);
    const joined = formatDate(user.created_at);

    // ── Hero
    DOM.heroName.textContent = name;
    DOM.heroPhone.textContent = phone;
    DOM.heroJoin.textContent = `Tham gia từ ${joined}`;
    DOM.heroRole.textContent = role;

    // ── Info rows
    DOM.infoName.textContent = name;
    DOM.infoEmail.textContent = email;
    DOM.infoPhone.textContent = phone;
    DOM.infoRole.textContent = role;
    DOM.infoStatus.innerHTML = translateStatus(user.is_active);
    DOM.infoCreated.textContent = joined;

    // ── Pre-fill edit form
    DOM.editName.value = name !== '(Chưa cập nhật)' ? name : '';
    DOM.editEmail.value = email !== '—' ? email : '';
    DOM.editPhone.value = phone;
}

/* ─────────────────────────────────────────────────────────────
   FETCH USER PROFILE
───────────────────────────────────────────────────────────── */
async function loadProfile() {
    const token = getToken();

    if (!token) {
        hideLoader();
        showError('Bạn chưa đăng nhập. Vui lòng đăng nhập để xem hồ sơ.');
        return;
    }

    showLoader();

    try {
        const { ok, data } = await apiFetch('/users/me');

        hideLoader();

        if (!ok) {
            const code = data?.error_code || '';
            const msgMap = {
                TOKEN_MISSING: 'Phiên đăng nhập không hợp lệ.',
                TOKEN_INVALID: 'Token không hợp lệ. Vui lòng đăng nhập lại.',
                TOKEN_EXPIRED: 'Phiên đăng nhập đã hết hạn.',
                TOKEN_REVOKED: 'Token đã bị thu hồi. Vui lòng đăng nhập lại.',
            };
            clearToken();
            showError(msgMap[code] || data?.message || 'Không thể tải hồ sơ.');
            return;
        }

        // API có thể trả data.user hoặc data.data hoặc thẳng data
        const user = data.user || data.data || data;
        populateProfile(user);
        showContent();
        loadStats();

    } catch (err) {
        hideLoader();
        showError('Lỗi kết nối đến máy chủ. Vui lòng thử lại sau.');
        console.error('[Profile] loadProfile error:', err);
    }
}

/* ─────────────────────────────────────────────────────────────
   FETCH STATS (Orders, Cart)
   Graceful — nếu API lỗi thì chỉ hiện "—"
───────────────────────────────────────────────────────────── */
async function loadStats() {
    // Orders
    try {
        const { ok, data } = await apiFetch('/orders');
        if (ok) {
            const orders = data.data || data.orders || (Array.isArray(data) ? data : []);
            DOM.statOrders.textContent = orders.length;

            // Tổng chi tiêu
            const total = orders.reduce((sum, o) => sum + parseFloat(o.total || o.total_amount || 0), 0);
            DOM.statTotal.textContent = formatVND(total);
        }
    } catch { /* ignore */ }

    // Cart
    try {
        const { ok, data } = await apiFetch('/cart');
        if (ok) {
            const items = data.data?.items || data.items || [];
            const count = items.reduce((sum, i) => sum + (i.quantity || 0), 0);
            DOM.statCartItems.textContent = count;
        }
    } catch { /* ignore */ }
}

/* ─────────────────────────────────────────────────────────────
   EDIT MODE
───────────────────────────────────────────────────────────── */
function enterEditMode() {
    DOM.infoView.classList.add('hidden');
    DOM.editForm.classList.remove('hidden');
    DOM.editBtn.textContent = '✏️ Đang chỉnh sửa';
    DOM.editBtn.disabled = true;
    DOM.editAlert.classList.add('hidden');
    DOM.editAlert.textContent = '';
    DOM.editName.focus();
}

function exitEditMode() {
    DOM.infoView.classList.remove('hidden');
    DOM.editForm.classList.add('hidden');
    DOM.editBtn.textContent = '✏️ Chỉnh sửa';
    DOM.editBtn.disabled = false;
}

function showEditAlert(msg, type = 'danger') {
    DOM.editAlert.className = `alert alert--${type}`;
    DOM.editAlert.textContent = msg;
    DOM.editAlert.classList.remove('hidden');
}

async function saveProfile() {
    const name = DOM.editName.value.trim();
    const email = DOM.editEmail.value.trim();

    if (!name) {
        showEditAlert('Vui lòng nhập họ và tên.');
        DOM.editName.focus();
        return;
    }

    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showEditAlert('Email không hợp lệ.');
        DOM.editEmail.focus();
        return;
    }

    DOM.saveBtn.disabled = true;
    DOM.saveBtn.textContent = '⏳ Đang lưu...';

    try {
        // Note: endpoint update profile tuỳ backend — thường là PATCH /api/users/me
        // Nếu chưa có endpoint, ta chỉ cập nhật local UI và thông báo thành công mock
        const { ok, data } = await apiFetch('/users/me', {
            method: 'PATCH',
            body: JSON.stringify({ name, email }),
        });

        if (ok) {
            // Cập nhật lại giao diện
            DOM.infoName.textContent = name;
            DOM.infoEmail.textContent = email || '—';
            DOM.heroName.textContent = name;
            exitEditMode();
            showToast('Cập nhật hồ sơ thành công! 🎉', 'success');
        } else {
            const code = data?.error_code || '';
            const msgMap = {
                VALIDATION_ERROR: data?.errors ? Object.values(data.errors).join(', ') : 'Dữ liệu không hợp lệ.',
                NOT_FOUND: 'Không tìm thấy phương thức cập nhật.',
                METHOD_NOT_ALLOWED: 'API chưa hỗ trợ cập nhật hồ sơ. Thay đổi đã được lưu cục bộ.',
            };
            // Graceful fallback nếu endpoint chưa tồn tại
            if (code === 'NOT_FOUND' || code === 'METHOD_NOT_ALLOWED' || data?.status === 404 || data?.status === 405) {
                DOM.infoName.textContent = name;
                DOM.infoEmail.textContent = email || '—';
                DOM.heroName.textContent = name;
                exitEditMode();
                showToast('Giao diện đã cập nhật. (API endpoint chưa được triển khai)', 'warning', 5000);
            } else {
                showEditAlert(msgMap[code] || data?.message || 'Cập nhật thất bại.');
            }
        }
    } catch {
        // Graceful fallback — update UI locally
        DOM.infoName.textContent = name;
        DOM.infoEmail.textContent = email || '—';
        DOM.heroName.textContent = name;
        exitEditMode();
        showToast('Lỗi mạng. Giao diện đã cập nhật cục bộ.', 'warning', 5000);
    } finally {
        DOM.saveBtn.disabled = false;
        DOM.saveBtn.textContent = '💾 Lưu thay đổi';
    }
}

/* ─────────────────────────────────────────────────────────────
   LOGOUT
───────────────────────────────────────────────────────────── */
function openLogoutModal() {
    DOM.logoutModal.classList.add('is-open');
}

function closeLogoutModal() {
    DOM.logoutModal.classList.remove('is-open');
}

async function performLogout() {
    DOM.confirmLogoutBtn.disabled = true;
    DOM.confirmLogoutBtn.textContent = '⏳ Đang đăng xuất...';

    try {
        await apiFetch('/auth/logout', { method: 'POST' });
    } catch { /* always clear regardless */ }

    clearToken();
    closeLogoutModal();
    showToast('Đã đăng xuất. Tạm biệt! 🍩', 'success', 2000);
    setTimeout(() => { window.location.href = '/login.php'; }, 1800);
}

/* ─────────────────────────────────────────────────────────────
   SESSION INFO
───────────────────────────────────────────────────────────── */
function updateSessionInfo() {
    const token = getToken();
    if (!token) {
        DOM.sessionDesc.textContent = 'Chưa đăng nhập';
        return;
    }
    // We can decode JWT expiry if it's a JWT; otherwise just show active.
    // This project uses opaque tokens so we just show a generic message.
    const now = new Intl.DateTimeFormat('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: '2-digit',
    }).format(new Date());
    DOM.sessionDesc.textContent = `Đăng nhập lúc ${now}`;
}

/* ─────────────────────────────────────────────────────────────
   EVENT BINDINGS
───────────────────────────────────────────────────────────── */
function bindEvents() {
    // Edit toggle
    DOM.editBtn.addEventListener('click', enterEditMode);
    DOM.cancelEditBtn.addEventListener('click', exitEditMode);

    // Save
    DOM.saveBtn.addEventListener('click', saveProfile);

    // Enter key in edit fields
    [DOM.editName, DOM.editEmail].forEach((el) => {
        el.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') saveProfile();
            if (e.key === 'Escape') exitEditMode();
        });
    });

    // Logout buttons (navbar + security card)
    DOM.logoutBtn.addEventListener('click', openLogoutModal);
    DOM.revokeBtn.addEventListener('click', openLogoutModal);

    // Modal controls
    DOM.logoutModalClose.addEventListener('click', closeLogoutModal);
    DOM.cancelLogoutBtn.addEventListener('click', closeLogoutModal);
    DOM.confirmLogoutBtn.addEventListener('click', performLogout);

    // Close modal on overlay click
    DOM.logoutModal.addEventListener('click', (e) => {
        if (e.target === DOM.logoutModal) closeLogoutModal();
    });

    // Close modal on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && DOM.logoutModal.classList.contains('is-open')) {
            closeLogoutModal();
        }
    });
}

/* ─────────────────────────────────────────────────────────────
   INIT
───────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    bindEvents();
    updateSessionInfo();
    loadProfile();
});