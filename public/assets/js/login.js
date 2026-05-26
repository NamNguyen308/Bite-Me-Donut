document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const identifierInput = document.getElementById('identifier');
    const passwordInput = document.getElementById('password');
    const errorMessage = document.getElementById('errorMessage');
    const loginBtn = document.getElementById('loginBtn');
    const btnText = loginBtn.querySelector('.btn-text');
    const loader = loginBtn.querySelector('.loader');

    // Make sure API route matches backend exactly
    const API_BASE_URL = 'api'; // Use relative to support both XAMPP & Laragon

    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.classList.add('show');

        // Remove animation class after it completes so it can be re-triggered
        setTimeout(() => {
            errorMessage.classList.remove('show');
            // Re-add to keep it visible without animation
            errorMessage.style.display = 'block';
            errorMessage.style.animation = 'none';
        }, 500);
    }

    function hideError() {
        errorMessage.style.display = 'none';
        errorMessage.classList.remove('show');
    }

    function setLoading(isLoading) {
        if (isLoading) {
            loginBtn.disabled = true;
            btnText.classList.add('hidden');
            loader.classList.remove('hidden');
        } else {
            loginBtn.disabled = false;
            btnText.classList.remove('hidden');
            loader.classList.add('hidden');
        }
    }

    function isEmail(str) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(str);
    }

    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideError();

        const identifier = identifierInput.value.trim();
        const password = passwordInput.value;

        if (!identifier || !password) {
            showError('Please enter both identifier and password.');
            return;
        }

        setLoading(true);

        try {
            // Determine if identifier is email or phone
            const payload = {
                password: password
            };

            if (isEmail(identifier)) {
                payload.email = identifier;
            } else {
                payload.phone = identifier;
            }

            // Step 1: POST /api/auth/login
            const loginResponse = await fetch(`${API_BASE_URL}/auth/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const loginData = await loginResponse.json();

            if (!loginResponse.ok || !loginData.success) {
                // Return generic error or specific risk message
                if (loginData.error_code === 'RISK_LEVEL_HIGH') {
                    throw new Error('Login temporarily blocked due to high risk. Please try again later.');
                }
                throw new Error(loginData.message || 'Login failed. Please check your credentials.');
            }

            const loginChallengeId = loginData.data.login_challenge_id;

            // Save login_challenge_id to sessionStorage
            sessionStorage.setItem('login_challenge_id', loginChallengeId);

            // Step 2: POST /api/otp/request
            const otpRequestResponse = await fetch(`${API_BASE_URL}/otp/request`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    login_challenge_id: loginChallengeId
                })
            });

            const otpData = await otpRequestResponse.json();

            if (!otpRequestResponse.ok || !otpData.success) {
                if (otpData.error_code === 'RISK_LEVEL_HIGH') {
                    throw new Error('OTP request is temporarily blocked due to high risk.');
                } else if (otpData.error_code === 'OTP_RESEND_LIMIT_EXCEEDED') {
                    throw new Error('OTP request limit exceeded.');
                }
                throw new Error(otpData.message || 'Failed to request OTP.');
            }

            // Success - Redirect to otp.php
            setTimeout(() => {
                window.location.href = 'otp.php';
            }, 500);

        } catch (error) {
            showError(error.message);
            setLoading(false);
        }
    });

    // Add interactive effects for inputs
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('focus', () => hideError());
    });
});
