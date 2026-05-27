<?php
// VIEW/products-detail.php
?>
<!DOCTYPE html> 
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Detail - Bite Me Donut</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800&display=swap');
        :root { --color-bg: #fef9e7; --color-bg-card: #ffffff; --color-primary: #e91e8c; --color-primary-dark: #c01575; --color-text: #2b1a0e; --color-text-muted: #7a6352; --color-border: #e8dbc8; --color-danger: #e53935; --color-success: #43a047; --font-heading: 'Fredoka One', cursive; --font-body: 'Nunito', sans-serif; --text-xs: 0.75rem; --text-sm: 0.875rem; --text-base: 1rem; --text-md: 1.125rem; --text-xl: 1.5rem; --text-3xl: 2.5rem; --font-weight-bold: 700; --font-weight-black: 800; --space-2: 0.5rem; --space-3: 0.75rem; --space-4: 1rem; --space-6: 1.5rem; --space-8: 2rem; --space-16: 4rem; --radius-sm: 4px; --radius-md: 8px; --radius-full: 9999px; --border-width: 1px; --shadow-lg: 0 8px 32px rgba(43,26,14,0.15); --transition-fast: 150ms ease; --container-max: 1200px; --navbar-height: 68px; }
        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-size: 16px; scroll-behavior: smooth; }
        body { font-family: var(--font-body); font-size: var(--text-base); color: var(--color-text); background-color: var(--color-bg); line-height: 1.6; min-height: 100vh; }
        a { color: var(--color-primary); text-decoration: none; transition: color var(--transition-fast); } a:hover { color: var(--color-primary-dark); }
        img { max-width: 100%; display: block; }
        h1, .page-title { font-family: var(--font-heading); font-weight: 400; line-height: 1.2; }
        .page-title { font-size: var(--text-3xl); margin-bottom: var(--space-4); }
        .text-left { text-align: left; } .text-center { text-align: center; } .text-danger { color: var(--color-danger); } .text-primary { color: var(--color-primary); } .text-muted { color: var(--color-text-muted); }
        .font-bold { font-weight: var(--font-weight-bold); } .font-black { font-weight: var(--font-weight-black); }
        .text-sm { font-size: var(--text-sm); } .text-md { font-size: var(--text-md); } .text-xl { font-size: var(--text-xl); }
        .container { max-width: var(--container-max); margin: 0 auto; padding: 0 var(--space-6); }
        .section { padding: var(--space-16) 0; }
        .flex { display: flex; } .flex-col { display: flex; flex-direction: column; } .items-center { align-items: center; } .justify-center { justify-content: center; } .gap-6 { gap: var(--space-6); }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-8); }
        .navbar { position: sticky; top: 0; z-index: 200; background: var(--color-bg); border-bottom: var(--border-width) solid var(--color-border); height: var(--navbar-height); display: flex; align-items: center; }
        .navbar__inner { display: flex; align-items: center; justify-content: space-between; width: 100%; max-width: var(--container-max); margin: 0 auto; padding: 0 var(--space-6); }
        .navbar__logo { font-family: var(--font-heading); font-size: var(--text-xl); color: var(--color-text); }
        .navbar__nav { display: flex; gap: var(--space-1); }
        .navbar__link { font-size: var(--text-sm); font-weight: var(--font-weight-bold); padding: var(--space-2) var(--space-4); text-transform: uppercase; color: var(--color-text); } .navbar__link.active { color: var(--color-primary); }
        .navbar__actions { display: flex; align-items: center; gap: var(--space-4); }
        .navbar__icon-btn { color: var(--color-text); display: flex; align-items: center; cursor: pointer; } .navbar__icon-btn:hover { color: var(--color-primary); }
        .btn { display: inline-flex; align-items: center; justify-content: center; font-weight: var(--font-weight-bold); text-transform: uppercase; padding: var(--space-3) var(--space-6); border-radius: var(--radius-sm); cursor: pointer; border: none; font-size: var(--text-sm); transition: background-color var(--transition-fast); }
        .btn--primary { background: var(--color-primary); color: white; } .btn--primary:hover:not(:disabled) { background: var(--color-primary-dark); } .btn--primary:disabled { background: var(--color-border); cursor: not-allowed; }
        .btn--danger { background: var(--color-danger); color: white; font-size: var(--text-xs); padding: var(--space-2) var(--space-4); }
        .card { background: var(--color-bg-card); border: var(--border-width) solid var(--color-border); border-radius: var(--radius-md); overflow: hidden; }
        .badge { padding: var(--space-1) var(--space-3); font-size: var(--text-xs); font-weight: var(--font-weight-bold); text-transform: uppercase; border-radius: var(--radius-full); display: inline-block; }
        .badge--success { background: #e8f5e9; color: var(--color-success); } .badge--danger { background: #ffebee; color: var(--color-danger); }
        .qty-selector { display: inline-flex; align-items: center; border: var(--border-width) solid var(--color-border); border-radius: var(--radius-sm); }
        .qty-selector__btn { width: 40px; height: 40px; background: transparent; border: none; font-weight: bold; font-size: var(--text-md); cursor: pointer; } .qty-selector__btn:hover:not(:disabled) { color: var(--color-primary); }
        .qty-selector__value { padding: 0 var(--space-4); font-weight: bold; font-size: var(--text-base); border-left: var(--border-width) solid var(--color-border); border-right: var(--border-width) solid var(--color-border); line-height: 40px; }
        .alert { padding: var(--space-4); border-radius: var(--radius-md); font-weight: var(--font-weight-bold); margin-bottom: var(--space-4); border-left: 4px solid transparent; } .alert--success { background: #e8f5e9; border-left-color: var(--color-success); color: #1b5e20; } .alert--danger { background: #ffebee; border-left-color: var(--color-danger); color: #b71c1c; }
        .divider { border-top: var(--border-width) solid var(--color-border); margin: var(--space-4) 0; }
        .empty-state { text-align: center; padding: var(--space-16); } .empty-state__title { font-size: var(--text-xl); margin-bottom: var(--space-2); }
        .footer { background: var(--color-text); color: rgba(255,255,255,0.7); padding: var(--space-8) 0; margin-top: var(--space-16); }
        .spinner { width: 24px; height: 24px; border: 3px solid var(--color-border); border-top-color: var(--color-primary); border-radius: 50%; animation: spin 0.7s linear infinite; } @keyframes spin { to { transform: rotate(360deg); } } .loading-overlay { display: flex; justify-content: center; padding: var(--space-16); }
        .w-full { width: 100%; } .mb-2 { margin-bottom: var(--space-2); } .mb-4 { margin-bottom: var(--space-4); } .mb-8 { margin-bottom: var(--space-8); } .mt-4 { margin-top: var(--space-4); } .p-6 { padding: var(--space-6); } .p-4 { padding: var(--space-4); }
    </style> 
</head>
<body> 
    <?php include __DIR__ . '/../layouts/header.php'; ?>


    <main class="container section">
        <div class="mb-8 font-bold">
            <a href="products.php" class="text-muted">&larr; Back to Menu</a>
        </div>


        <div id="alert-container"></div>


        <div class="grid-2" id="product-detail-container">
            <div class="loading-overlay w-full" style="grid-column: 1 / -1;">
                <div class="spinner"></div>
            </div>
        </div>
    </main>


    <?php include __DIR__ . '/../layouts/footer.php'; ?>



    <script src="../public/assets/js/config.js"></script>
    <script src="../public/assets/js/api.js"></script>
    <script src="../public/assets/js/products-detail.js"></script>
</body>
</html>





