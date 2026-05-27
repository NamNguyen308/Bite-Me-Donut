document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('global-btn-logout')?.addEventListener('click', async () => {
        if(window.Api && window.Api.auth) {
            await window.Api.auth.logout();
            window.location.href = 'login.php';
        } 
    });


    const detailContainer = document.getElementById('product-detail-container');
    const alertContainer = document.getElementById('alert-container'); 


    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('id');


    if (!productId) {
        detailContainer.innerHTML = `<div class="empty-state w-full" style="grid-column: 1 / -1;"><h2 class="empty-state__title text-danger">Product Not Found</h2><a href="products.php" class="btn btn--primary mt-4">Go Back to Menu</a></div>`;
        return;
    }


    const response = await window.Api.products.getById(productId);


    if (!response.success || !response.data) {
        detailContainer.innerHTML = `<div class="empty-state w-full" style="grid-column: 1 / -1;"><h2 class="empty-state__title text-danger">Error Loading Product</h2><a href="products.php" class="btn btn--primary mt-4">Go Back to Menu</a></div>`;
        return;
    }


    const product = response.data;
    const inStock = parseInt(product.stock) > 0;
    const stockBadge = inStock ? `<span class="badge badge--success">In Stock (${product.stock})</span>` : `<span class="badge badge--danger">Out of Stock</span>`;
   
    // Xử lý nối đúng đường dẫn ảnh như yêu cầu
    const imageUrl = product.image ? `/img/product/${product.image}` : '/img/default.png';


    detailContainer.innerHTML = `
        <div class="card flex items-center justify-center p-6">
            <img src="${imageUrl}" alt="${product.name}" class="w-full" style="object-fit: contain; max-height: 400px;">
        </div>
        <div class="flex flex-col justify-center p-4">
            <div>${stockBadge}</div>
            <h1 class="page-title text-left mt-4 mb-2">${product.name}</h1>
            <p class="text-xl font-black text-primary mb-4">${Number(product.price).toLocaleString('en-US')} VND</p>
            <p class="text-sm text-muted mb-8" style="line-height: 1.8;">${product.description || 'No detailed description available.'}</p>
            <div class="divider"></div>
            <div class="flex items-center gap-6 mt-4">
                <div class="qty-selector">
                    <button class="qty-selector__btn" id="btn-qty-minus" ${!inStock ? 'disabled' : ''}>-</button>
                    <span class="qty-selector__value" id="qty-value">1</span>
                    <button class="qty-selector__btn" id="btn-qty-plus" ${!inStock ? 'disabled' : ''}>+</button>
                </div>
                <button class="btn btn--primary" style="padding: var(--space-3) var(--space-8);" id="btn-add-cart" ${!inStock ? 'disabled' : ''}>Add to Cart</button>
            </div>
        </div>
    `;


    if (inStock) {
        const btnMinus = document.getElementById('btn-qty-minus');
        const btnPlus = document.getElementById('btn-qty-plus');
        const qtyValue = document.getElementById('qty-value');
        const btnAddCart = document.getElementById('btn-add-cart');
       
        let currentQty = 1;
        const maxQty = parseInt(product.stock); 


        btnMinus.addEventListener('click', () => { if (currentQty > 1) { currentQty--; qtyValue.textContent = currentQty; }});
        btnPlus.addEventListener('click', () => { if (currentQty < maxQty) { currentQty++; qtyValue.textContent = currentQty; } else { showAlert('warning', `Max limit is ${maxQty}.`); }});


        btnAddCart.addEventListener('click', async () => {
            if (!window.Api.Auth.isLoggedIn()) { window.location.href = 'login.php'; return; }
            btnAddCart.disabled = true;
            btnAddCart.textContent = "Adding...";
            const addResponse = await window.Api.cart.add(productId, currentQty);
            if (addResponse.success) { showAlert('success', `Added ${currentQty}x ${product.name} successfully!`); }
            else { showAlert('danger', addResponse.message || 'Failed to add item.'); }
            btnAddCart.disabled = false;
            btnAddCart.textContent = "Add to Cart";
        });
    }


    function showAlert(type, message) {
        alertContainer.innerHTML = `<div class="alert alert--${type}">${message}</div>`;
        setTimeout(() => { alertContainer.innerHTML = ''; }, 4000);
    }
});





