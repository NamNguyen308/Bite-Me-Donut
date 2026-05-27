<?php
/**
 * views/layouts/footer.php
 *
 * Usage — include at the bottom of any page:
 *   <?php include __DIR__ . '/../layouts/footer.php'; ?>
 *   or from public/:
 *   <?php include __DIR__ . '/../../views/layouts/footer.php'; ?>
 */

$activePage = $activePage ?? '';
?>

<link rel="stylesheet" href="../../public/assets/css/root.css">

<style>
  /* -------------------------------------------------------
     Footer — 4 equal columns + copyright bar
     Background: --color-secondary (soft pink from root)
     ------------------------------------------------------- */

  .site-footer {
    background-color: var(--color-primary-light);   /* soft pink #f7a8d4 */
    border-top: 2px solid var(--color-text-muted);
    margin-top: auto;
  }

  /* 4 equal columns, full width, separated by borders */
  .site-footer__grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    border-left: 1px solid var(--color-text-muted);
    border-right: 1px solid var(--color-text-muted);
  }

  .site-footer__col {
    padding: var(--space-10) var(--space-8);
    border-right: 1px solid var(--color-text-muted);
  }

  .site-footer__col:last-child {
    border-right: none;
  }

  /* ------- Col 1: Logo + brand + slogan ------- */
  .site-footer__brand {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-5);
    text-decoration: none;
  }

  .site-footer__brand img {
    width: 44px;
    height: 44px;
    object-fit: contain;
    flex-shrink: 0;
  }

  .site-footer__brand-name {
    font-family: var(--font-heading);
    font-size: var(--text-lg);
    color: var(--color-text);
    text-transform: uppercase;
    line-height: 1.2;
    letter-spacing: 0.02em;
  }

  .site-footer__slogan {
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    line-height: var(--line-height-relaxed);
    max-width: 220px;
  }

  /* ------- Col 2–4: Section title + links ------- */
  .site-footer__col-title {
    font-family: var(--font-heading);
    font-size: var(--text-lg);
    color: var(--color-text);
    margin-bottom: var(--space-5);
    letter-spacing: 0.02em;
  }

  .site-footer__link {
    display: block;
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    text-underline-offset: 3px;
    margin-bottom: var(--space-3);
    transition: color var(--transition-fast);
    font-weight: var(--font-weight-medium);
    letter-spacing: 0.06em;
  }

  .site-footer__link:hover {
    color: var(--color-primary);
  }

  /* Contact info rows */
  .site-footer__contact-item {
    display: flex;
    align-items: flex-start;
    gap: var(--space-3);
    margin-bottom: var(--space-4);
    font-size: var(--text-sm);
    color: var(--color-text-muted);
    font-weight: var(--font-weight-medium);
    letter-spacing: 0.06em;
    line-height: var(--line-height-normal);
  }

  .site-footer__contact-item a {
    color: var(--color-text-muted);
    text-underline-offset: 3px;
    transition: color var(--transition-fast);
  }

  .site-footer__contact-item a:hover {
    color: var(--color-primary);
  }

  .site-footer__contact-icon {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    margin-top: 2px;
    color: var(--color-primary);
  }

  /* ------- Copyright bar ------- */
  .site-footer__copyright {
    background-color: var(--color-primary);
    border-top: 1px solid var(--color-text-muted);
    padding: var(--space-4) var(--space-6);
    text-align: center;
  }

  .site-footer__copyright p {
    font-size: var(--text-sm);
    font-weight: var(--font-weight-medium);
    color: var(--color-text-light);
    letter-spacing: 0.04em;
  }

  /* ------- Responsive ------- */
  @media (max-width: 900px) {
    .site-footer__col {
      padding: var(--space-8) var(--space-5);
    }
  }

  @media (max-width: 680px) {
    .site-footer__grid {
      grid-template-columns: repeat(2, 1fr);
    }
    .site-footer__col:nth-child(2) {
      border-right: none;
    }
    .site-footer__col:nth-child(3) {
      border-top: 1px solid var(--color-text-muted);
    }
    .site-footer__col:nth-child(4) {
      border-top: 1px solid var(--color-text-muted);
      border-right: none;
    }
  }

  @media (max-width: 420px) {
    .site-footer__grid {
      grid-template-columns: 1fr;
    }
    .site-footer__col {
      border-right: none !important;
      border-top: 1px solid var(--color-text-muted);
    }
    .site-footer__col:first-child {
      border-top: none;
    }
  }
