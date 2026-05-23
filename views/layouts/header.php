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
 */

$activePage = $activePage ?? '';
?>
<!DOCTYPE html>
<!-- Chỉ dùng phần <head> + <header> này, KHÔNG đóng </body></html> ở đây -->
<!-- Trang gọi vào sẽ tự đóng ở cuối -->

<link rel="stylesheet" href="../../public/assets/css/root.css">

<style>
  /* -------------------------------------------------------
     Header — fixed navbar
     Các style này bổ sung cho root.css, không ghi đè token
     ------------------------------------------------------- */

  .site-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: var(--z-sticky);
    background-color: var(--color-bg);
    border-bottom: 1px solid var(--color-border);
    height: var(--navbar-height);
  }

  .site-header__inner {
    display: flex;
    align-items: stretch;      /* mỗi ô kéo dài full height */
    height: 100%;
    max-width: 100%;
    border-left: 1px solid var(--color-border);  /* border trái cùng */
  }

  /* ------- Logo ô đầu tiên ------- */
  .site-header__logo {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    padding: 0 var(--space-5);
    border-right: 1px solid var(--color-border);
    text-decoration: none;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background-color var(--transition-fast);
  }

  .site-header__logo:hover {
    background-color: var(--color-primary-light);
  }

  .site-header__logo img {
    width: 36px;
    height: 36px;
    object-fit: contain;
    flex-shrink: 0;
  }

  .site-header__brand {
    display: flex;
    flex-direction: column;
    line-height: 1.15;
  }

  .site-header__brand-name {
    font-family: var(--font-heading);
    font-size: var(--text-md);
    color: var(--color-text);
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }

  /* ------- Nav links ------- */
  .site-header__nav {
    display: flex;
    align-items: stretch;
    flex: 1;
  }

  .site-header__link {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 1;
    padding: 0;
    font-family: var(--font-body);
    font-size: var(--text-sm);
    font-weight: var(--font-weight-bold);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--color-text-muted);
    text-decoration: none;
    border-right: 1px solid var(--color-border);
    transition: background-color var(--transition-fast), color var(--transition-fast);
    white-space: nowrap;
  }

  .site-header__link:hover {
    background-color: var(--color-primary-light);
    color: var(--color-primary);
  }

  .site-header__link.is-active {
    background-color: var(--color-primary-light);
    color: var(--color-primary);
  }

  /* ------- Spacer đẩy actions về phải ------- */
  /* .site-header__spacer {
    flex: 1;
    border-right: 1px solid var(--color-border);
  } */

  /* ------- Icon actions (user + cart) ------- */
  /* .site-header__actions {
    display: flex;
    align-items: stretch;
  } */

.site-header__actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-5);         /* ← 2 icon cách nhau */
    padding: 0 var(--space-5);
    border-left: 1px solid var(--color-border);   /* ← chỉ cần border trái */
    flex-shrink: 0;
}

  .site-header__action-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: auto;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--color-primary);
    text-decoration: none;
    transition: background-color var(--transition-fast);
    position: relative;
  }

  

  .site-header__action-btn:hover {
    background-color: transparent;  /* ← bỏ hover background */
    color: var(--color-primary-dark);
  }

  .site-header__action-btn svg {
    width: 22px;
    height: 22px;
    flex-shrink: 0;
  }

  /* Badge số lượng giỏ hàng */
  .site-header__cart-badge {
    position: absolute;
    top: 12px;
    right: 10px;
    min-width: 18px;
    height: 18px;
    background-color: var(--color-primary);
    color: white;
    font-size: 10px;
    font-weight: var(--font-weight-black);
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    line-height: 1;
  }

  /* ------- Offset cho nội dung trang không bị che bởi fixed header ------- */
  .header-offset {
    height: var(--navbar-height);
  }

  /* ------- Responsive: thu gọn padding khi màn nhỏ ------- */
  @media (max-width: 900px) {
    .site-header__link {
      padding: 0 var(--space-4);
      font-size: var(--text-xs);
    }
    .site-header__logo {
      padding: 0 var(--space-4);
    }
  }

  @media (max-width: 680px) {
    /* Mobile: ẩn label text của nav, chỉ giữ icon actions */
    .site-header__link span { display: none; }
    .site-header__link { padding: 0 var(--space-3); }
  }
</style>

<header class="site-header" role="banner">
  <div class="site-header__inner">

    <!-- Logo + Tên thương hiệu -->
    <a href="/index.html" class="site-header__logo" aria-label="Bite Me Donut — Homepage">
      <img
        src="../../public/assets/img/logo.png"
        alt="Bite Me Donut logo"
        width="36"
        height="36"
      >
      <div class="site-header__brand">
        <span class="site-header__brand-name">Bite Me<br>Donut</span>
      </div>
    </a>

    <!-- Nav links -->
    <nav class="site-header__nav" role="navigation" aria-label="Menu chính">

      <a href="/index.html"
         class="site-header__link <?= $activePage === 'home' ? 'is-active' : '' ?>">
        Home
      </a>

      <a href="/products.html"
         class="site-header__link <?= $activePage === 'products' ? 'is-active' : '' ?>">
        Products
      </a>

      <a href="/contact.html"
         class="site-header__link <?= $activePage === 'contact' ? 'is-active' : '' ?>">
        Contact
      </a>

      <a href="/policies.html"
         class="site-header__link <?= $activePage === 'policies' ? 'is-active' : '' ?>">
        Policies
      </a>

    </nav>

    <!-- Actions: User + Cart -->
    <div class="site-header__actions">

      <!-- User -->
      <a href="/login.html"
         class="site-header__action-btn"
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
      <a href="/cart.html"
         class="site-header__action-btn"
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
        <?php
          /* Hiển thị badge nếu giỏ hàng có sản phẩm.
             Truyền $cartCount từ trang trước khi include header.
             Ví dụ: $cartCount = count($_SESSION['cart'] ?? []);  */
          if (!empty($cartCount) && $cartCount > 0): ?>
          <span class="site-header__cart-badge"><?= min($cartCount, 99) ?></span>
        <?php endif; ?>
      </a>

    </div><!-- /.site-header__actions -->

  </div><!-- /.site-header__inner -->
</header>

<!-- Đẩy nội dung trang xuống đúng chiều cao header (vì header là fixed) -->
<div class="header-offset" aria-hidden="true"></div>
