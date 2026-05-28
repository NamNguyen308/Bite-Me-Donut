document.addEventListener('DOMContentLoaded', () => {
    const token = localStorage.getItem('bmd_access_token');
    if (!token) {
        window.location.href = '../../views/admin/admin-login.php';
        return;
    }

    const tbody = document.getElementById('customers-tbody');
    const modal = document.getElementById('customer-modal');
    const form = document.getElementById('customer-form');

    // Load customers
    async function loadCustomers() {
        try {
            const res = await fetch('/Bite-Me-Donut/public/api/admin/customers', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const data = await res.json();
            
            if (data.success) {
                renderTable(data.data.customers);
            }
        } catch (err) {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="8">Error loading customers</td></tr>';
        }
    }

    function renderTable(users) {
        if (!users.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center">No customers found</td></tr>';
            return;
        }

        tbody.innerHTML = users.map(u => `
            <tr>
                <td>${u.id}</td>
                <td><strong>${u.name}</strong></td>
                <td>${u.email || '-'}</td>
                <td>${u.phone || '-'}</td>
                <td><span class="status-badge ${u.role === 'admin' ? 'status-badge--info' : 'status-badge--warning'}">${u.role}</span></td>
                <td>
                    <span class="status-badge ${u.is_active ? 'status-badge--success' : 'status-badge--danger'}">
                        ${u.is_active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>${new Date(u.created_at).toLocaleDateString()}</td>
                <td>
                    <button class="btn btn--outline btn-delete" data-id="${u.id}" style="color: var(--color-danger); border-color: var(--color-danger);">Delete</button>
                </td>
            </tr>
        `).join('');

        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                if(confirm('Are you sure you want to delete this user?')) {
                    const id = e.target.getAttribute('data-id');
                    await deleteCustomer(id);
                }
            });
        });
    }

    // Delete Customer
    async function deleteCustomer(id) {
        try {
            await fetch(`/Bite-Me-Donut/public/api/admin/customers/${id}`, {
                method: 'DELETE',
                headers: { 'Authorization': `Bearer ${token}` }
            });
            loadCustomers();
        } catch (err) {
            console.error(err);
        }
    }

    // Modal Handling
    function openModal() {
        form.reset();
        modal.classList.add('active');
    }

    function closeModal() {
        modal.classList.remove('active');
    }

    document.getElementById('btn-add-customer').addEventListener('click', openModal);
    document.getElementById('modal-close').addEventListener('click', closeModal);
    document.getElementById('modal-cancel').addEventListener('click', closeModal);

    // Form Submit
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const payload = {
            name: document.getElementById('c-name').value,
            phone: document.getElementById('c-phone').value,
            email: document.getElementById('c-email').value,
            password: document.getElementById('c-password').value,
            role: document.getElementById('c-role').value,
            is_active: parseInt(document.getElementById('c-is_active').value)
        };

        try {
            await fetch('/Bite-Me-Donut/public/api/admin/customers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify(payload)
            });
            
            closeModal();
            loadCustomers();
        } catch (err) {
            console.error(err);
            alert('Error creating customer');
        }
    });

    // Init
    loadCustomers();
});
