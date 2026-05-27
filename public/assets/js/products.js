document.addEventListener('DOMContentLoaded', async () => {
    // Xử lý đăng xuất
    document.getElementById('global-btn-logout')?.addEventListener('click', async () => {
        if(window.Api && window.Api.auth) {
            await window.Api.auth.logout();
            window.location.href = 'login.php';
        }
    });

    const productsGrid = document.getElementById('products-grid');
    const alertContainer = document.getElementById('alert-container');

    function showAlert(type, message) {
        alertContainer.innerHTML = `<div class="alert alert--${type}">${message}</div>`;
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => { alertContainer.innerHTML = ''; }, 3000);
    }

    try {
        // Lấy dữ liệu từ API
        const response = await window.Api.products.getAll();

        if (!response || !response.success) {
            productsGrid.innerHTML = `
                <div class="empty-state w-full" style="grid-column: 1 / -1;">
                    <h2 class="empty-state__title text-danger">Oops!</h2>
                    <p class="empty-state__desc">Không thể tải menu. Vui lòng kiểm tra lại kết nối Database.</p>
                </div>
            `;
            return;
        }

        const products = response.data || [];
        
        // Chỉ hiển thị các sản phẩm đang bán (is_active = 1 trong DB)
        const activeProducts = products.filter(p => parseInt(p.is_active) === 1);

        if (activeProducts.length === 0) {
            productsGrid.innerHTML = `
                <div class="empty-state w-full" style="grid-column: 1 / -1;">
                    <h2 class="empty-state__title">Menu trống</h2>
                    <p class="empty-state__desc">Hiện tại chưa có bánh nào. Bạn quay lại sau nhé!</p>
                </div>
            `;
            return;
        }

        let html = '';
        activeProducts.forEach(product => {
            const inStock = parseInt(product.stock) > 0;
            
            // Đảm bảo tên file php trỏ tới đúng (products-detail.php hoặc products_detail.php tùy thư mục)
            const detailUrl = `products-detail.php?id=${product.id}`; 
            
            const imageUrl = product.image ? `/img/product/${product.image}` : '/img/default.png';
            
            // Ưu tiên dùng short_description có sẵn trong DB
            let shortDesc = product.short_description || product.description || 'Món bánh tuyệt ngon.';
            if (shortDesc.length > 60 && !product.short_description) {
                shortDesc = shortDesc.substring(0, 60) + '...';
            }

            html += `
                <div class="product-card">
                    <a href="${detailUrl}">
                        <img src="${imageUrl}" alt="${product.name}" class="product-card__image" onerror="this.src='/img/default.png'">
                    </a>
                    <div class="product-card__body">
                        <a href="${detailUrl}"><h3 class="product-card__name">${product.name}</h3></a>
                        <p class="product-card__description">${shortDesc}</p>
                    </div>
                    <div class="product-card__footer">
                        <div class="product-card__price">${Number(product.price).toLocaleString('en-US')} VND</div>
                        <button class="btn--add-cart add-to-cart-btn" data-id="${product.id}" ${!inStock ? 'disabled' : ''} title="${inStock ? 'Thêm vào giỏ' : 'Hết hàng'}">+</button>
                    </div>
                </div>
            `;
        });
        
        productsGrid.innerHTML = html;

        // Xử lý logic Add to Cart
        const addToCartBtns = document.querySelectorAll('.add-to-cart-btn');
        addToCartBtns.forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                if (!window.Api.Auth || !window.Api.Auth.isLoggedIn()) { 
                    window.location.href = 'login.php'; 
                    return; 
                }
                
                const productId = btn.getAttribute('data-id');
                btn.disabled = true;
                
                try {
                    const addResponse = await window.Api.cart.add(productId, 1);
                    if (addResponse && addResponse.success) { 
                        showAlert('success', 'Đã thêm món vào giỏ hàng!'); 
                    } else { 
                        showAlert('danger', addResponse?.message || 'Không thể thêm vào giỏ.'); 
                    }
                } catch (err) {
                    showAlert('danger', 'Lỗi kết nối đến server.');
                }
                
                btn.disabled = false;
            });
        });

    } catch (error) {
        console.error("Fetch API bị lỗi:", error);
        productsGrid.innerHTML = `
            <div class="empty-state w-full" style="grid-column: 1 / -1;">
                <h2 class="empty-state__title text-danger">Lỗi Máy Chủ</h2>
                <p class="empty-state__desc">Không thể kết nối đến cơ sở dữ liệu Laragon.</p>
            </div>
        `;
    }
});
