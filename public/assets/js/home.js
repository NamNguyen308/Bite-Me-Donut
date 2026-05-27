 document.addEventListener('DOMContentLoaded', async () => {
    // Check logout button
    document.getElementById('global-btn-logout')?.addEventListener('click', async () => {
        if(window.Api && window.Api.auth) {
            await window.Api.auth.logout();
            window.location.href = 'home.php';
        }
    });


    const isLoggedIn = window.Api.Auth.isLoggedIn();
    const logoutBtn = document.getElementById('global-btn-logout');
    const loginLink = document.getElementById('nav-login-link');


    if (!isLoggedIn) {
        // Show public landing page for non-logged-in users
        document.getElementById('welcome-name').textContent = `Welcome to Bite Me Donut!`;
        document.getElementById('welcome-message').textContent = `Discover our delicious selection of fresh donuts and treats.`;
        document.getElementById('nav-user-name').textContent = '';
       
        // Hide account details for non-logged-in users
        const accountCard = document.querySelector('section.section.container .card');
        if (accountCard) {
            accountCard.style.display = 'none';
        }
       
        // Show login link and hide logout button
        if (loginLink) loginLink.style.display = 'inline-flex';
        if (logoutBtn) logoutBtn.style.display = 'none';
    } else {
        // Show personalized content for logged-in users
        const response = await window.Api.users.me();
       
        if (response.success && response.data) {
            const user = response.data;
            const displayName = user.name || user.full_name || 'Donut Lover';
            const displayContact = user.email || user.phone || 'Not provided';
            const displayRole = user.role || 'Customer';


            document.getElementById('nav-user-name').textContent = `Hi, ${displayName}!`;
            document.getElementById('welcome-name').textContent = `Welcome back, ${displayName}!`;
            document.getElementById('welcome-message').textContent = `Ready for some sweet, freshly baked treats today?`;
           
            document.getElementById('user-profile-email').textContent = displayContact;
            document.getElementById('user-profile-role').textContent = `Role: ${displayRole.toUpperCase()}`;
           
            // Hide login link and show logout button
            if (loginLink) loginLink.style.display = 'none';
            if (logoutBtn) logoutBtn.style.display = 'inline-flex';
        } else {
            // Session expired - clear and show public view
            window.Api.Auth.clearSession();
            window.location.reload();
        }
    }
});





