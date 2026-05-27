 <?php
// views/user/home.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bite Me Donut - Sweet & Fresh Every Day</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800&display=swap');
        :root { --color-bg: #fef9e7; --color-bg-alt: #fdf3c8; --color-bg-card: #ffffff; --color-primary: #e91e8c; --color-primary-dark: #c01575; --color-primary-light: #fce4f3; --color-text: #2b1a0e; --color-text-muted: #7a6352; --color-border: #e8dbc8; --color-danger: #e53935; --color-success: #43a047; --color-warning: #fb8c00; --font-heading: 'Fredoka One', cursive; --font-body: 'Nunito', sans-serif; --text-xs: 0.75rem; --text-sm: 0.875rem; --text-base: 1rem; --text-md: 1.125rem; --text-lg: 1.25rem; --text-xl: 1.5rem; --text-2xl: 2rem; --text-3xl: 2.5rem; --font-weight-bold: 700; --font-weight-black: 800; --space-1: 0.25rem; --space-2: 0.5rem; --space-3: 0.75rem; --space-4: 1rem; --space-6: 1.5rem; --space-8: 2rem; --space-16: 4rem; --radius-sm: 4px; --radius-md: 8px; --radius-full: 9999px; --border-width: 1px; --shadow-md: 0 4px 16px rgba(43,26,14,0.12); --shadow-lg: 0 8px 32px rgba(43,26,14,0.15); --transition-fast: 150ms ease; --container-max: 1200px; --container-md: 900px; --navbar-height: 68px; }
        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-size: 16px; scroll-behavior: smooth; }
        body { font-family: var(--font-body); font-size: var(--text-base); color: var(--color-text); background-color: var(--color-bg); line-height: 1.6; min-height: 100vh; }
        a { color: var(--color-primary); text-decoration: none; transition: color var(--transition-fast); } a:hover { color: var(--color-primary-dark); }
        img { max-width: 100%; display: block; }
        h1, h2, h3, .page-title { font-family: var(--font-heading); font-weight: 400; line-height: 1.2; }
        .page-title { font-size: var(--text-3xl); text-align: center; margin-bottom: var(--space-8); }
        .text-center { text-align: center; } .text-right { text-align: right; } .text-danger { color: var(--color-danger); } .text-primary { color: var(--color-primary); } .text-muted { color: var(--color-text-muted); }
        .font-bold { font-weight: var(--font-weight-bold); } .font-black { font-weight: var(--font-weight-black); }
        .text-xs { font-size: var(--text-xs); } .text-sm { font-size: var(--text-sm); } .text-lg { font-size: var(--text-lg); } .text-xl { font-size: var(--text-xl); }
        .container { max-width: var(--container-max); margin: 0 auto; padding: 0 var(--space-6); } .container--md { max-width: var(--container-md); }
        .section { padding: var(--space-16) 0; }
        .flex { display: flex; } .flex-col { display: flex; flex-direction: column; } .items-center { align-items: center; } .justify-between { justify-content: space-between; } .justify-center { justify-content: center; } .gap-2 { gap: var(--space-2); } .gap-4 { gap: var(--space-4); }
        .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-6); }
        .btn { display: inline-flex; align-items: center; justify-content: center; font-weight: var(--font-weight-bold); text-transform: uppercase; padding: var(--space-3) var(--space-6); border-radius: var(--radius-sm); cursor: pointer; border: 2px solid transparent; transition: all var(--transition-fast); }
        .btn--primary { background: var(--color-primary); color: white; border-color: var(--color-primary); } .btn--primary:hover { background: var(--color-primary-dark); box-shadow: var(--shadow-md); }
        .btn--outline { background: transparent; color: var(--color-primary); border-color: var(--color-primary); }
        .btn--danger { background: var(--color-danger); color: white; border-color: var(--color-danger); font-size: var(--text-xs); padding: var(--space-2) var(--space-4); }
        .btn--sm { font-size: var(--text-xs); padding: var(--space-2) var(--space-4); } .btn--lg { padding: var(--space-4) var(--space-8); }
        .card { background: var(--color-bg-card); border: var(--border-width) solid var(--color-border); border-radius: var(--radius-md); padding: var(--space-6); transition: transform var(--transition-fast), box-shadow var(--transition-fast); } .card:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        .badge { padding: var(--space-1) var(--space-3); font-size: var(--text-xs); font-weight: var(--font-weight-bold); text-transform: uppercase; border-radius: var(--radius-full); display: inline-block; } .badge--neutral { background: var(--color-border); color: var(--color-text-muted); }
        .divider { border-top: var(--border-width) solid var(--color-border); margin: var(--space-4) 0; }
        .w-full { width: 100%; } .mb-4 { margin-bottom: var(--space-4); } .mb-8 { margin-bottom: var(--space-8); } .mt-8 { margin-top: var(--space-8); }
    </style>
</head>
<body>


<?php include __DIR__ . '/../layouts/header.php'; ?>


<main>
    <section class="section text-center" style="background-color: var(--color-primary-light); border-bottom: var(--border-width) solid var(--color-border);">
        <div class="container container--md">
            <h1 class="page-title text-primary mb-4" id="welcome-name">Loading your sweet experience...</h1>
            <p class="text-lg text-muted mb-8" id="welcome-message">Please wait while we fetch your profile.</p>
            <div class="flex justify-center gap-4">
                <a href="products.php" class="btn btn--primary btn--lg">Explore Menu</a>
                <a href="orders.php" class="btn btn--outline btn--lg">Order History</a>
            </div>
        </div>
    </section>


    <section class="section container">
        <div class="grid-2">
            <div class="card">
                <h2 class="h4 font-bold mb-4">Account Details</h2>
                <div class="divider"></div>
                <div class="flex flex-col gap-2">
                    <p class="text-sm text-muted uppercase font-bold">Logged in as</p>
                    <p class="text-lg font-black text-primary" id="user-profile-email">Loading...</p>
                    <span class="badge badge--neutral w-full" style="max-width: max-content; margin-top: 0.5rem;" id="user-profile-role">...</span>
                </div>
            </div>


            <div class="card flex flex-col justify-between">
                <div>
                    <h2 class="h4 font-bold mb-4">Need a treat?</h2>
                    <div class="divider"></div>
                    <p class="text-base text-muted">Don't let your cravings wait. Check out our freshly baked donuts, custom boxes, and refreshing drinks.</p>
                </div>
                <div class="mt-8 text-right">
                    <a href="products.php" class="btn btn--primary">Order Now &rarr;</a>
                </div>
            </div>
        </div>
    </section>
</main>


<?php include __DIR__ . '/../layouts/footer.php'; ?>


<script src="../../public/assets/js/config.js"></script>
<script src="../../public/assets/js/api.js"></script>
<script src="../../public/assets/js/home.js"></script>
</body>
</html>





