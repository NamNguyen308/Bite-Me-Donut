/**
 * ============================================================
 * forgot-password.js
 * Xử lý 2 bước quên mật khẩu:
 *
 * Step 1 — Verify identity
 *   POST /api/auth/verify-identity  { email, phone }
 *   Backend: SELECT * FROM users WHERE email = ? AND phone = ?
 *   Nếu tìm thấy user active → trả { reset_token, user_id }
 *   reset_token: signed token tạm (JWT hoặc random bytes + session)
 *   Backend lưu reset_token_hash + expires_at vào bảng tạm
 *   (hoặc vào cột reset_token_hash, reset_token_expires_at trong users).
 *
 * Step 2 — Reset password
 *   POST /api/auth/reset-password  { reset_token, new_password }
 *   Backend: verify reset_token → UPDATE users SET password_hash = ?
 *   Xóa / vô hiệu hóa reset_token sau khi dùng.
 *
 * Database liên quan: bảng users (email, phone, password_hash,
 * reset_token_hash, reset_token_expires_at — nếu lưu trong users).
 * ============================================================
 */

'use strict';

/* ── DOM refs ─────────────────────────────────────────────── */
const verifyForm = document.getElementById('verify-form');
const resetForm = document.getElementById('reset-form');
const authAlert = document.getElementById('auth-alert');
const authSubtitle = document.getElementById('auth-subtitle');

// Step indicator
const stepDot1 = document.querySelector('.step-dot[data-step="1"]');
const stepDot2 = document.querySelector('.step-dot[data-step="2"]');
const stepLine = document.getElementById('step-line');
const stepLabel1 = document.getElementById('label-1');
const stepLabel2 = document.getElementById('label-2');

// Step 1 fields
const emailInput = document.getElementById('fp-email');
const phoneInput = document.getElementById('fp-phone');

// Step 2 fields
const newPasswordInput = document.getElementById('fp-new-password');
const confirmPasswordInput = document.getElementById('fp-confirm-password');
const strengthBar = document.getElementById('strength-bar');
const strengthLabel = document.getElementById('strength-label');
const strengthWrapper = document.getElementById('password-strength');

/* ── State ─────────────────────────────────────────────────── */
let resetToken = null; // nhận từ API verify-identity, dùng ở step 2

/* ═══════════════════════════════════════════════════════════
   UTILITIES
═══════════════════════════════════════════════════════════ */

/**
 * Hiển thị alert lỗi toàn cục.
 * @param {string} message
 * @param {'danger'|'warning'|'info'|'success'} type
 */
function showAlert(message, type = 'danger') {
    authAlert.className = `alert alert--${type}`;
    authAlert.textContent = message;
    authAlert.classList.remove('hidden');
    authAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function hideAlert() {
    authAlert.classList.add('hidden');
}

/**
 * Hiển thị lỗi inline dưới từng field.
 * @param {string} fieldName  - name attribute của input
 * @param {string} message
 */
function showFieldError(fieldName, message) {
    const el = document.querySelector(`[data-error-for="${fieldName}"]`);
    if (!el) return;
    el.textContent = message;
    el.classList.remove('hidden');

    const input = document.querySelector(`[name="${fieldName}"]`);
    if (input) {
        input.style.borderColor = 'var(--color-danger)';
    }
}

function clearFieldErrors() {
    document.querySelectorAll('.form-error').forEach(el => {
        el.textContent = '';
        el.classList.add('hidden');
    });
    document.querySelectorAll('.form-input').forEach(el => {
        el.style.borderColor = '';
    });
}

/**
 * Bật/tắt trạng thái loading trên nút submit.
 * @param {HTMLButtonElement} btn
 * @param {boolean} loading
 */
function setLoading(btn, loading) {
    const spinner = btn.querySelector('.spinner--btn');
    if (loading) {
        btn.classList.add('is-loading');
        spinner?.classList.remove('hidden');
    } else {
        btn.classList.remove('is-loading');
        spinner?.classList.add('hidden');
    }
}

/**
 * Gọi API JSON.
 * @param {string} url
 * @param {object} body
 * @returns {Promise<object>} parsed response JSON
 */
async function apiPost(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    const data = await res.json();
    return { ok: res.ok, status: res.status, data };
}

/* ═══════════════════════════════════════════════════════════
   STEP INDICATOR
═══════════════════════════════════════════════════════════ */

/**
 * Chuyển visual step indicator sang step 2.
 */
function activateStep2() {
    // Step 1 → done
    stepDot1.classList.remove('step-dot--active');
    stepDot1.classList.add('step-dot--done');
    stepLabel1.classList.remove('step-label--active');

    // Line → active
    stepLine.classList.add('step-line--active');

    // Step 2 → active
    stepDot2.classList.add('step-dot--active');
    stepLabel2.classList.add('step-label--active');

    // Cập nhật subtitle
    authSubtitle.textContent = 'Choose a strong new password for your account';
}

/* ═══════════════════════════════════════════════════════════
   TOGGLE SHOW/HIDE PASSWORD
═══════════════════════════════════════════════════════════ */

document.querySelectorAll('.input-wrap__toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const targetId = btn.dataset.toggleFor;
        const input = document.getElementById(targetId);
        if (!input) return;

        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';

        btn.setAttribute('aria-pressed', String(isPassword));
        btn.querySelector('.icon-eye')?.classList.toggle('hidden', isPassword);
        btn.querySelector('.icon-eye-off')?.classList.toggle('hidden', !isPassword);
    });
});

/* ═══════════════════════════════════════════════════════════
   PASSWORD STRENGTH
═══════════════════════════════════════════════════════════ */

