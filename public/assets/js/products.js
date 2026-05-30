document.addEventListener('DOMContentLoaded', async () => {
  'use strict';

  const CONFIG = window.PRODUCTS_CONFIG || {};

  const PRODUCTS_PROXY_URL = CONFIG.productsProxyUrl || `${window.location.pathname}?products_ajax=1`;
  const LOGIN_URL = CONFIG.loginUrl || '../auth/user-login.php';
  const PRODUCT_DETAIL_URL = CONFIG.productDetailUrl || './product-detail.php';
  const IMAGE_BASE_URL = CONFIG.imageBaseUrl || '../../public/assets/img/product';
  const DEFAULT_IMAGE_URL = CONFIG.defaultProductImageUrl || `${IMAGE_BASE_URL}/default-product.png`;

  const TOKEN_KEYS = ['access_token', 'auth_token'];
  const USER_KEYS = ['auth_user', 'current_user', 'user'];

  const productsGrid = document.getElementById('products-grid');
  const alertContainer = document.getElementById('alert-container');

  function getToken() {
    for (const key of TOKEN_KEYS) {
      const value = localStorage.getItem(key) || sessionStorage.getItem(key);

      if (value) {
        return value;
      }
    }

    return null;
  }

  function getCachedUser() {
    for (const key of USER_KEYS) {
      const raw = localStorage.getItem(key);

      if (!raw) continue;

      try {
        const user = JSON.parse(raw);

        if (user && typeof user === 'object') {
          return user;
        }
      } catch (error) {
        localStorage.removeItem(key);
      }
    }

    return null;
  }

  function showAlert(type, message) {
    if (!alertContainer) return;

    alertContainer.innerHTML = `<div class="alert alert--${type}">${escapeHtml(message)}</div>`;
    window.scrollTo({ top: 0, behavior: 'smooth' });

    setTimeout(() => {
      alertContainer.innerHTML = '';
    }, 3000);
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function fallbackImageDataUri() {
    const svg = `
      <svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">
        <rect width="600" height="600" fill="#fdf3c8"/>
        <circle cx="300" cy="300" r="145" fill="#e91e8c" opacity="0.18"/>
        <circle cx="300" cy="300" r="72" fill="#fef9e7"/>
        <text x="300" y="475" text-anchor="middle" font-family="Arial" font-size="34" font-weight="700" fill="#2b1a0e">Bite Me Donut</text>
      </svg>
    `;

    return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
  }

  function normalizeImageUrl(image) {
    const value = String(image || '').trim();

    if (!value) {
      return fallbackImageDataUri();
    }

    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/')) {
      return value;
    }

    return `${IMAGE_BASE_URL}/${encodeURIComponent(value)}`;
  }

  function formatPrice(value) {
    const number = Number(value || 0);

    return number.toLocaleString('vi-VN') + ' VND';
  }

  function productDetailHref(productId) {
    const joiner = PRODUCT_DETAIL_URL.includes('?') ? '&' : '?';

    return `${PRODUCT_DETAIL_URL}${joiner}id=${encodeURIComponent(productId)}`;
  }

  function extractProducts(responseData) {
    if (!responseData) return [];

    if (Array.isArray(responseData.data)) {
      return responseData.data;
    }

    if (responseData.data && Array.isArray(responseData.data.products)) {
      return responseData.data.products;
    }

    if (responseData.data && Array.isArray(responseData.data.items)) {
      return responseData.data.items;
    }

    if (Array.isArray(responseData.products)) {
      return responseData.products;
    }

    if (Array.isArray(responseData.items)) {
      return responseData.items;
    }

    return [];
  }

  async function postProducts(action, payload = {}) {
    const token = getToken();

    const requestBody = {
      action,
      ...payload
    };

    if (token) {
      requestBody.access_token = token;
    }

    console.log('[PRODUCTS POST]', PRODUCTS_PROXY_URL, requestBody);

    const response = await fetch(PRODUCTS_PROXY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(requestBody)
    });

    const rawText = await response.text();

    console.log('[PRODUCTS STATUS]', response.status);
    console.log('[PRODUCTS RAW]', rawText);

    let data;

    try {
      data = JSON.parse(rawText);
    } catch (error) {
      return {
        ok: false,
        status: response.status,
        data: {
          success: false,
          error_code: 'INVALID_JSON_RESPONSE',
          message: 'Server returned invalid JSON response',
          raw_response: rawText
        }
      };
    }

    return {
      ok: response.ok && data.success !== false,
      status: response.status,
      data
    };
  }

  function renderEmpty(title, description, danger = false) {
    if (!productsGrid) return;

    productsGrid.innerHTML = `
      <div class="empty-state">
        <h2 class="empty-state__title ${danger ? 'text-danger' : ''}">${escapeHtml(title)}</h2>
        <p class="empty-state__desc">${escapeHtml(description)}</p>
      </div>
    `;
  }

  function renderProducts(products) {
    if (!productsGrid) return;

    const activeProducts = products.filter((product) => {
      return Number(product.is_active ?? 1) === 1;
    });

    if (!activeProducts.length) {
      renderEmpty('Menu trống', 'Hiện tại chưa có bánh nào. Bạn quay lại sau nhé!');
      return;
    }

    productsGrid.innerHTML = activeProducts.map((product) => {
      const productId = product.id;
      const inStock = Number(product.stock || 0) > 0;
      const detailUrl = productDetailHref(productId);
      const imageUrl = normalizeImageUrl(product.image);

      let shortDescription =
        product.short_description ||
        product.short_desc ||
        product.description ||
        'Món bánh tuyệt ngon.';

      shortDescription = String(shortDescription);

      if (!product.short_description && shortDescription.length > 85) {
        shortDescription = shortDescription.slice(0, 85) + '...';
      }

      return `
        <article class="product-card">
          <a href="${escapeHtml(detailUrl)}" class="product-card__image-link">
            <img
              src="${escapeHtml(imageUrl)}"
              alt="${escapeHtml(product.name || 'Product')}"
              class="product-card__image"
              onerror="this.onerror=null; this.src='${fallbackImageDataUri()}'"
            >
          </a>

          <div class="product-card__body">
            <a href="${escapeHtml(detailUrl)}">
              <h3 class="product-card__name">${escapeHtml(product.name || 'Product')}</h3>
            </a>

            <p class="product-card__description">${escapeHtml(shortDescription)}</p>
          </div>

          <div class="product-card__footer">
            <div class="product-card__price">${escapeHtml(formatPrice(product.price))}</div>

            <button
              type="button"
              class="btn--add-cart add-to-cart-btn"
              data-id="${escapeHtml(productId)}"
              ${!inStock ? 'disabled' : ''}
              title="${inStock ? 'Thêm vào giỏ' : 'Hết hàng'}"
              aria-label="${inStock ? 'Thêm vào giỏ' : 'Hết hàng'}"
            >
              +
            </button>
          </div>
        </article>
      `;
    }).join('');

    bindAddToCartButtons();
  }

  function bindAddToCartButtons() {
    document.querySelectorAll('.add-to-cart-btn').forEach((button) => {
      button.addEventListener('click', async (event) => {
        event.preventDefault();

        if (!getToken()) {
          window.location.href = LOGIN_URL;
          return;
        }

        const productId = Number(button.dataset.id || 0);

        if (productId <= 0) {
          showAlert('danger', 'Product id không hợp lệ.');
          return;
        }

        button.disabled = true;

        try {
          const response = await postProducts('add_cart', {
            product_id: productId,
            quantity: 1
          });

          if (response.ok) {
            showAlert('success', 'Đã thêm món vào giỏ hàng!');
            return;
          }

          if (['UNAUTHENTICATED', 'TOKEN_INVALID', 'TOKEN_EXPIRED', 'TOKEN_REVOKED'].includes(response.data?.error_code)) {
            window.location.href = LOGIN_URL;
            return;
          }

          showAlert('danger', response.data?.message || 'Không thể thêm vào giỏ.');
        } catch (error) {
          console.error('[products:add_cart]', error);
          showAlert('danger', 'Lỗi kết nối đến server.');
        } finally {
          button.disabled = false;
        }
      });
    });
  }

  async function loadProducts() {
    if (!productsGrid) return;

    try {
      const response = await postProducts('products');

      if (!response.ok) {
        renderEmpty('Oops!', response.data?.message || 'Không thể tải menu. Vui lòng kiểm tra lại kết nối Database.', true);
        return;
      }

      const products = extractProducts(response.data);

      renderProducts(products);
    } catch (error) {
      console.error('[products:load]', error);
      renderEmpty('Lỗi Máy Chủ', 'Không thể kết nối đến cơ sở dữ liệu Laragon.', true);
    }
  }

  if (typeof window.updateGlobalHeaderAuth === 'function') {
    window.updateGlobalHeaderAuth(getCachedUser());
  }

  await loadProducts();
});