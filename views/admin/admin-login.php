<?php
/**
 * Admin Login Page
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Bite-Me-Donut</title>
    <link rel="stylesheet" href="../../public/assets/css/root.css">
    <link rel="stylesheet" href="../../public/assets/css/admin-login.css">
</head>
<body>
    <main class="admin-login-page">
        <div class="admin-login-card">
            <div class="admin-login-logo">
                <svg viewBox="0 0 48 48" width="32" height="32">
                    <circle cx="24" cy="24" r="20" fill="var(--color-primary)"></circle>
                    <circle cx="24" cy="24" r="7.5" fill="var(--color-bg-card)"></circle>
                </svg>
            </div>
            <h1 class="admin-login-title">Admin Login</h1>
            
            <div id="admin-alert" class="admin-alert hidden"></div>
            
            <form id="admin-login-form">
                <div class="admin-form-group">
                    <label class="admin-form-label" for="email">Email</label>
                    <input type="email" id="email" class="admin-form-input" required placeholder="admin@example.com">
                </div>
                
                <div class="admin-form-group">
                    <label class="admin-form-label" for="password">Password</label>
                    <input type="password" id="password" class="admin-form-input" required>
                </div>
                
                <button type="submit" class="btn btn--primary btn--lg w-full" style="width:100%; margin-top:var(--space-4)">Login</button>
            </form>
        </div>
    </main>

    <script src="../../public/assets/js/api.js"></script>
    <script src="../../public/assets/js/admin-login.js"></script>
</body>
</html>
