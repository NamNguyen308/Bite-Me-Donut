document.addEventListener('DOMContentLoaded', async () => {
  'use strict';

  console.log('[ADMIN PRODUCTS JS] loaded');

  const CONFIG = window.ADMIN_PRODUCTS_CONFIG || {};

  const PRODUCTS_PROXY_URL =
    CONFIG.productsProxyUrl || `${window.location.pathname}?admin_products_ajax=1`;

  const LOGIN_URL =
    CONFIG.loginUrl || '../auth/user-login.php';

  const IMAGE_BASE_URL =
    CONFIG.imageBaseUrl || '../../public/assets/img/product';

  const TOKEN_KEYS = ['access_token', 'auth_token', 'bmd_access_token'];
  const USER_KEYS = ['auth_user', 'current_user', 'user', 'bmd_user'];

  const tbody = document.getElementById('products-tbody');

  const modal = document.getElementById('product-modal');
  const form = document.getElementById('product-form');

  const modalTitle = document.getElementById('modal-title');

  const inputId = document.getElementById('product-id');
  const inputName = document.getElementById('p-name');
  const inputShortDesc = document.getElementById('p-short_desc');
  const inputDesc = document.getElementById('p-desc');
  const inputIngredient = document.getElementById('p-ingredient');
  const inputPrice = document.getElementById('p-price');
  const inputStock = document.getElementById('p-stock');
  const inputImage = document.getElementById('p-image');
  const inputIsActive = document.getElementById('p-is_active');

  let products = [];

  function getToken() {
    for (const key of TOKEN_KEYS) {
      const value = localStorage.getItem(key) || sessionStorage.getItem(key);

      if (value) {
        return value;
      }
    }

    return null;
  }

  function clearSession() {
    TOKEN_KEYS.forEach((key) => {
      localStorage.removeItem(key);
      sessionStorage.removeItem(key);
    });

    USER_KEYS.forEach((key) => {
      localStorage.removeItem(key);
      sessionStorage.removeItem(key);
    });

    localStorage.removeItem('is_logged_in');
    sessionStorage.removeItem('is_logged_in');
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function formatMoney(value) {
    return Number(value || 0).toLocaleString('vi-VN') + ' VND';
  }

  function fallbackImageDataUri() {
    const svg = `
      <svg xmlns="http://www.w3.org/2000/svg" width="120" height="80" viewBox="0 0 120 80">
        <rect width="120" height="80" fill="#fdf3c8"/>
        <circle cx="60" cy="38" r="20" fill="#e91e8c" opacity="0.2"/>
        <text x="60" y="70" text-anchor="middle" font-family="Arial" font-size="10" fill="#2b1a0e">Donut</text>
      </svg>
    `;

    return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
  }

  function imageUrl(image) {
    const value = String(image || '').trim();

    if (!value) {
      return fallbackImageDataUri();
    }

    if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/')) {
      return value;
    }

    return `${IMAGE_BASE_URL}/${encodeURIComponent(value)}`;
  }

  async function postProducts(action, payload = {}) {
    const token = getToken();

    if (!token) {
      return {
        ok: false,
        status: 401,
        data: {
          success: false,
          error_code: 'UNAUTHENTICATED',
          message: 'Missing access token'
        }
      };
    }

    const body = {
      action,
      access_token: token,
      ...payload
    };

    console.log('[ADMIN PRODUCTS POST]', PRODUCTS_PROXY_URL, body);

    const response = await fetch(PRODUCTS_PROXY_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(body)
    });

    const rawText = await response.text();

    console.log('[ADMIN PRODUCTS STATUS]', response.status);
    console.log('[ADMIN PRODUCTS RAW]', rawText);

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

  function extractProducts(responseData) {
    if (!responseData) return [];

    if (responseData.data && Array.isArray(responseData.data.products)) {
      return responseData.data.products;
    }

    if (Array.isArray(responseData.products)) {
      return responseData.products;
    }

    if (Array.isArray(responseData.data)) {
      return responseData.data;
    }

    return [];
  }

  function renderProducts() {
    if (!tbody) return;

    if (!products.length) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" style="text-align:center;">No products found</td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = products.map((product) => {
      const active = Number(product.is_active ?? 1) === 1;
      const shortDesc = product.short_description || product.short_desc || '';
      const ingredient = product.ingredient || product.ingredients || '';

      return `
        <tr>
          <td>${escapeHtml(product.id)}</td>

          <td>
            <img
              src="${escapeHtml(imageUrl(product.image))}"
              alt="${escapeHtml(product.name)}"
              style="width:56px;height:56px;object-fit:cover;border-radius:10px;background:#fff;"
              onerror="this.onerror=null;this.src='${fallbackImageDataUri()}'"
            >
          </td>

          <td>
            <strong>${escapeHtml(product.name || '-')}</strong>
            ${shortDesc ? `<div style="font-size:12px;color:#777;">${escapeHtml(shortDesc)}</div>` : ''}
            ${ingredient ? `<div style="font-size:12px;color:#999;">${escapeHtml(ingredient)}</div>` : ''}
          </td>

          <td>${escapeHtml(formatMoney(product.price))}</td>
          <td>${escapeHtml(product.stock)}</td>

          <td>
            <span style="
              display:inline-flex;
              padding:4px 10px;
              border-radius:999px;
              font-size:12px;
              font-weight:700;
              background:${active ? '#e8f5e9' : '#ffebee'};
              color:${active ? '#1b5e20' : '#b71c1c'};
            ">
              ${active ? 'Active' : 'Inactive'}
            </span>
          </td>

          <td>
            <button type="button" class="btn btn--outline btn-edit-product" data-id="${escapeHtml(product.id)}">
              Edit
            </button>

            <button
              type="button"
              class="btn btn--danger btn-delete-product"
              data-id="${escapeHtml(product.id)}"
              ${!active ? 'disabled' : ''}
            >
              ${active ? 'Delete' : 'Inactive'}
            </button>
          </td>
        </tr>
      `;
    }).join('');
  }

  function findProduct(id) {
    return products.find((item) => Number(item.id) === Number(id)) || null;
  }

  function openModal(mode, product = null) {
    console.log('[ADMIN PRODUCTS] Open modal:', mode, product?.id || '');

    if (!modal || !form) {
      console.error('[ADMIN PRODUCTS] Modal or form not found');
      return;
    }

    form.reset();

    if (mode === 'edit' && product) {
      modalTitle.textContent = 'Edit Product';

      inputId.value = product.id || '';
      inputName.value = product.name || '';
      inputShortDesc.value = product.short_description || product.short_desc || '';
      inputDesc.value = product.description || '';
      inputIngredient.value = product.ingredient || product.ingredients || '';
      inputPrice.value = product.price || 0;
      inputStock.value = product.stock || 0;
      inputImage.value = product.image || '';
      inputIsActive.value = String(product.is_active ?? 1);
    } else {
      modalTitle.textContent = 'Add Product';

      inputId.value = '';
      inputName.value = '';
      inputShortDesc.value = '';
      inputDesc.value = '';
      inputIngredient.value = '';
      inputPrice.value = '';
      inputStock.value = 0;
      inputImage.value = '';
      inputIsActive.value = '1';
    }

    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.removeAttribute('aria-hidden');

    modal.classList.add('active');
    modal.classList.add('is-open');

    modal.style.setProperty('display', 'flex', 'important');
    modal.style.setProperty('pointer-events', 'auto', 'important');
    modal.style.setProperty('visibility', 'visible', 'important');
    modal.style.setProperty('opacity', '1', 'important');

    setTimeout(() => {
      inputName?.focus();
    }, 0);
  }

  function closeModal() {
    if (!modal) return;

    modal.classList.remove('active');
    modal.classList.remove('is-open');

    modal.hidden = true;
    modal.setAttribute('hidden', 'hidden');
    modal.setAttribute('aria-hidden', 'true');

    modal.style.setProperty('display', 'none', 'important');
    modal.style.setProperty('pointer-events', 'none', 'important');
    modal.style.setProperty('visibility', 'hidden', 'important');
    modal.style.setProperty('opacity', '0', 'important');
  }

  async function loadProducts() {
    if (!getToken()) {
      clearSession();
      window.location.href = LOGIN_URL;
      return;
    }

    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" style="text-align:center;">Loading...</td>
        </tr>
      `;
    }

    const response = await postProducts('list');

    if (!response.ok) {
      console.error('[admin-products:list]', response.data);

      if (
        [
          'UNAUTHENTICATED',
          'TOKEN_INVALID',
          'TOKEN_EXPIRED',
          'TOKEN_REVOKED',
          'ADMIN_REQUIRED'
        ].includes(response.data?.error_code)
      ) {
        clearSession();
        window.location.href = LOGIN_URL;
        return;
      }

      if (tbody) {
        tbody.innerHTML = `
          <tr>
            <td colspan="7" style="text-align:center;">
              ${escapeHtml(response.data?.message || 'Error loading products')}
            </td>
          </tr>
        `;
      }

      return;
    }

    products = extractProducts(response.data);
    renderProducts();
  }

  document.addEventListener('click', async (event) => {
    const addButton = event.target.closest('#btn-add-product');
    const editButton = event.target.closest('.btn-edit-product');
    const deleteButton = event.target.closest('.btn-delete-product');
    const closeButton = event.target.closest('#modal-close');
    const cancelButton = event.target.closest('#modal-cancel');

    if (addButton) {
      event.preventDefault();
      console.log('[ADMIN PRODUCTS] Add clicked');
      openModal('create');
      return;
    }

    if (editButton) {
      event.preventDefault();

      const id = Number(editButton.dataset.id || 0);
      console.log('[ADMIN PRODUCTS] Edit clicked', id);

      const product = findProduct(id);

      if (!product) {
        alert('Product not found');
        return;
      }

      openModal('edit', product);
      return;
    }

    if (deleteButton) {
      event.preventDefault();

      const id = Number(deleteButton.dataset.id || 0);
      console.log('[ADMIN PRODUCTS] Delete clicked', id);

      if (!id) {
        alert('Invalid product id');
        return;
      }

      if (!confirm('Delete this product? It will be marked as inactive.')) {
        return;
      }

      deleteButton.disabled = true;

      try {
        const response = await postProducts('delete', { id });

        if (!response.ok) {
          alert(response.data?.message || 'Cannot delete product');
          return;
        }

        await loadProducts();
      } finally {
        deleteButton.disabled = false;
      }

      return;
    }

    if (closeButton || cancelButton) {
      event.preventDefault();
      closeModal();
      return;
    }

    if (modal && event.target === modal) {
      closeModal();
    }
  });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const id = Number(inputId.value || 0);

    const payload = {
      id,
      name: inputName.value.trim(),
      short_description: inputShortDesc.value.trim(),
      short_desc: inputShortDesc.value.trim(),
      description: inputDesc.value.trim(),
      ingredient: inputIngredient.value.trim(),
      ingredients: inputIngredient.value.trim(),
      price: Number(inputPrice.value || 0),
      stock: Number(inputStock.value || 0),
      image: inputImage.value.trim(),
      is_active: Number(inputIsActive.value || 1)
    };

    const action = id > 0 ? 'update' : 'create';
    const submitBtn = form.querySelector('button[type="submit"]');

    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving...';
    }

    try {
      const response = await postProducts(action, payload);

      if (!response.ok) {
        alert(response.data?.message || 'Cannot save product');
        return;
      }

      closeModal();
      await loadProducts();
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save';
      }
    }
  });

  closeModal();
  await loadProducts();
});