document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('global-btn-logout')?.addEventListener('click', async () => {
        if(window.Api && window.Api.auth) {
            await window.Api.auth.logout();
            window.location.href = 'login.php'; 
        }
    });


    const productsGrid = document.getElementById('products-grid');
    const alertContainer = document.getElementById('alert-container');


    const response = await window.Api.products.getAll();


    if (!response.success) {
        productsGrid.innerHTML = `
            <div class="empty-state w-full" style="grid-column: 1 / -1;">
                <h2 class="empty-state__title text-danger">Oops!</h2>
                <p class="empty-state__desc">Could not load the menu. Please try again later.</p>
            </div>
        `;
        return;
    }


    const products = response.data || [];


    if (products.length === 0) {
        productsGrid.innerHTML = `
            <div class="empty-state w-full" style="grid-column: 1 / -1;">
                <h2 class="empty-state__title">Menu is empty</h2>
                <p class="empty-state__desc">We are currently baking new donuts. Check back soon!</p>
            </div>
        `;
        return;
    }


    let html = '';
    products.forEach(product => {
        const inStock = parseInt(product.stock) > 0;
        const detailUrl = `products-detail.php?id=${product.id}`;
       
        // Nối đường dẫn ảnh chuẩn theo thư mục public/img/product
        const imageUrl = product.image ? `/img/product/${product.image}` : '/img/default.png';
       
        // Tạo mô tả ngắn từ trường description (cắt ở 60 ký tự)
        let shortDesc = product.description || 'Delicious freshly baked treat.';
        if (shortDesc.length > 60) {
            shortDesc = shortDesc.substring(0, 60) + '...';
        }


        html += `
            <div class="product-card">
                <a href="${detailUrl}">
                    <img src="${imageUrl}" alt="${product.name}" class="product-card__image">
                </a>
                <div class="product-card__body">
                    <a href="${detailUrl}"><h3 class="product-card__name">${product.name}</h3></a>
                    <p class="product-card__description">${shortDesc}</p>
                </div>
                <div class="product-card__footer">
                    <div class="product-card__price">${Number(product.price).toLocaleString('en-US')} VND</div>
                    <button class="btn--add-cart add-to-cart-btn" data-id="${product.id}" ${!inStock ? 'disabled' : ''} title="${inStock ? 'Add to Cart' : 'Out of Stock'}">+</button>
                </div>
            </div>
        `;
    });
   
    productsGrid.innerHTML = html;


    const addToCartBtns = document.querySelectorAll('.add-to-cart-btn');
    addToCartBtns.forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            if (!window.Api.Auth.isLoggedIn()) { window.location.href = 'login.php'; return; }
           
            const productId = btn.getAttribute('data-id');
            btn.disabled = true;
           
            const addResponse = await window.Api.cart.add(productId, 1);
            if (addResponse.success) { showAlert('success', 'Item added to your cart successfully!'); }
            else { showAlert('danger', addResponse.message || 'Failed to add item.'); }
           
            btn.disabled = false;
        });
    });


    function showAlert(type, message) {
        alertContainer.innerHTML = `<div class="alert alert--${type}">${message}</div>`;
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => { alertContainer.innerHTML = ''; }, 3000);
    }
});





