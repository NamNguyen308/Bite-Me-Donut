<?php
/**
 * Admin Customers View
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Admin Panel</title>
    <link rel="stylesheet" href="../../public/assets/css/root.css">
    <link rel="stylesheet" href="../../public/assets/css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/../layouts/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <h1 style="font-family: var(--font-heading); font-size: var(--text-2xl);">Customers</h1>
                <button class="btn btn--primary" id="btn-add-customer">Add New Customer</button>
            </header>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customers-tbody">
                        <tr><td colspan="8" style="text-align: center;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Customer Modal -->
    <div class="modal" id="customer-modal">
        <div class="modal__content">
            <div class="modal__header">
                <h3 class="modal__title">Add Customer</h3>
                <button class="modal__close" id="modal-close">&times;</button>
            </div>
            <form id="customer-form">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" id="c-name" class="form-input" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="text" id="c-phone" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" id="c-email" class="form-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" id="c-password" class="form-input" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select id="c-role" class="form-input">
                            <option value="customer">Customer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select id="c-is_active" class="form-input">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: var(--space-2); margin-top: var(--space-6);">
                    <button type="button" class="btn btn--outline" id="modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn--primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../public/assets/js/admin-customers.js"></script>
</body>
</html>
