<?php
/**
 * Admin Orders View
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Admin Panel</title>
    <link rel="stylesheet" href="../../public/assets/css/root.css">
    <link rel="stylesheet" href="../../public/assets/css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/../layouts/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <h1 style="font-family: var(--font-heading); font-size: var(--text-2xl);">Orders</h1>
            </header>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>User ID</th>
                            <th>Shipping Name</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="orders-tbody">
                        <tr><td colspan="8" style="text-align: center;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Status Modal -->
    <div class="modal" id="status-modal">
        <div class="modal__content" style="max-width: 400px;">
            <div class="modal__header">
                <h3 class="modal__title">Update Status</h3>
                <button class="modal__close" id="modal-close">&times;</button>
            </div>
            <form id="status-form">
                <input type="hidden" id="order-id">
                
                <div class="form-group">
                    <label class="form-label">New Status</label>
                    <select id="o-status" class="form-input">
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: var(--space-2); margin-top: var(--space-6);">
                    <button type="button" class="btn btn--outline" id="modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn--primary">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../public/assets/js/admin-orders.js"></script>
</body>
</html>
