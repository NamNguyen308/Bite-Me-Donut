/**
 * ============================================================
 * otp.js — OTP Verification Page Logic
 * View: views/auth/otp.php
 *
 * Flow:
 *   1. On page load: read login_challenge_id from sessionStorage.
 *      If missing, redirect to user-login.php immediately.
 *   2. Auto-call POST /api/otp/request to trigger Twilio voice call.
 *   3. Start 5-minute countdown timer.
 *   4. When user fills all 6 digits, enable the submit button.
 *   5. On submit: POST /api/otp/verify.
 *   6. On success: POST /api/auth/complete-login.
 *   7. Store access_token in localStorage, clear sessionStorage
 *      challenge data, redirect to home.php.
 *
 * Error handling:
 *   - RISK_LEVEL_HIGH / LOGIN_CHALLENGE_BLOCKED → show message, lock form.
 *   - OTP_TOO_MANY_ATTEMPTS / OTP_RESEND_LIMIT_EXCEEDED → show specific message.
 *   - SMS_SEND_FAILED → show error, allow retry via resend button.
 *   - Any provider failure → show generic error.
 *
 * Architecture rules respected:
 *   - This JS is the "client" layer only — no business logic.
 *   - All validation & risk decisions are made by the backend.
 *   - Plain access_token stored in localStorage after complete-login.
 * ============================================================
 */