/**
 * Đánh giá độ mạnh mật khẩu đơn giản (3 mức).
 * @param {string} pw
 * @returns {'weak'|'medium'|'strong'}
 */
function getStrengthLevel(pw) {
    if (pw.length < 6) return 'weak';
    let score = 0;
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    if (score <= 1) return 'weak';
    if (score <= 2) return 'medium';
    return 'strong';
}

newPasswordInput?.addEventListener('input', () => {
    const pw = newPasswordInput.value;

    if (!pw) {
        strengthWrapper.classList.remove('is-visible');
        return;
    }

    strengthWrapper.classList.add('is-visible');
    const level = getStrengthLevel(pw);
    const labels = { weak: 'Weak', medium: 'Medium', strong: 'Strong' };

    strengthBar.setAttribute('data-level', level);
    strengthLabel.setAttribute('data-level', level);
    strengthLabel.textContent = labels[level];
});

/* ═══════════════════════════════════════════════════════════
   STEP 1 — VERIFY IDENTITY
═══════════════════════════════════════════════════════════ */

verifyForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFieldErrors();
    hideAlert();

    const email = emailInput.value.trim();
    const phone = phoneInput.value.trim();

    /* ── Client-side validation ── */
    let hasError = false;

    if (!email) {
        showFieldError('email', 'Email address is required.');
        hasError = true;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showFieldError('email', 'Please enter a valid email address.');
        hasError = true;
    }

    if (!phone) {
        showFieldError('phone', 'Phone number is required.');
        hasError = true;
    } else if (!/^\+?[\d\s\-()]{7,20}$/.test(phone)) {
        showFieldError('phone', 'Please enter a valid phone number.');
        hasError = true;
    }

    if (hasError) return;

    /* ── Gọi API ── */
    const submitBtn = document.getElementById('verify-submit');
    setLoading(submitBtn, true);

    try {
        const { ok, data } = await apiPost('/api/auth/verify-identity', { email, phone });

        if (!ok) {
            /* Map error_code → thông báo thân thiện */
            const messages = {
                USER_NOT_FOUND: 'No account found with that email and phone combination.',
                ACCOUNT_INACTIVE: 'This account has been deactivated. Please contact support.',
                VALIDATION_ERROR: 'Please check your email and phone number.',
            };
            const msg = messages[data.error_code] || data.message || 'Verification failed. Please try again.';
            showAlert(msg);
            return;
        }

        /* Lưu reset_token để dùng ở step 2 */
        resetToken = data.data?.reset_token ?? null;

        if (!resetToken) {
            showAlert('An unexpected error occurred. Please try again.');
            return;
        }

        /* Animate chuyển form */
        verifyForm.classList.add('is-exiting');
        setTimeout(() => {
            verifyForm.classList.add('hidden');
            verifyForm.classList.remove('is-exiting');
            resetForm.classList.remove('hidden');
            activateStep2();
            newPasswordInput?.focus();
        }, 250);

    } catch (err) {
        console.error('[verify-identity]', err);
        showAlert('Network error. Please check your connection and try again.');
    } finally {
        setLoading(submitBtn, false);
    }
});

/* ═══════════════════════════════════════════════════════════
   STEP 2 — RESET PASSWORD
═══════════════════════════════════════════════════════════ */

resetForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFieldErrors();
    hideAlert();

    const newPassword = newPasswordInput.value;
    const confirmPassword = confirmPasswordInput.value;

    /* ── Client-side validation ── */
    let hasError = false;

    if (!newPassword || newPassword.length < 6) {
        showFieldError('new_password', 'Password must be at least 6 characters.');
        hasError = true;
    }

    if (!confirmPassword) {
        showFieldError('confirm_password', 'Please confirm your new password.');
        hasError = true;
    } else if (newPassword !== confirmPassword) {
        showFieldError('confirm_password', 'Passwords do not match.');
        hasError = true;
    }

    if (!resetToken) {
        showAlert('Session expired. Please start over.');
        return;
    }

    if (hasError) return;

    /* ── Gọi API ── */
    const submitBtn = document.getElementById('reset-submit');
    setLoading(submitBtn, true);

    try {
        const { ok, data } = await apiPost('/api/auth/reset-password', {
            reset_token: resetToken,
            new_password: newPassword,
        });

        if (!ok) {
            const messages = {
                VALIDATION_ERROR: 'Please check your new password.',
                INVALID_RESET_TOKEN: 'Your session has expired. Please start over.',
                RESET_TOKEN_USED: 'This reset link has already been used.',
                USER_NOT_FOUND: 'Account not found. Please start over.',
            };
            const msg = messages[data.error_code] || data.message || 'Password reset failed. Please try again.';

            /* Token hết hạn → đẩy về step 1 */
            if (['INVALID_RESET_TOKEN', 'RESET_TOKEN_USED'].includes(data.error_code)) {
                showAlert(msg, 'warning');
                setTimeout(() => location.reload(), 2500);
                return;
            }

            showAlert(msg);
            return;
        }

        /* Thành công — hiện thông báo rồi redirect về login */
        showAlert('Password reset successfully! Redirecting to login…', 'success');
        resetToken = null;

        setTimeout(() => {
            window.location.href = '/views/auth/user-login.php';
        }, 2000);

    } catch (err) {
        console.error('[reset-password]', err);
        showAlert('Network error. Please check your connection and try again.');
    } finally {
        setLoading(submitBtn, false);
    }
});

/* ── Xóa border lỗi khi user bắt đầu gõ lại ── */
document.querySelectorAll('.form-input').forEach(input => {
    input.addEventListener('input', () => {
        input.style.borderColor = '';
        const errorEl = document.querySelector(`[data-error-for="${input.name}"]`);
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
        }
        hideAlert();
    });
});