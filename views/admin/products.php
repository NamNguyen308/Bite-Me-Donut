<?php
/**
 * Admin Products View
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Admin Panel</title>
    <link rel="stylesheet" href="../../public/assets/css/root.css">
    <link rel="stylesheet" href="../../public/assets/css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/../layouts/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <h1 style="font-family: var(--font-heading); font-size: var(--text-2xl);">Products</h1>
                <button class="btn btn--primary" id="btn-add-product">Add New Product</button>
            </header>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="products-tbody">
                        <tr><td colspan="7" style="text-align: center;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Product Modal -->
    <div class="modal" id="product-modal">
        <div class="modal__content">
            <div class="modal__header">
                <h3 class="modal__title" id="modal-title">Add Product</h3>
                <button class="modal__close" id="modal-close">&times;</button>
            </div>
            <form id="product-form">
                <input type="hidden" id="product-id">
                
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" id="p-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Short Description</label>
                    <input type="text" id="p-short_desc" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea id="p-desc" class="form-input" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Ingredient</label>
                    <input type="text" id="p-ingredient" class="form-input">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Price</label>
                        <input type="number" step="0.01" id="p-price" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock</label>
                        <input type="number" id="p-stock" class="form-input" value="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Image URL</label>
                    <input type="text" id="p-image" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select id="p-is_active" class="form-input">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: var(--space-2); margin-top: var(--space-6);">
                    <button type="button" class="btn btn--outline" id="modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn--primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../public/assets/js/admin-products.js"></script>
</body>
</html>
