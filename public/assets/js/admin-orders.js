document.addEventListener('DOMContentLoaded', () => {
    const token = localStorage.getItem('bmd_access_token');
    if (!token) {
        window.location.href = '../../views/admin/admin-login.php';
        return;
    }

    const tbody = document.getElementById('orders-tbody');
    const modal = document.getElementById('status-modal');
    const form = document.getElementById('status-form');

    // Load orders
    async function loadOrders() {
        try {
            const res = await fetch('/api/admin/orders', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const data = await res.json();
            
            if (data.status === 'success') {
                renderTable(data.data.orders);
            }
        } catch (err) {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="8">Error loading orders</td></tr>';
        }
    }

    function renderTable(orders) {
        if (!orders.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center">No orders found</td></tr>';
            return;
        }

        tbody.innerHTML = orders.map(o => `
            <tr>
                <td>#${o.id}</td>
                <td>${o.user_id}</td>
                <td>
                    <strong>${o.shipping_name}</strong><br>
                    <small style="color:var(--color-text-muted)">${o.shipping_phone}</small>
                </td>
                <td>$${Number(o.total).toFixed(2)}</td>
                <td>${o.payment_method.toUpperCase()}</td>
                <td>
                    <span class="status-badge status-badge--${getStatusColor(o.status)}">
                        ${o.status.toUpperCase()}
                    </span>
                </td>
                <td>${new Date(o.created_at).toLocaleDateString()}</td>
                <td>
                    <button class="btn btn--outline btn-update" data-id="${o.id}" data-status="${o.status}">Update Status</button>
                </td>
            </tr>
        `).join('');

        document.querySelectorAll('.btn-update').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.getAttribute('data-id');
                const status = e.target.getAttribute('data-status');
                openModal(id, status);
            });
        });
    }

    function getStatusColor(status) {
        switch(status) {
            case 'completed': return 'success';
            case 'cancelled': return 'danger';
            case 'processing': return 'info';
            default: return 'warning';
        }
    }

    // Modal Handling
    function openModal(id, status) {
        document.getElementById('order-id').value = id;
        document.getElementById('o-status').value = status;
        modal.classList.add('active');
    }

    function closeModal() {
        modal.classList.remove('active');
    }

    document.getElementById('modal-close').addEventListener('click', closeModal);
    document.getElementById('modal-cancel').addEventListener('click', closeModal);

    // Form Submit
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const id = document.getElementById('order-id').value;
        const status = document.getElementById('o-status').value;

        try {
            await fetch(`/api/admin/orders/${id}/status`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({ status })
            });
            
            closeModal();
            loadOrders();
        } catch (err) {
            console.error(err);
            alert('Error updating status');
        }
    });

    // Init
    loadOrders();
});