(() => {
    'use strict';

    /* ------------------------------------------------------------------
       CONSTANTS
    ------------------------------------------------------------------ */
    const API_BASE = '/api';
    const REDIRECT_HOME = '/views/home.php';
    const REDIRECT_LOGIN = '/views/auth/user-login.php';
    const OTP_EXPIRE_SECONDS = 5 * 60;   // 5 minutes (matches .env OTP_EXPIRE_MINUTES=5)
    const SESSION_KEY_CHALLENGE = 'login_challenge_id';
    const SESSION_KEY_PHONE = 'otp_phone';            // stored by user-login.js
    const STORAGE_KEY_TOKEN = 'access_token';
    const STORAGE_KEY_USER = 'auth_user';

    /* ------------------------------------------------------------------
       DOM REFERENCES
    ------------------------------------------------------------------ */
    const otpForm = document.getElementById('otp-form');
    const otpDigits = Array.from(document.querySelectorAll('.otp-digit'));
    const otpSubmitBtn = document.getElementById('otp-submit');
    const otpSpinner = document.getElementById('otp-spinner');
    const submitLabel = otpSubmitBtn.querySelector('.btn-label');
    const otpAlert = document.getElementById('otp-alert');
    const callBanner = document.getElementById('call-status-banner');
    const callStatusText = document.getElementById('call-status-text');
    const countdownEl = document.getElementById('otp-countdown');
    const countdownWrap = document.getElementById('otp-countdown-wrap');
    const resendBtn = document.getElementById('resend-btn');
    const otpCard = document.querySelector('.otp-card');

    /* ------------------------------------------------------------------
       STATE
    ------------------------------------------------------------------ */
    let challengeId = sessionStorage.getItem(SESSION_KEY_CHALLENGE);
    let phone = sessionStorage.getItem(SESSION_KEY_PHONE);
    let countdownTimer = null;
    let secondsLeft = OTP_EXPIRE_SECONDS;
    let isSubmitting = false;
    let resendCount = 0;
    const MAX_RESEND = 3;   // mirrors OTP_MAX_RESEND in .env

    /* ------------------------------------------------------------------
       INIT GUARD — redirect if no challenge_id
    ------------------------------------------------------------------ */
    if (!challengeId) {
        window.location.replace(REDIRECT_LOGIN);
        return; // stop execution
    }

    /* ------------------------------------------------------------------
       HELPERS
    ------------------------------------------------------------------ */

    /**
     * Show / hide the alert banner.
     * @param {string} message  - HTML-escaped user-facing message.
     * @param {'danger'|'warning'|'success'} type
     */
    function showAlert(message, type = 'danger') {
        otpAlert.className = `alert alert--${type}`;
        otpAlert.textContent = message;
        otpAlert.classList.remove('hidden');
    }

    function hideAlert() {
        otpAlert.classList.add('hidden');
    }

    /**
     * Update the call-status banner.
     * @param {'calling'|'sent'|'error'} state
     * @param {string} text
     */
    function setCallBanner(state, text) {
        callBanner.className = `call-status-banner call-status-banner--${state}`;
        callStatusText.textContent = text;
    }

    /** Collect the 6 OTP digits into a string. */
    function getOtpCode() {
        return otpDigits.map(d => d.value).join('');
    }

    /** Enable or disable the submit button. */
    function syncSubmitState() {
        const code = getOtpCode();
        otpSubmitBtn.disabled = code.length < 6;
    }

    /** Show loading state on submit button. */
    function setSubmitLoading(loading) {
        isSubmitting = loading;
        if (loading) {
            otpSubmitBtn.classList.add('is-loading');
            otpSpinner.classList.remove('hidden');
            submitLabel.style.visibility = 'hidden';
            otpSubmitBtn.disabled = true;
        } else {
            otpSubmitBtn.classList.remove('is-loading');
            otpSpinner.classList.add('hidden');
            submitLabel.style.visibility = '';
            syncSubmitState();
        }
    }

    /** Mark all digit inputs as error state. */
    function shakeDigits() {
        otpDigits.forEach(d => {
            d.classList.remove('is-error');
            void d.offsetWidth; // force reflow so animation replays
            d.classList.add('is-error');
        });
        // Remove error class after animation completes
        setTimeout(() => {
            otpDigits.forEach(d => d.classList.remove('is-error'));
        }, 600);
    }

    /** Clear all digit inputs. */
    function clearDigits() {
        otpDigits.forEach(d => {
            d.value = '';
            d.classList.remove('is-filled', 'is-error');
        });
        otpDigits[0].focus();
        syncSubmitState();
    }

    /**
     * Generic API call wrapper.
     * Returns { ok: true, data } or { ok: false, error_code, message }.
     */
    async function apiPost(endpoint, body = {}) {
        try {
            const res = await fetch(`${API_BASE}${endpoint}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (data.success) {
                return { ok: true, data };
            }
            return { ok: false, error_code: data.error_code, message: data.message, data };
        } catch (err) {
            console.error(`API error [${endpoint}]:`, err);
            return { ok: false, error_code: 'NETWORK_ERROR', message: 'Network error. Please check your connection.' };
        }
    }

    /* ------------------------------------------------------------------
       COUNTDOWN TIMER
    ------------------------------------------------------------------ */

    function formatTime(seconds) {
        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    }

    function startCountdown(durationSeconds = OTP_EXPIRE_SECONDS) {
        clearInterval(countdownTimer);
        secondsLeft = durationSeconds;
        countdownEl.textContent = formatTime(secondsLeft);
        countdownWrap.classList.remove('hidden', 'is-urgent');
        resendBtn.classList.add('hidden');

        countdownTimer = setInterval(() => {
            secondsLeft--;

            if (secondsLeft <= 0) {
                clearInterval(countdownTimer);
                countdownWrap.classList.add('hidden');
                showResendButton();
                otpForm.querySelector('#otp-submit').disabled = true;
                showAlert('Your verification code has expired. Please request a new call.', 'warning');
                return;
            }

            countdownEl.textContent = formatTime(secondsLeft);

            // Urgency warning at < 60 s
            if (secondsLeft < 60) {
                countdownWrap.classList.add('is-urgent');
            }
        }, 1000);
    }

    function showResendButton() {
        if (resendCount >= MAX_RESEND) {
            resendBtn.textContent = 'Maximum resend limit reached';
            resendBtn.disabled = true;
            resendBtn.classList.remove('hidden');
            return;
        }
        resendBtn.classList.remove('hidden');
        resendBtn.disabled = false;
    }

    /* ------------------------------------------------------------------
       STEP 2 — POST /api/otp/request (auto on page load)
    ------------------------------------------------------------------ */

    async function requestOtp() {
        setCallBanner('calling', 'Initiating voice call…');
        hideAlert();

        const result = await apiPost('/otp/request', { login_challenge_id: challengeId });

        if (!result.ok) {
            handleRequestError(result.error_code, result.message);
            return;
        }

        resendCount++;
        setCallBanner('sent', 'Voice call placed! Listen for your 6-digit code.');
        startCountdown(OTP_EXPIRE_SECONDS);
        otpDigits[0].focus();
    }

    function handleRequestError(code, message) {
        setCallBanner('error', 'Could not place the call.');

        switch (code) {
            case 'RISK_LEVEL_HIGH':
            case 'LOGIN_CHALLENGE_BLOCKED':
                showAlert('Your session has been blocked due to suspicious activity. Please log in again.', 'danger');
                lockFormPermanently();
                scheduleLoginRedirect(4000);
                break;

            case 'OTP_RESEND_LIMIT_EXCEEDED':
                showAlert('You have requested too many codes. Please log in again.', 'danger');
                lockFormPermanently();
                scheduleLoginRedirect(4000);
                break;

            case 'LOGIN_CHALLENGE_EXPIRED':
                showAlert('Your login session has expired. Please log in again.', 'warning');
                scheduleLoginRedirect(3000);
                break;

            case 'SMS_SEND_FAILED':
                showAlert('We could not place the call right now. Please try requesting a new one.', 'danger');
                showResendButton();
                break;

            default:
                showAlert(message || 'Failed to send verification call. Please try again.', 'danger');
                showResendButton();
        }
    }

    /* ------------------------------------------------------------------
       STEP 3 — POST /api/otp/verify
    ------------------------------------------------------------------ */

    async function verifyOtp(otpCode) {
        const result = await apiPost('/otp/verify', {
            login_challenge_id: challengeId,
            otp_code: otpCode,
        });

        if (!result.ok) {
            handleVerifyError(result.error_code, result.message);
            return false;
        }

        return true; // OTP_VERIFIED
    }

    function handleVerifyError(code, message) {
        shakeDigits();
        clearDigits();
        setSubmitLoading(false);

        switch (code) {
            case 'OTP_INVALID':
                showAlert('Incorrect code. Please check the code you heard and try again.', 'danger');
                break;

            case 'OTP_EXPIRED':
            case 'OTP_USED':
                showAlert('This code is no longer valid. Please request a new call.', 'warning');
                clearInterval(countdownTimer);
                countdownWrap.classList.add('hidden');
                showResendButton();
                break;

            case 'OTP_TOO_MANY_ATTEMPTS':
            case 'RISK_LEVEL_HIGH':
            case 'LOGIN_CHALLENGE_BLOCKED':
                showAlert('Too many incorrect attempts. Your session has been locked. Please log in again.', 'danger');
                lockFormPermanently();
                scheduleLoginRedirect(4000);
                break;

            case 'LOGIN_CHALLENGE_EXPIRED':
                showAlert('Your session has expired. Please log in again.', 'warning');
                scheduleLoginRedirect(3000);
                break;

            case 'OTP_PROVIDER_VERIFY_FAILED':
                showAlert('Verification service is temporarily unavailable. Please try again.', 'danger');
                break;

            default:
                showAlert(message || 'Verification failed. Please try again.', 'danger');
        }
    }

    /* ------------------------------------------------------------------
       STEP 4 — POST /api/auth/complete-login
    ------------------------------------------------------------------ */

    async function completeLogin() {
        const result = await apiPost('/auth/complete-login', {
            login_challenge_id: challengeId,
        });

        if (!result.ok) {
            setSubmitLoading(false);
            showAlert(result.message || 'Login could not be completed. Please try again.', 'danger');
            return false;
        }

        const { access_token, user } = result.data;

        // Persist token and user data
        localStorage.setItem(STORAGE_KEY_TOKEN, access_token);
        if (user) {
            localStorage.setItem(STORAGE_KEY_USER, JSON.stringify(user));
        }

        // Clean up session storage
        sessionStorage.removeItem(SESSION_KEY_CHALLENGE);
        sessionStorage.removeItem(SESSION_KEY_PHONE);

        return true;
    }

    /* ------------------------------------------------------------------
       FORM SUBMIT — orchestrates verify + complete-login
    ------------------------------------------------------------------ */

    otpForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (isSubmitting) return;

        const otpCode = getOtpCode();
        if (otpCode.length < 6) return;

        hideAlert();
        setSubmitLoading(true);

        // Step 3: Verify OTP
        const verified = await verifyOtp(otpCode);
        if (!verified) return; // errors already handled

        // Step 4: Complete login
        const loggedIn = await completeLogin();
        if (!loggedIn) return;

        // Success — animate card and redirect
        clearInterval(countdownTimer);
        otpCard.classList.add('is-success');

        setTimeout(() => {
            window.location.replace(REDIRECT_HOME);
        }, 700);
    });

    /* ------------------------------------------------------------------
       RESEND BUTTON — request a new Twilio call
    ------------------------------------------------------------------ */

    resendBtn.addEventListener('click', async () => {
        if (resendCount >= MAX_RESEND) return;

        clearDigits();
        hideAlert();
        resendBtn.disabled = true;
        resendBtn.classList.add('hidden');

        await requestOtp();
    });

    /* ------------------------------------------------------------------
       OTP DIGIT INPUT BEHAVIOUR
       - Auto-advance on digit entry.
       - Backspace moves to previous field.
       - Paste support for full 6-digit code.
       - Only allow numeric characters.
    ------------------------------------------------------------------ */

    otpDigits.forEach((input, idx) => {
        /* Filter non-numeric characters */
        input.addEventListener('keydown', (e) => {
            const allowed = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Enter'];
            if (!allowed.includes(e.key) && !/^\d$/.test(e.key)) {
                e.preventDefault();
            }
        });

        input.addEventListener('input', (e) => {
            const val = input.value.replace(/\D/g, '');

            // Keep only the last digit typed (handles mobile keyboards sending full value)
            input.value = val ? val[val.length - 1] : '';

            if (input.value) {
                input.classList.add('is-filled');
                // Advance to next
                if (idx < otpDigits.length - 1) {
                    otpDigits[idx + 1].focus();
                }
            } else {
                input.classList.remove('is-filled');
            }

            syncSubmitState();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !input.value && idx > 0) {
                // Move to previous on backspace when empty
                otpDigits[idx - 1].focus();
                otpDigits[idx - 1].value = '';
                otpDigits[idx - 1].classList.remove('is-filled');
                syncSubmitState();
            }
        });

        /* Click to position cursor at end */
        input.addEventListener('focus', () => {
            setTimeout(() => {
                input.setSelectionRange(input.value.length, input.value.length);
            }, 0);
        });
    });

    /* Paste: spread digits across inputs */
    otpDigits[0].addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData)
            .getData('text')
            .replace(/\D/g, '')
            .slice(0, 6);

        pasted.split('').forEach((char, i) => {
            if (otpDigits[i]) {
                otpDigits[i].value = char;
                otpDigits[i].classList.add('is-filled');
            }
        });

        // Focus the next empty or last input
        const nextEmpty = otpDigits.find(d => !d.value);
        (nextEmpty || otpDigits[otpDigits.length - 1]).focus();
        syncSubmitState();
    });

    /* ------------------------------------------------------------------
       UTILITY — lock form permanently (blocked / too many attempts)
    ------------------------------------------------------------------ */

    function lockFormPermanently() {
        clearInterval(countdownTimer);
        otpDigits.forEach(d => { d.disabled = true; });
        otpSubmitBtn.disabled = true;
        resendBtn.disabled = true;
        resendBtn.classList.add('hidden');
        countdownWrap.classList.add('hidden');
    }

    function scheduleLoginRedirect(ms = 3000) {
        setTimeout(() => {
            window.location.replace(REDIRECT_LOGIN);
        }, ms);
    }

    /* ------------------------------------------------------------------
       AUTO-REQUEST OTP ON PAGE LOAD
    ------------------------------------------------------------------ */
    requestOtp();

})();