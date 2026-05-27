 document.addEventListener('DOMContentLoaded', async () => {
    // Global Logout
    document.getElementById('global-btn-logout')?.addEventListener('click', async () => {
        if(window.Api && window.Api.auth) {
            await window.Api.auth.logout();
            window.location.href = 'login.php';
        }
    });


    if (!window.Api.Auth.isLoggedIn()) {
        window.location.href = 'login.php';
        return;
    }


    const detailContainer = document.getElementById('order-detail-container');
    const orderIdDisplay = document.getElementById('display-order-id');


    const urlParams = new URLSearchParams(window.location.search);
    const orderId = urlParams.get('id');


    if (!orderId) {
        detailContainer.innerHTML = `<div class="empty-state w-full" style="grid-column: 1 / -1;"><h2 class="empty-state__title text-danger">Invalid Request</h2><a href="orders.php" class="btn btn--primary mt-4">Back to Orders</a></div>`;
        return;
    }


    orderIdDisplay.textContent = `#${orderId}`;


    const response = await window.Api.orders.getById(orderId);


    if (!response.success || !response.data) {
        detailContainer.innerHTML = `<div class="empty-state w-full" style="grid-column: 1 / -1;"><h2 class="empty-state__title text-danger">Access Denied</h2><p class="empty-state__desc">Order not found or permission denied.</p><a href="orders.php" class="btn btn--primary mt-4">Back to Orders</a></div>`;
        return;
    }


    const order = response.data;
    const items = order.items || order.order_items || [];


    let statusClass = 'badge--neutral';
    const statusText = (order.status || '').toUpperCase();
    if (statusText === 'COMPLETED' || statusText === 'DELIVERED') statusClass = 'badge--success';
    if (statusText === 'PENDING' || statusText === 'PROCESSING') statusClass = 'badge--warning';
    if (statusText === 'CANCELLED') statusClass = 'badge--danger';


    let tableRows = '';
    items.forEach(item => {
        tableRows += `
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <img src="${item.image || '/img/default.png'}" alt="Product" style="width: 48px; height: 48px; object-fit: cover; border-radius: var(--radius-sm);">
                        <span class="font-bold">${item.name || item.product_name}</span>
                    </div>
                </td>
                <td class="text-center">x${item.quantity}</td>
                <td class="text-right font-bold text-primary">${Number(item.price).toLocaleString('en-US')} VND</td>
            </tr>
        `;
    });


    detailContainer.innerHTML = `
        <div class="card p-0 overflow-hidden" style="align-self: start;">
            <div class="table-wrapper" style="border: none;">
                <table class="table">
                    <thead><tr><th>Product</th><th class="text-center">Qty</th><th class="text-right">Price</th></tr></thead>
                    <tbody>${tableRows}</tbody>
                </table>
            </div>
        </div>


        <div class="card p-6 flex-col gap-4" style="align-self: start;">
            <div class="flex justify-between items-center mb-2">
                <h3 class="font-bold" style="font-family: var(--font-heading); font-size: var(--text-lg);">Order Summary</h3>
                <span class="badge ${statusClass}">${statusText}</span>
            </div>
            <div class="divider mt-0 mb-4"></div>
            <div class="flex flex-col gap-3">
                <div><p class="text-xs text-muted uppercase font-bold mb-1">Customer Name</p><p class="text-sm font-bold">${order.shipping_name || order.customer_name || 'N/A'}</p></div>
                <div><p class="text-xs text-muted uppercase font-bold mb-1">Contact Phone</p><p class="text-sm font-bold">${order.shipping_phone || order.phone || 'N/A'}</p></div>
                <div><p class="text-xs text-muted uppercase font-bold mb-1">Shipping Address</p><p class="text-sm font-bold">${order.shipping_address || order.address || 'N/A'}</p></div>
                <div><p class="text-xs text-muted uppercase font-bold mb-1">Order Date</p><p class="text-sm font-bold">${new Date(order.created_at).toLocaleString('en-US')}</p></div>
            </div>
            <div class="divider my-4"></div>
            <div class="flex justify-between items-center">
                <span class="text-sm font-bold uppercase text-muted">Total Amount</span>
                <span class="text-xl font-black text-primary">${Number(order.total_amount || order.total).toLocaleString('en-US')} VND</span>
            </div>
        </div>
    `;
});





