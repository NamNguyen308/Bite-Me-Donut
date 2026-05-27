<?php
?>
<!DOCTYPE html>
<html lang="en"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Menu - Bite Me Donut</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800&display=swap');
        :root { --color-bg: #fef9e7; --color-bg-alt: #fdf3c8; --color-bg-card: #ffffff; --color-primary: #e91e8c; --color-primary-dark: #c01575; --color-text: #2b1a0e; --color-text-muted: #7a6352; --color-border: #e8dbc8; --color-danger: #e53935; --color-success: #43a047; --font-heading: 'Fredoka One', cursive; --font-body: 'Nunito', sans-serif; --text-xs: 0.75rem; --text-sm: 0.875rem; --text-base: 1rem; --text-md: 1.125rem; --text-xl: 1.5rem; --text-3xl: 2.5rem; --font-weight-bold: 700; --font-weight-black: 800; --space-2: 0.5rem; --space-3: 0.75rem; --space-4: 1rem; --space-6: 1.5rem; --space-8: 2rem; --space-16: 4rem; --radius-sm: 4px; --radius-md: 8px; --border-width: 1px; --shadow-lg: 0 8px 32px rgba(43,26,14,0.15); --transition-fast: 150ms ease; --container-max: 1200px; --navbar-height: 68px; }
        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-size: 16px; scroll-behavior: smooth; }
        body { font-family: var(--font-body); font-size: var(--text-base); color: var(--color-text); background-color: var(--color-bg); line-height: 1.6; min-height: 100vh; }
        a { color: var(--color-primary); text-decoration: none; transition: color var(--transition-fast); } a:hover { color: var(--color-primary-dark); }
        img { max-width: 100%; display: block; }
        h1, h3, .page-title { font-family: var(--font-heading); font-weight: 400; line-height: 1.2; }
        .page-title { font-size: var(--text-3xl); text-align: center; margin-bottom: var(--space-8); }
        .text-center { text-align: center; } .text-danger { color: var(--color-danger); } .w-full { width: 100%; }
        .container { max-width: var(--container-max); margin: 0 auto; padding: 0 var(--space-6); } .section { padding: var(--space-16) 0; }
        .grid-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: var(--space-6); }
        .navbar { position: sticky; top: 0; z-index: 200; background: var(--color-bg); border-bottom: var(--border-width) solid var(--color-border); height: var(--navbar-height); display: flex; align-items: center; }
        .navbar__inner { display: flex; align-items: center; justify-content: space-between; width: 100%; max-width: var(--container-max); margin: 0 auto; padding: 0 var(--space-6); }
        .navbar__logo { font-family: var(--font-heading); font-size: var(--text-xl); color: var(--color-text); }
        .navbar__nav { display: flex; gap: var(--space-1); }
        .navbar__link { font-size: var(--text-sm); font-weight: var(--font-weight-bold); padding: var(--space-2) var(--space-4); text-transform: uppercase; color: var(--color-text); } .navbar__link.active { color: var(--color-primary); }
        .navbar__actions { display: flex; align-items: center; gap: var(--space-4); }
        .navbar__icon-btn { color: var(--color-text); display: flex; align-items: center; cursor: pointer; } .navbar__icon-btn:hover { color: var(--color-primary); }
        .btn { display: inline-flex; align-items: center; justify-content: center; font-weight: var(--font-weight-bold); text-transform: uppercase; padding: var(--space-2) var(--space-4); border-radius: var(--radius-sm); cursor: pointer; border: none; font-size: var(--text-xs); }
        .btn--danger { background: var(--color-danger); color: white; }
        .product-card { display: flex; flex-direction: column; background: var(--color-bg-card); border: var(--border-width) solid var(--color-border); border-radius: var(--radius-md); overflow: hidden; transition: transform var(--transition-fast), box-shadow var(--transition-fast); } .product-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        .product-card__image { width: 100%; aspect-ratio: 1/1; object-fit: cover; } .product-card__body { padding: var(--space-4); flex: 1; }
        .product-card__name { font-size: var(--text-sm); font-weight: var(--font-weight-black); text-transform: uppercase; margin-bottom: var(--space-2); color: var(--color-text); } .product-card__description { font-size: var(--text-sm); color: var(--color-text-muted); }
        .product-card__footer { display: flex; justify-content: space-between; align-items: center; padding: var(--space-3) var(--space-4); border-top: var(--border-width) solid var(--color-border); } .product-card__price { font-size: var(--text-md); font-weight: var(--font-weight-black); }
        .btn--add-cart { width: 36px; height: 36px; border-radius: 50%; background: var(--color-primary); color: white; border: none; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center; } .btn--add-cart:hover:not(:disabled) { background: var(--color-primary-dark); transform: scale(1.1); } .btn--add-cart:disabled { background: var(--color-border); cursor: not-allowed; }
        .empty-state { text-align: center; padding: var(--space-16); } .empty-state__title { font-size: var(--text-xl); margin-bottom: var(--space-2); }
        .alert { padding: var(--space-4); border-radius: var(--radius-md); font-weight: var(--font-weight-bold); margin-bottom: var(--space-4); border-left: 4px solid transparent; } .alert--success { background: #e8f5e9; border-left-color: var(--color-success); color: #1b5e20; } .alert--danger { background: #ffebee; border-left-color: var(--color-danger); color: #b71c1c; }
        .footer { background: var(--color-text); color: rgba(255,255,255,0.7); padding: var(--space-8) 0; margin-top: var(--space-16); }
        .spinner { width: 24px; height: 24px; border: 3px solid var(--color-border); border-top-color: var(--color-primary); border-radius: 50%; animation: spin 0.7s linear infinite; } @keyframes spin { to { transform: rotate(360deg); } } .loading-overlay { display: flex; justify-content: center; padding: var(--space-16); }
    </style>
</head>
<body>


<?php include __DIR__ . '/../layouts/header.php'; ?>


<main class="container section">
    <h1 class="page-title">Our Sweet Menu</h1>
   
    <div id="alert-container"></div>


    <div id="products-grid" class="grid-cards">
        <div class="loading-overlay w-full" style="grid-column: 1 / -1;">
            <div class="spinner"></div>
        </div>
    </div>
</main>


<?php include __DIR__ . '/../layouts/footer.php'; ?>


<script src="../../public/assets/js/config.js"></script>
<script src="../../public/assets/js/api.js"></script>
<script src="../../public/assets/js/products.js"></script>
</body>
</html>



