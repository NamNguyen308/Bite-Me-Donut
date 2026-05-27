/**
 * forgot-password.js
 * Không gọi API trực tiếp.
 * JS gọi forgot_password.php?forgot_password_ajax=1.
 * PHP view sẽ proxy request sang backend API.
 */

'use strict';

const CONFIG = window.FORGOT_PASSWORD_CONFIG || {};

const PROXY_URL = CONFIG.proxyUrl || `${window.location.pathname}?forgot_password_ajax=1`;
const LOGIN_URL = CONFIG.loginUrl || './user-login.php';

const verifyForm = document.getElementById('verify-form');
const resetForm = document.getElementById('reset-form');
const authAlert = document.getElementById('auth-alert');
const authSubtitle = document.getElementById('auth-subtitle');

const stepDot1 = document.querySelector('.step-dot[data-step="1"]');
const stepDot2 = document.querySelector('.step-dot[data-step="2"]');
const stepLine = document.getElementById('step-line');
const stepLabel1 = document.getElementById('label-1');
const stepLabel2 = document.getElementById('label-2');

const emailInput = document.getElementById('fp-email');
const phoneInput = document.getElementById('fp-phone');

const newPasswordInput = document.getElementById('fp-new-password');
const confirmPasswordInput = document.getElementById('fp-confirm-password');
const strengthBar = document.getElementById('strength-bar');
const strengthLabel = document.getElementById('strength-label');
const strengthWrapper = document.getElementById('password-strength');

let resetToken = null;

function showAlert(message, type = 'danger') {
    if (!authAlert) return;

    authAlert.className = `alert alert--${type}`;
    authAlert.textContent = message;
    authAlert.classList.remove('hidden');
    authAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function hideAlert() {
    if (!authAlert) return;

    authAlert.classList.add('hidden');
}

function showFieldError(fieldName, message) {
    const errorElement = document.querySelector(`[data-error-for="${fieldName}"]`);

    if (!errorElement) return;

    errorElement.textContent = message;
    errorElement.classList.remove('hidden');

    const input = document.querySelector(`[name="${fieldName}"]`);

    if (input) {
        input.style.borderColor = 'var(--color-danger)';
    }
}

function clearFieldErrors() {
    document.querySelectorAll('.form-error').forEach((element) => {
        element.textContent = '';
        element.classList.add('hidden');
    });

    document.querySelectorAll('.form-input').forEach((element) => {
        element.style.borderColor = '';
    });
}

function setLoading(button, loading) {
    if (!button) return;

    const spinner = button.querySelector('.spinner--btn');

    if (loading) {
        button.disabled = true;
        button.classList.add('is-loading');
        spinner?.classList.remove('hidden');
    } else {
        button.disabled = false;
        button.classList.remove('is-loading');
        spinner?.classList.add('hidden');
    }
}

function normalizePhone(phone) {
    return String(phone || '')
        .replace(/\s+/g, '')
        .replace(/-/g, '')
        .replace(/\(/g, '')
        .replace(/\)/g, '')
        .trim();
}

async function postForgotPassword(action, payload) {
    const requestBody = {
        action,
        ...payload
    };

    console.log('[FORGOT PASSWORD POST]', PROXY_URL, requestBody);

    const response = await fetch(PROXY_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(requestBody)
    });

    const rawText = await response.text();

    console.log('[FORGOT PASSWORD STATUS]', response.status);
    console.log('[FORGOT PASSWORD RAW]', rawText);

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
                message: `Server did not return JSON. Status: ${response.status}`,
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

function activateStep2() {
    stepDot1?.classList.remove('step-dot--active');
    stepDot1?.classList.add('step-dot--done');
    stepLabel1?.classList.remove('step-label--active');

    stepLine?.classList.add('step-line--active');

    stepDot2?.classList.add('step-dot--active');
    stepLabel2?.classList.add('step-label--active');

    if (authSubtitle) {
        authSubtitle.textContent = 'Choose a strong new password for your account';
    }
}

document.querySelectorAll('.input-wrap__toggle').forEach((button) => {
    button.addEventListener('click', () => {
        const targetId = button.dataset.toggleFor;
        const input = document.getElementById(targetId);

        if (!input) return;

        const isPassword = input.type === 'password';

        input.type = isPassword ? 'text' : 'password';

        button.setAttribute('aria-pressed', String(isPassword));
        button.querySelector('.icon-eye')?.classList.toggle('hidden', isPassword);
        button.querySelector('.icon-eye-off')?.classList.toggle('hidden', !isPassword);
    });
});

function getStrengthLevel(password) {
    if (password.length < 6) return 'weak';

    let score = 0;

    if (password.length >= 8) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    if (score <= 1) return 'weak';
    if (score <= 2) return 'medium';

    return 'strong';
}

newPasswordInput?.addEventListener('input', () => {
    const password = newPasswordInput.value;

    if (!password) {
        strengthWrapper?.classList.remove('is-visible');
        return;
    }

    strengthWrapper?.classList.add('is-visible');

    const level = getStrengthLevel(password);

    const labels = {
        weak: 'Weak',
        medium: 'Medium',
        strong: 'Strong'
    };

    strengthBar?.setAttribute('data-level', level);
    strengthLabel?.setAttribute('data-level', level);

    if (strengthLabel) {
        strengthLabel.textContent = labels[level];
    }
});

verifyForm?.addEventListener('submit', async (event) => {
    event.preventDefault();

    clearFieldErrors();
    hideAlert();

    const email = emailInput.value.trim();
    const phone = normalizePhone(phoneInput.value);

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
    } else if (!/^\+?\d{7,20}$/.test(phone)) {
        showFieldError('phone', 'Please enter a valid phone number.');
        hasError = true;
    }

    if (hasError) return;

    const submitButton = document.getElementById('verify-submit');

    setLoading(submitButton, true);

    try {
        const { ok, data } = await postForgotPassword('verify_identity', {
            email,
            phone
        });

        if (!ok) {
            const messages = {
                USER_NOT_FOUND: 'No account found with that email and phone combination.',
                ACCOUNT_INACTIVE: 'This account has been deactivated. Please contact support.',
                VALIDATION_ERROR: 'Please check your email and phone number.',
                API_PROXY_INVALID_JSON: data.message,
                API_PROXY_CURL_ERROR: data.message,
                API_PROXY_REQUEST_FAILED: data.message
            };

            const message = messages[data.error_code] || data.message || 'Verification failed. Please try again.';

            showAlert(message);
            return;
        }

        resetToken = data.data?.reset_token ?? null;

        if (!resetToken) {
            showAlert('Reset token missing. Please try again.');
            return;
        }

        verifyForm.classList.add('is-exiting');

        setTimeout(() => {
            verifyForm.classList.add('hidden');
            verifyForm.classList.remove('is-exiting');

            resetForm?.classList.remove('hidden');

            activateStep2();

            newPasswordInput?.focus();
        }, 250);
    } catch (error) {
        console.error('[verify_identity]', error);
        showAlert('Network error. Please check your connection and try again.');
    } finally {
        setLoading(submitButton, false);
    }
});

