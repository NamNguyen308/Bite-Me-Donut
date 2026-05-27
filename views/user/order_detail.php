<?php
// VIEW/order-detail.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Bite Me Donut</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800&display=swap');
        :root { --color-bg: #fef9e7; --color-bg-alt: #fdf3c8; --color-bg-card: #ffffff; --color-primary: #e91e8c; --color-primary-dark: #c01575; --color-text: #2b1a0e; --color-text-muted: #7a6352; --color-border: #e8dbc8; --color-danger: #e53935; --color-success: #43a047; --color-warning: #fb8c00; --font-heading: 'Fredoka One', cursive; --font-body: 'Nunito', sans-serif; --text-xs: 0.75rem; --text-sm: 0.875rem; --text-base: 1rem; --text-md: 1.125rem; --text-xl: 1.5rem; --text-3xl: 2.5rem; --font-weight-bold: 700; --font-weight-black: 800; --space-1: 0.25rem; --space-2: 0.5rem; --space-3: 0.75rem; --space-4: 1rem; --space-6: 1.5rem; --space-8: 2rem; --space-16: 4rem; --radius-sm: 4px; --radius-md: 8px; --radius-full: 9999px; --border-width: 1px; --shadow-lg: 0 8px 32px rgba(43,26,14,0.15); --transition-fast: 150ms ease; --container-max: 1200px; --navbar-height: 68px; }
        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-size: 16px; scroll-behavior: smooth; }
        body { font-family: var(--font-body); font-size: var(--text-base); color: var(--color-text); background-color: var(--color-bg); line-height: 1.6; min-height: 100vh; }
        a { color: var(--color-primary); text-decoration: none; transition: color var(--transition-fast); } a:hover { color: var(--color-primary-dark); }
        img { max-width: 100%; display: block; }
        h1, h3, .page-title { font-family: var(--font-heading); font-weight: 400; line-height: 1.2; }
        .page-title { font-size: var(--text-3xl); margin-bottom: var(--space-6); }
        .text-left { text-align: left; } .text-center { text-align: center; } .text-right { text-align: right; } .text-danger { color: var(--color-danger); } .text-primary { color: var(--color-primary); } .text-muted { color: var(--color-text-muted); }
        .font-bold { font-weight: var(--font-weight-bold); } .font-black { font-weight: var(--font-weight-black); }
        .text-xs { font-size: var(--text-xs); } .text-sm { font-size: var(--text-sm); } .text-xl { font-size: var(--text-xl); }
        .container { max-width: var(--container-max); margin: 0 auto; padding: 0 var(--space-6); }
        .section { padding: var(--space-16) 0; }
        .flex { display: flex; } .flex-col { display: flex; flex-direction: column; } .items-center { align-items: center; } .justify-between { justify-content: space-between; } .gap-3 { gap: var(--space-3); } .gap-4 { gap: var(--space-4); }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-8); }
        .navbar { position: sticky; top: 0; z-index: 200; background: var(--color-bg); border-bottom: var(--border-width) solid var(--color-border); height: var(--navbar-height); display: flex; align-items: center; }
        .navbar__inner { display: flex; align-items: center; justify-content: space-between; width: 100%; max-width: var(--container-max); margin: 0 auto; padding: 0 var(--space-6); }
        .navbar__logo { font-family: var(--font-heading); font-size: var(--text-xl); color: var(--color-text); }
        .navbar__nav { display: flex; gap: var(--space-1); }
        .navbar__link { font-size: var(--text-sm); font-weight: var(--font-weight-bold); padding: var(--space-2) var(--space-4); text-transform: uppercase; color: var(--color-text); } .navbar__link.active { color: var(--color-primary); }
        .navbar__actions { display: flex; align-items: center; gap: var(--space-4); }
        .navbar__icon-btn { color: var(--color-text); display: flex; align-items: center; cursor: pointer; } .navbar__icon-btn:hover { color: var(--color-primary); }
        .btn { display: inline-flex; align-items: center; justify-content: center; font-weight: var(--font-weight-bold); text-transform: uppercase; padding: var(--space-3) var(--space-6); border-radius: var(--radius-sm); cursor: pointer; border: none; font-size: var(--text-sm); }
        .btn--primary { background: var(--color-primary); color: white; }
        .btn--danger { background: var(--color-danger); color: white; font-size: var(--text-xs); padding: var(--space-2) var(--space-4); }
        .card { background: var(--color-bg-card); border: var(--border-width) solid var(--color-border); border-radius: var(--radius-md); overflow: hidden; }
        .badge { padding: var(--space-1) var(--space-3); font-size: var(--text-xs); font-weight: var(--font-weight-bold); text-transform: uppercase; border-radius: var(--radius-full); display: inline-block; }
        .badge--success { background: #e8f5e9; color: var(--color-success); } .badge--danger { background: #ffebee; color: var(--color-danger); } .badge--warning { background: #fff3e0; color: var(--color-warning); } .badge--neutral { background: var(--color-border); color: var(--color-text-muted); }
        .table-wrapper { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; background: var(--color-bg-card); }
        .table th { font-size: var(--text-xs); text-transform: uppercase; color: var(--color-text-muted); padding: var(--space-3) var(--space-5); background: var(--color-bg-alt); text-align: left; border-bottom: var(--border-width) solid var(--color-border); }
        .table td { padding: var(--space-4) var(--space-5); border-bottom: var(--border-width) solid var(--color-border); font-size: var(--text-sm); vertical-align: middle; }
        .table tr:last-child td { border-bottom: none; }
        .divider { border-top: var(--border-width) solid var(--color-border); margin: var(--space-4) 0; }
        .empty-state { text-align: center; padding: var(--space-16); } .empty-state__title { font-size: var(--text-xl); margin-bottom: var(--space-2); }
        .footer { background: var(--color-text); color: rgba(255,255,255,0.7); padding: var(--space-8) 0; margin-top: var(--space-16); }
        .spinner { width: 24px; height: 24px; border: 3px solid var(--color-border); border-top-color: var(--color-primary); border-radius: 50%; animation: spin 0.7s linear infinite; } @keyframes spin { to { transform: rotate(360deg); } } .loading-overlay { display: flex; justify-content: center; padding: var(--space-16); }
        .w-full { width: 100%; } .mb-1 { margin-bottom: var(--space-1); } .mb-2 { margin-bottom: var(--space-2); } .mb-4 { margin-bottom: var(--space-4); } .mb-6 { margin-bottom: var(--space-6); } .mb-8 { margin-bottom: var(--space-8); } .mt-0 { margin-top: 0; } .mt-4 { margin-top: var(--space-4); } .my-4 { margin: var(--space-4) 0; } .p-0 { padding: 0; } .p-6 { padding: var(--space-6); } .uppercase { text-transform: uppercase; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar__inner">
            <a href="home.php" class="navbar__logo">Bite Me Donut</a>
            <div class="navbar__nav">
                <a href="products.php" class="navbar__link">Menu</a>
                <a href="cart.php" class="navbar__link">Cart</a>
                <a href="orders.php" class="navbar__link active">Orders</a>
            </div>
            <div class="navbar__actions">
                <a href="cart.php" class="navbar__icon-btn" title="View Cart">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                </a>
                <button id="global-btn-logout" class="btn btn--danger btn--sm" style="margin-left: 1rem;">Logout</button>
            </div>
        </div>
    </nav>


    <main class="container section">
        <div class="mb-8 font-bold">
            <a href="orders.php" class="text-muted">&larr; Back to Order History</a>
        </div>
       
        <h1 class="page-title text-left mb-6">Order <span id="display-order-id" class="text-primary"></span></h1>
       
        <div class="grid-2" id="order-detail-container">
            <div class="loading-overlay w-full" style="grid-column: 1 / -1;">
                <div class="spinner"></div>
            </div>
        </div>
    </main>


    <footer class="footer">
        <div class="container text-center text-sm">
            &copy; 2026 Bite Me Donut. All rights reserved.
        </div>
    </footer>


    <script src="../public/assets/js/config.js"></script>
    <script src="../public/assets/js/api.js"></script>
    <script src="../public/assets/js/order-detail.js"></script>
</body>
</html>





