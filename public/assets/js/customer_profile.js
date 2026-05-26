/**
 * profile.js — Bite-Me-Donut
 * Handles: load user profile, edit mode, logout modal, toast notifications
 * Auth:    Bearer token stored in localStorage (key: "access_token")
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
   SVG ICON HELPERS (no emoji — all inline SVG strings)
───────────────────────────────────────────────────────────── */
const ICONS = {
    check: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`,
    cross: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,
    info: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    warn: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
};

/* ─────────────────────────────────────────────────────────────
   DOM REFERENCES
───────────────────────────────────────────────────────────── */
const $ = (id) => document.getElementById(id);

const DOM = {
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

    // Sidebar mini-profile
    sidebarAvatar: $('sidebarAvatar'),
    sidebarName: $('sidebarName'),
    sidebarRole: $('sidebarRole'),

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

    // Security
    sessionDesc: $('sessionDesc'),

    // Logout
    logoutBtn: $('logoutBtn'),
    sidebarLogoutBtn: $('sidebarLogoutBtn'),
    revokeBtn: $('revokeBtn'),
    logoutModal: $('logoutModal'),
    logoutModalClose: $('logoutModalClose'),
    confirmLogoutBtn: $('confirmLogoutBtn'),
    cancelLogoutBtn: $('cancelLogoutBtn'),

    toastContainer: $('toastContainer'),
};

/* ─────────────────────────────────────────────────────────────
   AUTH HELPERS
───────────────────────────────────────────────────────────── */
function getToken() {
    return localStorage.getItem(TOKEN_KEY) || sessionStorage.getItem(TOKEN_KEY);
}

function clearToken() {
    localStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(TOKEN_KEY);
}

async function apiFetch(path, options = {}) {
    const token = getToken();
    const headers = {
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(options.headers || {}),
    };
    const res = await fetch(`${API_BASE}${path}`, { ...options, headers });
    const data = await res.json();
    return { ok: res.ok, status: res.status, data };
}

/* ─────────────────────────────────────────────────────────────
   FORMAT HELPERS
───────────────────────────────────────────────────────────── */
function formatDate(str) {
    if (!str) return '—';
    try {
        return new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(str));
    } catch { return str; }
}

function translateRole(role) {
    return { admin: 'Quản trị viên', customer: 'Khách hàng' }[role] || role || '—';
}

function translateStatus(isActive) {
    return (isActive === 1 || isActive === true)
        ? '<span class="badge badge--success">Active</span>'
        : '<span class="badge badge--danger">Inactive</span>';
}

/* ─────────────────────────────────────────────────────────────
   TOAST  (SVG icons only)
───────────────────────────────────────────────────────────── */
function showToast(message, type = 'success', duration = 3500) {
    const iconMap = { success: ICONS.check, danger: ICONS.cross, warning: ICONS.warn, info: ICONS.info };
    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.innerHTML = `<span class="toast__icon">${iconMap[type] || ICONS.info}</span><span>${message}</span>`;
    DOM.toastContainer.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('toast--out');
        toast.addEventListener('animationend', () => toast.remove(), { once: true });
    }, duration);
}

/* ─────────────────────────────────────────────────────────────
   PAGE STATES
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
   POPULATE
───────────────────────────────────────────────────────────── */
function populateProfile(user) {
    const name = user.name || user.full_name || '(Not updated)';
    const email = user.email || '—';
    const phone = user.phone || '—';
    const role = translateRole(user.role);
    const joined = formatDate(user.created_at);

    // Hero
    DOM.heroName.textContent = name;
    DOM.heroPhone.textContent = phone;
    DOM.heroJoin.textContent = `Joined ${joined}`;
    DOM.heroRole.textContent = role;

    // Sidebar mini
    DOM.sidebarName.textContent = name;
    DOM.sidebarRole.textContent = role;

    // Info rows
    DOM.infoName.textContent = name;
    DOM.infoEmail.textContent = email;
    DOM.infoPhone.textContent = phone;
    DOM.infoRole.textContent = role;
    DOM.infoStatus.innerHTML = translateStatus(user.is_active);
    DOM.infoCreated.textContent = joined;

    // Pre-fill edit form
    DOM.editName.value = name !== '(Not updated)' ? name : '';
    DOM.editEmail.value = email !== '—' ? email : '';
    DOM.editPhone.value = phone;
}

/* ─────────────────────────────────────────────────────────────
   LOAD PROFILE
───────────────────────────────────────────────────────────── */
async function loadProfile() {
    if (!getToken()) {
        hideLoader();
        showError('You are not logged in. Please log in to view your profile.');
        return;
    }

    showLoader();

    try {
        const { ok, data } = await apiFetch('/users/me');
        hideLoader();

        if (!ok) {
            const msgs = {
                TOKEN_MISSING: 'Invalid session.',
                TOKEN_INVALID: 'Invalid token. Please log in again.',
                TOKEN_EXPIRED: 'Session expired.',
                TOKEN_REVOKED: 'Token has been revoked.',
            };
            clearToken();
            showError(msgs[data?.error_code] || data?.message || 'Failed to load profile.');
            return;
        }

        const user = data.user || data.data || data;
        populateProfile(user);
        showContent();

    } catch (err) {
        hideLoader();
        showError('Failed to load profile.');
        console.error('[Profile] loadProfile:', err);
    }
}

/* ─────────────────────────────────────────────────────────────
   EDIT MODE
───────────────────────────────────────────────────────────── */
function enterEditMode() {
    DOM.infoView.classList.add('hidden');
    DOM.editForm.classList.remove('hidden');
    DOM.editBtn.disabled = true;
    DOM.editAlert.classList.add('hidden');
    DOM.editName.focus();
}

function exitEditMode() {
    DOM.infoView.classList.remove('hidden');
    DOM.editForm.classList.add('hidden');
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

    if (!name) { showEditAlert('Please enter your full name.'); DOM.editName.focus(); return; }
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showEditAlert('Invalid email.'); DOM.editEmail.focus(); return;
    }

    DOM.saveBtn.disabled = true;
    DOM.saveBtn.textContent = 'Saving...';

    try {
        const { ok, data } = await apiFetch('/users/me', {
            method: 'PATCH',
            body: JSON.stringify({ name, email }),
        });

        const graceful = !ok && ['NOT_FOUND', 'METHOD_NOT_ALLOWED'].includes(data?.error_code);

        if (ok || graceful) {
            DOM.infoName.textContent = name;
            DOM.infoEmail.textContent = email || '—';
            DOM.heroName.textContent = name;
            DOM.sidebarName.textContent = name;
            exitEditMode();
            showToast(
                ok ? 'Profile updated successfully!' : 'Interface updated (API endpoint not implemented).',
                ok ? 'success' : 'warning',
                ok ? 3500 : 5000
            );
        } else {
            showEditAlert(data?.message || 'Failed to update profile.');
        }

    } catch {
        // Network error — update UI locally
        DOM.infoName.textContent = name;
        DOM.infoEmail.textContent = email || '—';
        DOM.heroName.textContent = name;
        DOM.sidebarName.textContent = name;
        exitEditMode();
        showToast('Network error. Interface updated locally.', 'warning', 5000);
    } finally {
        DOM.saveBtn.disabled = false;
        // Restore button content (SVG + text) — easier to re-set innerHTML
        DOM.saveBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Changes`;
    }
}