resetForm?.addEventListener('submit', async (event) => {
    event.preventDefault();

    clearFieldErrors();
    hideAlert();

    const newPassword = newPasswordInput.value;
    const confirmPassword = confirmPasswordInput.value;

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

    const submitButton = document.getElementById('reset-submit');

    setLoading(submitButton, true);

    try {
        const { ok, data } = await postForgotPassword('reset_password', {
            reset_token: resetToken,
            new_password: newPassword
        });

        if (!ok) {
            const messages = {
                VALIDATION_ERROR: 'Please check your new password.',
                INVALID_RESET_TOKEN: 'Your session has expired. Please start over.',
                RESET_TOKEN_USED: 'This reset token has already been used.',
                USER_NOT_FOUND: 'Account not found. Please start over.',
                API_PROXY_INVALID_JSON: data.message,
                API_PROXY_CURL_ERROR: data.message,
                API_PROXY_REQUEST_FAILED: data.message
            };

            const message = messages[data.error_code] || data.message || 'Password reset failed. Please try again.';

            if (['INVALID_RESET_TOKEN', 'RESET_TOKEN_USED'].includes(data.error_code)) {
                showAlert(message, 'warning');

                setTimeout(() => {
                    location.reload();
                }, 2500);

                return;
            }

            showAlert(message);
            return;
        }

        showAlert('Password reset successfully! Redirecting to login…', 'success');

        resetToken = null;

        setTimeout(() => {
            window.location.href = LOGIN_URL;
        }, 2000);
    } catch (error) {
        console.error('[reset_password]', error);
        showAlert('Network error. Please check your connection and try again.');
    } finally {
        setLoading(submitButton, false);
    }
});

document.querySelectorAll('.form-input').forEach((input) => {
    input.addEventListener('input', () => {
        input.style.borderColor = '';

        const errorElement = document.querySelector(`[data-error-for="${input.name}"]`);

        if (errorElement) {
            errorElement.textContent = '';
            errorElement.classList.add('hidden');
        }

        hideAlert();
    });
});