</style>

<footer class="site-footer" role="contentinfo">

  <!-- 4-column grid -->
  <div class="site-footer__grid">

    <!-- Col 1: Logo + Brand + Slogan -->
    <div class="site-footer__col">
      <a href="../../views/user/index.php" class="site-footer__brand" aria-label="Bite Me Donut — Home">
        <img
          src="../../public/assets/img/logo.png"
          alt="Bite Me Donut logo"
          width="44"
          height="44"
        >
        <span class="site-footer__brand-name">Bite Me<br>Donut</span>
      </a>
      <p class="site-footer__slogan">
        Handcrafted donuts made with love,
        every day from 7am – 10pm.
      </p>
    </div>

    <!-- Col 2: Pages -->
    <div class="site-footer__col">
      <p class="site-footer__col-title">Pages</p>
      <a href="../../views/user/index.php"    class="site-footer__link">Home</a>
      <a href="../../views/user/products.php" class="site-footer__link">Products</a>
      <a href="../../views/user/policies.php" class="site-footer__link">Policies</a>
      <a href="../../views/user/contact.php" class="site-footer__link">Contact</a>
    </div>

    <!-- Col 3: Account -->
    <div class="site-footer__col">
      <p class="site-footer__col-title">Follow us</p>
      <a href="https://facebook.com"  class="site-footer__link">Facebook</a>
      <a href="https://instagram.com" class="site-footer__link">Instagram</a>
      <a href="https://tiktok.com"   class="site-footer__link">TikTok</a>
      <a href="https://youtube.com"   class="site-footer__link">Youtube</a>
    </div>

    <!-- Col 4: Contact -->
    <div class="site-footer__col">
      <p class="site-footer__col-title">Contact</p>

      <div class="site-footer__contact-item">
        <!-- Pin / location icon -->
        <svg class="site-footer__contact-icon"
             xmlns="http://www.w3.org/2000/svg"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="1.8"
             stroke-linecap="round"
             stroke-linejoin="round"
             aria-hidden="true">
          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
          <circle cx="12" cy="9" r="2.5"/>
        </svg>
        <span>279 Nguyen Tri Phuong,<br>Ho Chi Minh City</span>
      </div>

      <div class="site-footer__contact-item">
        <!-- Phone icon -->
        <svg class="site-footer__contact-icon"
             xmlns="http://www.w3.org/2000/svg"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="1.8"
             stroke-linecap="round"
             stroke-linejoin="round"
             aria-hidden="true">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.86 19.86 0 0 1 3.09 4.18 2 2 0 0 1 5.07 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L9.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
        <a href="tel:+840901234567">0901 234 567</a>
      </div>

      <div class="site-footer__contact-item">
        <!-- Mail icon -->
        <svg class="site-footer__contact-icon"
             xmlns="http://www.w3.org/2000/svg"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="1.8"
             stroke-linecap="round"
             stroke-linejoin="round"
             aria-hidden="true">
          <rect x="2" y="4" width="20" height="16" rx="2"/>
          <path d="M2 7l10 7 10-7"/>
        </svg>
        <a href="mailto:hello@bitemedonuts.com">hello@bitemedonuts.com</a>
      </div>

    </div><!-- /Col 4 -->

  </div><!-- /.site-footer__grid -->

  <!-- Copyright bar -->
  <div class="site-footer__copyright">
    <p>© 2026 Bite-Me-Donut. All rights reserved.</p>
  </div>

</footer>