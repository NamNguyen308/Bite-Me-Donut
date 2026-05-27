document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('admin-login-form');
    const alertBox = document.getElementById('admin-alert');

    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const email = document.getElementById('email').value;
        const password = document.getElementById('password').value;
        const btn = form.querySelector('button[type="submit"]');

        alertBox.classList.add('hidden');
        btn.disabled = true;
        btn.textContent = 'Logging in...';

        try {
            const response = await fetch('/api/admin/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });

            const data = await response.json();

            if (data.status === 'success') {
                // Save token
                localStorage.setItem('bmd_access_token', data.data.access_token);
                localStorage.setItem('bmd_user', JSON.stringify(data.data.user));
                
                // Redirect
                window.location.href = '../../views/admin/dashboard.php';
            } else {
                alertBox.textContent = data.message || 'Login failed';
                alertBox.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Login error:', error);
            alertBox.textContent = 'An error occurred. Please try again.';
            alertBox.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Login';
        }
    });
});
