<?php
/**
 * views/layouts/header.php
 *
 * Cách dùng — include vào bất kỳ trang nào:
 *   <?php include __DIR__ . '/../layouts/header.php'; ?>
 *   hoặc nếu gọi từ public/:
 *   <?php include __DIR__ . '/../../views/layouts/header.php'; ?>
 *
 * Để highlight trang hiện tại, định nghĩa $activePage trước khi include:
 *   <?php $activePage = 'products'; include ...; ?>
 *   Các giá trị hợp lệ: 'home' | 'products' | 'contact' | 'policies'
 *
 * Hiển thị badge giỏ hàng (tuỳ chọn):
 *   <?php $cartCount = 3; include ...; ?>
 */

$activePage = $activePage ?? '';
?>
<link rel="stylesheet" href="../../public/assets/css/root.css">

<style>
  /* -------------------------------------------------------
     Header — fixed navbar, 6 ô bằng nhau trải full width
     ------------------------------------------------------- */

  .site-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: var(--z-sticky);
    background-color: var(--color-bg);
    border-bottom: 2px solid var(--color-border);
    height: var(--navbar-height);
  }

  /* Grid 6 cột bằng nhau, full width */
  .site-header__inner {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    align-items: stretch;
    height: 100%;
    width: 100%;
    border-left: 1px solid var(--color-border);
  }

  /* Style chung cho MỌI ô trong header */
  .site-header__cell {
    display: flex;
    align-items: center;
    justify-content: center;
    border-right: 1px solid var(--color-border);
    transition: background-color var(--transition-fast);
    overflow: hidden;
  }

  /* ------- Ô 1: Logo + Tên ------- */
  .site-header__logo {
    gap: var(--space-3);
    text-decoration: none;
    padding: 0 var(--space-4);
  }

  .site-header__logo:hover {
    background-color: var(--color-primary-light);
  }

  .site-header__logo img {
    width: 40px;
    height: 40px;
    object-fit: contain;
    flex-shrink: 0;
  }

  .site-header__brand-name {
    font-family: var(--font-heading);
    font-size: 1rem;
    color: var(--color-text);
    text-transform: uppercase;
    letter-spacing: 0.03em;
    line-height: 1.2;
    white-space: nowrap;
  }

  /* ------- Ô 2–5: Nav links ------- */
  .site-header__link {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-body);
    font-size: var(--text-sm);
    font-weight: var(--font-weight-bold);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--color-text-muted);
    text-decoration: none;
    transition: background-color var(--transition-fast), color var(--transition-fast);
  }

  .site-header__link:hover {
    background-color: var(--color-primary-light);
    color: var(--color-primary);
  }

  .site-header__link.is-active {
    background-color: var(--color-primary-light);
    color: var(--color-primary);
  }

  /* ------- Ô 6: User + Cart icons trong 1 ô ------- */
  .site-header__actions {
    gap: var(--space-6);
    padding: 0 var(--space-4);
  }

  .site-header__icon-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--color-primary);
    text-decoration: none;
    padding: var(--space-2);
    border-radius: var(--radius-md);
    transition: background-color var(--transition-fast), color var(--transition-fast);
    position: relative;
  }

  .site-header__icon-btn:hover {
    background-color: var(--color-primary-light);
    color: var(--color-primary-dark);
  }

  .site-header__icon-btn svg {
    width: 24px;
    height: 24px;
    display: block;
  }

  /* Badge số giỏ hàng */
  .site-header__cart-badge {
    position: absolute;
    top: -2px;
    right: -4px;
    min-width: 17px;
    height: 17px;
    background-color: var(--color-primary);
    color: white;
    font-size: 10px;
    font-weight: var(--font-weight-black);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 3px;
    line-height: 1;
    pointer-events: none;
  }

  /* ------- Offset nội dung trang (vì header fixed) ------- */
  .header-offset {
    height: var(--navbar-height);
  }

  /* ------- Responsive ------- */
  @media (max-width: 768px) {
    .site-header__link {
      font-size: var(--text-xs);
      letter-spacing: 0.04em;
    }
    .site-header__brand-name {
      font-size: var(--text-sm);
    }
    .site-header__logo img {
      width: 32px;
      height: 32px;
    }
    .site-header__actions {
      gap: var(--space-3);
    }
  }

  @media (max-width: 520px) {
    .site-header__inner {
      grid-template-columns: 2fr repeat(4, 1fr) 1.2fr;
    }
    .site-header__link {
      font-size: 9px;
      letter-spacing: 0;
    }
  }
</style>

<header class="site-header" role="banner">
  <div class="site-header__inner">

    <!-- Ô 1: Logo + Tên -->
    <a href="../../views/user/index.php"
       class="site-header__cell site-header__logo"
       aria-label="Bite Me Donut — Trang chủ">
      <img
        src="../../public/assets/img/logo.png"
        alt="Logo Bite Me Donut"
        width="40"
        height="40"
      >
      <span class="site-header__brand-name">Bite Me<br>Donut</span>
    </a>

    <!-- Ô 2: Home -->
    <div class="site-header__cell">
      <a href="../../views/user/index.php"
         class="site-header__link <?= $activePage === 'home' ? 'is-active' : '' ?>">
        Home
      </a>
    </div>

    <!-- Ô 3: Products -->
    <div class="site-header__cell">
      <a href="../../views/user/products.php"
         class="site-header__link <?= $activePage === 'products' ? 'is-active' : '' ?>">
        Products
      </a>
    </div>

    <!-- Ô 4: Contact -->
    <div class="site-header__cell">
      <a href="../../views/user/contact.php"
         class="site-header__link <?= $activePage === 'contact' ? 'is-active' : '' ?>">
        Contact
      </a>
    </div>

    <!-- Ô 5: Policies -->
    <div class="site-header__cell">
      <a href="../../views/user/policies.php"
         class="site-header__link <?= $activePage === 'policies' ? 'is-active' : '' ?>">
        Policies
      </a>
    </div>

    <!-- Ô 6: User + Cart (cùng 1 ô) -->
    <div class="site-header__cell site-header__actions">

      <!-- User -->
      <a href="/login.html"
         class="site-header__icon-btn"
         aria-label="Tài khoản">
        <svg xmlns="http://www.w3.org/2000/svg"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="1.8"
             stroke-linecap="round"
             stroke-linejoin="round"
             aria-hidden="true">
          <circle cx="12" cy="8" r="4"/>
          <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
        </svg>
      </a>

      <!-- Cart -->
      <a href="../../views/user/cart.php"
         class="site-header__icon-btn"
         aria-label="Giỏ hàng">
        <svg xmlns="http://www.w3.org/2000/svg"
             viewBox="0 0 24 24"
             fill="none"
             stroke="currentColor"
             stroke-width="1.8"
             stroke-linecap="round"
             stroke-linejoin="round"
             aria-hidden="true">
          <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
          <line x1="3" y1="6" x2="21" y2="6"/>
          <path d="M16 10a4 4 0 0 1-8 0"/>
        </svg>
        <?php if (!empty($cartCount) && $cartCount > 0): ?>
          <span class="site-header__cart-badge"
                aria-label="<?= (int)$cartCount ?> sản phẩm trong giỏ">
            <?= min((int)$cartCount, 99) ?>
          </span>
        <?php endif; ?>
      </a>

    </div><!-- /Ô 6 -->

  </div><!-- /.site-header__inner -->
</header>

<!-- Đẩy nội dung xuống đúng chiều cao header fixed -->
<div class="header-offset" aria-hidden="true"></div>