/* ─────────────────────────────────────────────────────────────
   LOGOUT
───────────────────────────────────────────────────────────── */
function openLogoutModal() { DOM.logoutModal.classList.add('is-open'); }
function closeLogoutModal() { DOM.logoutModal.classList.remove('is-open'); }

async function performLogout() {
    DOM.confirmLogoutBtn.disabled = true;
    DOM.confirmLogoutBtn.textContent = 'Logging out...';

    try { await apiFetch('/auth/logout', { method: 'POST' }); } catch { /* always clear */ }

    clearToken();
    closeLogoutModal();
    showToast('Logged out. Goodbye!', 'success', 2000);
    setTimeout(() => { window.location.href = '/login.php'; }, 1800);
}

/* ─────────────────────────────────────────────────────────────
   SESSION INFO
───────────────────────────────────────────────────────────── */
function updateSessionInfo() {
    if (!getToken()) { DOM.sessionDesc.textContent = 'Not logged in'; return; }
    const now = new Intl.DateTimeFormat('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' }).format(new Date());
    DOM.sessionDesc.textContent = `Logged in at ${now}`;
}

/* ─────────────────────────────────────────────────────────────
   EVENT BINDINGS
───────────────────────────────────────────────────────────── */
function bindEvents() {
    DOM.editBtn.addEventListener('click', enterEditMode);
    DOM.cancelEditBtn.addEventListener('click', exitEditMode);
    DOM.saveBtn.addEventListener('click', saveProfile);

    [DOM.editName, DOM.editEmail].forEach((el) => {
        el.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') saveProfile();
            if (e.key === 'Escape') exitEditMode();
        });
    });

    // All logout triggers
    [DOM.logoutBtn, DOM.sidebarLogoutBtn, DOM.revokeBtn].forEach((btn) => {
        btn?.addEventListener('click', openLogoutModal);
    });

    DOM.logoutModalClose.addEventListener('click', closeLogoutModal);
    DOM.cancelLogoutBtn.addEventListener('click', closeLogoutModal);
    DOM.confirmLogoutBtn.addEventListener('click', performLogout);

    DOM.logoutModal.addEventListener('click', (e) => {
        if (e.target === DOM.logoutModal) closeLogoutModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && DOM.logoutModal.classList.contains('is-open')) closeLogoutModal();
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
