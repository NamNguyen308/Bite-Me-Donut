document.addEventListener('DOMContentLoaded', async () => {
  'use strict';

  const CONFIG = window.PRODUCT_DETAIL_CONFIG || {};

  const PRODUCT_DETAIL_PROXY_URL = CONFIG.productDetailProxyUrl || `${window.location.pathname}?product_detail_ajax=1`;
  const LOGIN_URL = CONFIG.loginUrl || '../auth/user-login.php';
  const PRODUCTS_URL = CONFIG.productsUrl || './products.php';
  const IMAGE_BASE_URL = CONFIG.imageBaseUrl || '../../public/assets/img/product';

  const TOKEN_KEYS = ['access_token', 'auth_token'];

  const detailContainer = document.getElementById('product-detail-container');
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

  function showAlert(type, message) {
    if (!alertContainer) return;

    alertContainer.innerHTML = `<div class="alert alert--${type}">${escapeHtml(message)}</div>`;

    setTimeout(() => {
      alertContainer.innerHTML = '';
    }, 4000);
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

  function getProductId() {
    const urlParams = new URLSearchParams(window.location.search);
    return Number(urlParams.get('id') || 0);
  }

  async function postProductDetail(action, payload = {}) {
    const token = getToken();

    const requestBody = {
      action,
      ...payload
    };

    if (token) {
      requestBody.access_token = token;
    }

    console.log('[PRODUCT DETAIL POST]', PRODUCT_DETAIL_PROXY_URL, requestBody);

    const response = await fetch(PRODUCT_DETAIL_PROXY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(requestBody)
    });

    const rawText = await response.text();

    console.log('[PRODUCT DETAIL STATUS]', response.status);
    console.log('[PRODUCT DETAIL RAW]', rawText);

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

  function extractProduct(responseData) {
    if (!responseData) return null;

    if (responseData.data && responseData.data.product) {
      return responseData.data.product;
    }

    if (responseData.data && responseData.data.id) {
      return responseData.data;
    }

    if (responseData.product) {
      return responseData.product;
    }

    if (responseData.id) {
      return responseData;
    }

    return null;
  }

  function renderError(title, description = '') {
    if (!detailContainer) return;

    detailContainer.innerHTML = `
      <div class="empty-state">
        <h2 class="empty-state__title">${escapeHtml(title)}</h2>
        ${description ? `<p>${escapeHtml(description)}</p>` : ''}
        <a href="${escapeHtml(PRODUCTS_URL)}" class="btn btn--primary" style="margin-top: 20px;">
          Quay lại Menu
        </a>
      </div>
    `;
  }

  function renderProduct(product) {
    const inStock = Number(product.stock || 0) > 0;
    const isActive = Number(product.is_active ?? 1) === 1;

    if (!isActive) {
      renderError('Sản phẩm ngừng kinh doanh');
      return;
    }

    const stockBadge = inStock
      ? `<span class="badge badge--success">Còn hàng (${escapeHtml(product.stock)})</span>`
      : `<span class="badge badge--danger">Hết hàng</span>`;

    const imageUrl = normalizeImageUrl(product.image);
    const ingredient = product.ingredient || product.ingredients || '';
    const ingredientHtml = ingredient
      ? `<p class="product-ingredient"><strong>Thành phần:</strong> ${escapeHtml(ingredient)}</p>`
      : '';

    detailContainer.innerHTML = `
      <div class="product-image-card">
        <img
          src="${escapeHtml(imageUrl)}"
          alt="${escapeHtml(product.name || 'Product')}"
          onerror="this.onerror=null; this.src='${fallbackImageDataUri()}'"
        >
      </div>

      <div class="product-info-panel">
        <div>${stockBadge}</div>

        <h1 class="product-title">${escapeHtml(product.name || 'Product')}</h1>

        <div class="product-price">${escapeHtml(formatPrice(product.price))}</div>

        <p class="product-description">
          ${escapeHtml(product.description || product.short_description || 'Chưa có mô tả chi tiết.')}
        </p>

        ${ingredientHtml}

        <div class="divider"></div>

        <div class="qty-row">
          <div class="qty-selector">
            <button type="button" class="qty-selector__btn" id="btn-qty-minus" ${!inStock ? 'disabled' : ''}>-</button>
            <span class="qty-selector__value" id="qty-value">1</span>
            <button type="button" class="qty-selector__btn" id="btn-qty-plus" ${!inStock ? 'disabled' : ''}>+</button>
          </div>

          <button type="button" class="btn btn--primary" id="btn-add-cart" ${!inStock ? 'disabled' : ''}>
            Thêm vào giỏ
          </button>
        </div>
      </div>
    `;

    if (inStock) {
      bindQuantityAndCart(product);
    }
  }

  function bindQuantityAndCart(product) {
    const btnMinus = document.getElementById('btn-qty-minus');
    const btnPlus = document.getElementById('btn-qty-plus');
    const qtyValue = document.getElementById('qty-value');
    const btnAddCart = document.getElementById('btn-add-cart');

    let currentQty = 1;
    const maxQty = Number(product.stock || 1);

    btnMinus?.addEventListener('click', () => {
      if (currentQty > 1) {
        currentQty -= 1;
        qtyValue.textContent = String(currentQty);
      }
    });

    btnPlus?.addEventListener('click', () => {
      if (currentQty < maxQty) {
        currentQty += 1;
        qtyValue.textContent = String(currentQty);
        return;
      }

      showAlert('danger', `Chỉ còn tối đa ${maxQty} sản phẩm.`);
    });

    btnAddCart?.addEventListener('click', async () => {
      if (!getToken()) {
        window.location.href = LOGIN_URL;
        return;
      }

      btnAddCart.disabled = true;
      btnAddCart.textContent = 'Đang thêm...';

      try {
        const response = await postProductDetail('add_cart', {
          product_id: Number(product.id),
          quantity: currentQty
        });

        if (response.ok) {
          showAlert('success', `Đã thêm ${currentQty} ${product.name} vào giỏ!`);
          return;
        }

        if (['UNAUTHENTICATED', 'TOKEN_INVALID', 'TOKEN_EXPIRED', 'TOKEN_REVOKED'].includes(response.data?.error_code)) {
          window.location.href = LOGIN_URL;
          return;
        }

        showAlert('danger', response.data?.message || 'Lỗi thêm sản phẩm.');
      } catch (error) {
        console.error('[product-detail:add-cart]', error);
        showAlert('danger', 'Lỗi kết nối server.');
      } finally {
        btnAddCart.disabled = false;
        btnAddCart.textContent = 'Thêm vào giỏ';
      }
    });
  }

  async function loadProduct() {
    const productId = getProductId();

    if (productId <= 0) {
      renderError('Không tìm thấy sản phẩm');
      return;
    }

    try {
      const response = await postProductDetail('product', {
        product_id: productId
      });

      if (!response.ok) {
        renderError('Lỗi tải sản phẩm', response.data?.message || '');
        return;
      }

      const product = extractProduct(response.data);

      if (!product) {
        renderError('Không tìm thấy sản phẩm');
        return;
      }

      renderProduct(product);
    } catch (error) {
      console.error('[product-detail:load]', error);
      renderError('Lỗi Máy Chủ', 'Không thể tải chi tiết sản phẩm.');
    }
  }

  if (typeof window.updateGlobalHeaderAuth === 'function') {
    window.updateGlobalHeaderAuth();
  }

  await loadProduct();
});