document.addEventListener('DOMContentLoaded', () => {
    const token = localStorage.getItem('bmd_access_token');
    if (!token) {
        window.location.href = '../../views/admin/admin-login.php';
        return;
    }

    const tbody = document.getElementById('products-tbody');
    const modal = document.getElementById('product-modal');
    const form = document.getElementById('product-form');
    
    let isEditMode = false;

    // Load products
    async function loadProducts() {
        try {
            const res = await fetch('/api/admin/products', {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            const data = await res.json();
            
            if (data.status === 'success') {
                renderTable(data.data.products);
            }
        } catch (err) {
            console.error(err);
            tbody.innerHTML = '<tr><td colspan="7">Error loading products</td></tr>';
        }
    }

    function renderTable(products) {
        if (!products.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center">No products found</td></tr>';
            return;
        }

        tbody.innerHTML = products.map(p => `
            <tr>
                <td>${p.id}</td>
                <td><img src="${p.image || 'https://placehold.co/100x100?text=No+Img'}" class="thumbnail-img" alt="${p.name}"></td>
                <td><strong>${p.name}</strong><br><small style="color:var(--color-text-muted)">${p.short_description || ''}</small></td>
                <td>$${p.price.toFixed(2)}</td>
                <td>${p.stock}</td>
                <td>
                    <span class="status-badge ${p.is_active ? 'status-badge--success' : 'status-badge--danger'}">
                        ${p.is_active ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>
                    <button class="btn btn--outline btn-edit" data-id="${p.id}" data-product='${JSON.stringify(p).replace(/'/g, "&#39;")}'>Edit</button>
                    <button class="btn btn--outline btn-delete" data-id="${p.id}" style="color: var(--color-danger); border-color: var(--color-danger);">Delete</button>
                </td>
            </tr>
        `).join('');

        // Bind events
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const product = JSON.parse(e.target.getAttribute('data-product'));
                openModal(product);
            });
        });

        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                if(confirm('Are you sure you want to delete this product?')) {
                    const id = e.target.getAttribute('data-id');
                    await deleteProduct(id);
                }
            });
        });
    }

    // Delete Product
    async function deleteProduct(id) {
        try {
            await fetch(`/api/admin/products/${id}`, {
                method: 'DELETE',
                headers: { 'Authorization': `Bearer ${token}` }
            });
            loadProducts();
        } catch (err) {
            console.error(err);
        }
    }

    // Modal Handling
    function openModal(product = null) {
        modal.classList.add('active');
        isEditMode = !!product;
        
        document.getElementById('modal-title').textContent = isEditMode ? 'Edit Product' : 'Add Product';
        
        if (product) {
            document.getElementById('product-id').value = product.id;
            document.getElementById('p-name').value = product.name;
            document.getElementById('p-short_desc').value = product.short_description || '';
            document.getElementById('p-desc').value = product.description || '';
            document.getElementById('p-ingredient').value = product.ingredient || '';
            document.getElementById('p-price').value = product.price;
            document.getElementById('p-stock').value = product.stock;
            document.getElementById('p-image').value = product.image || '';
            document.getElementById('p-is_active').value = product.is_active;
        } else {
            form.reset();
            document.getElementById('product-id').value = '';
        }
    }

    function closeModal() {
        modal.classList.remove('active');
    }

    document.getElementById('btn-add-product').addEventListener('click', () => openModal());
    document.getElementById('modal-close').addEventListener('click', closeModal);
    document.getElementById('modal-cancel').addEventListener('click', closeModal);

    // Form Submit
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const id = document.getElementById('product-id').value;
        const payload = {
            name: document.getElementById('p-name').value,
            short_description: document.getElementById('p-short_desc').value,
            description: document.getElementById('p-desc').value,
            ingredient: document.getElementById('p-ingredient').value,
            price: parseFloat(document.getElementById('p-price').value),
            stock: parseInt(document.getElementById('p-stock').value),
            image: document.getElementById('p-image').value,
            is_active: parseInt(document.getElementById('p-is_active').value)
        };

        const method = isEditMode ? 'POST' : 'POST'; // using POST for both store and update as per routes
        const url = isEditMode ? `/api/admin/products/${id}` : '/api/admin/products';

        try {
            await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify(payload)
            });
            
            closeModal();
            loadProducts();
        } catch (err) {
            console.error(err);
            alert('Error saving product');
        }
    });

    // Init
    loadProducts();
});
