document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('global-btn-logout')?.addEventListener('click', async () => {
        if(window.Api && window.Api.auth) {
            await window.Api.auth.logout();
            window.location.href = 'login.php';
        }
    });

    const detailContainer = document.getElementById('product-detail-container');
    const alertContainer = document.getElementById('alert-container');

    function showAlert(type, message) {
        alertContainer.innerHTML = `<div class="alert alert--${type}">${message}</div>`;
        setTimeout(() => { alertContainer.innerHTML = ''; }, 4000);
    }

    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('id');

    if (!productId) {
        detailContainer.innerHTML = `<div class="empty-state w-full" style="grid-column: 1 / -1;"><h2 class="empty-state__title text-danger">Không tìm thấy sản phẩm</h2><a href="products.php" class="btn btn--primary mt-4">Quay lại Menu</a></div>`;
        return;
    }

    try {
        const response = await window.Api.products.getById(productId);

        if (!response || !response.success || !response.data) {
            detailContainer.innerHTML = `<div class="empty-state w-full" style="grid-column: 1 / -1;"><h2 class="empty-state__title text-danger">Lỗi tải sản phẩm</h2><a href="products.php" class="btn btn--primary mt-4">Quay lại Menu</a></div>`;
            return;
        }

        const product = response.data;
        
        // Kiểm tra xem sản phẩm có bị ngừng kinh doanh (is_active = 0) không
        if (parseInt(product.is_active) === 0) {
            detailContainer.innerHTML = `<div class="empty-state w-full" style="grid-column: 1 / -1;"><h2 class="empty-state__title text-danger">Sản phẩm ngừng kinh doanh</h2><a href="products.php" class="btn btn--primary mt-4">Quay lại Menu</a></div>`;
            return;
        }

        const inStock = parseInt(product.stock) > 0;
        const stockBadge = inStock ? `<span class="badge badge--success">Còn hàng (${product.stock})</span>` : `<span class="badge badge--danger">Hết hàng</span>`;
        
        const imageUrl = product.image ? `/img/product/${product.image}` : '/img/default.png';

        // Gọi ra thêm trường ingredient (Thành phần) từ Schema DB để làm giao diện xịn hơn
        const ingredientsHtml = product.ingredient ? `<p class="text-sm text-muted mb-4"><strong>Thành phần:</strong> ${product.ingredient}</p>` : '';

        detailContainer.innerHTML = `
            <div class="card flex items-center justify-center p-6">
                <img src="${imageUrl}" alt="${product.name}" class="w-full" style="object-fit: contain; max-height: 400px;" onerror="this.src='/img/default.png'">
            </div>
            <div class="flex flex-col justify-center p-4">
                <div>${stockBadge}</div>
                <h1 class="page-title text-left mt-4 mb-2">${product.name}</h1>
                <p class="text-xl font-black text-primary mb-4">${Number(product.price).toLocaleString('en-US')} VND</p>
                <p class="text-md mb-2" style="line-height: 1.6;">${product.description || 'Chưa có mô tả chi tiết.'}</p>
                ${ingredientsHtml}
                <div class="divider"></div>
                <div class="flex items-center gap-6 mt-4">
                    <div class="qty-selector">
                        <button class="qty-selector__btn" id="btn-qty-minus" ${!inStock ? 'disabled' : ''}>-</button>
                        <span class="qty-selector__value" id="qty-value">1</span>
                        <button class="qty-selector__btn" id="btn-qty-plus" ${!inStock ? 'disabled' : ''}>+</button>
                    </div>
                    <button class="btn btn--primary" style="padding: var(--space-3) var(--space-8);" id="btn-add-cart" ${!inStock ? 'disabled' : ''}>Thêm vào giỏ</button>
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

            btnMinus.addEventListener('click', () => { 
                if (currentQty > 1) { 
                    currentQty--; 
                    qtyValue.textContent = currentQty; 
                }
            });
            
            btnPlus.addEventListener('click', () => { 
                if (currentQty < maxQty) { 
                    currentQty++; 
                    qtyValue.textContent = currentQty; 
                } else { 
                    showAlert('danger', `Chỉ còn tối đa ${maxQty} sản phẩm.`); 
                }
            });

            btnAddCart.addEventListener('click', async () => {
                if (!window.Api.Auth || !window.Api.Auth.isLoggedIn()) { 
                    window.location.href = 'login.php'; 
                    return; 
                }
                
                btnAddCart.disabled = true;
                btnAddCart.textContent = "Đang thêm...";
                
                try {
                    const addResponse = await window.Api.cart.add(productId, currentQty);
                    if (addResponse && addResponse.success) { 
                        showAlert('success', `Đã thêm ${currentQty} ${product.name} vào giỏ!`); 
                    } else { 
                        showAlert('danger', addResponse?.message || 'Lỗi thêm sản phẩm.'); 
                    }
                } catch(err) {
                    showAlert('danger', 'Lỗi kết nối server.');
                }
                
                btnAddCart.disabled = false;
                btnAddCart.textContent = "Thêm vào giỏ";
            });
        }
    } catch (error) {
        console.error("Lỗi get chi tiết SP:", error);
        detailContainer.innerHTML = `
            <div class="empty-state w-full" style="grid-column: 1 / -1;">
                <h2 class="empty-state__title text-danger">Lỗi Máy Chủ</h2>
                <p>Không thể tải chi tiết sản phẩm.</p>
            </div>
        `;
    }
});
