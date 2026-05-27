<?php
/**
 * Admin Dashboard View
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin Panel</title>
    <link rel="stylesheet" href="../../public/assets/css/root.css">
    <link rel="stylesheet" href="../../public/assets/css/admin.css">
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/../layouts/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-header">
                <h1 style="font-family: var(--font-heading); font-size: var(--text-2xl);">Dashboard</h1>
                <div id="admin-user-info" style="font-weight: 500;"></div>
            </header>

            <div class="kpi-grid">
                <div class="kpi-card">
                    <span class="kpi-card__title">Total Orders</span>
                    <span class="kpi-card__value" id="kpi-orders">0</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-card__title">Total Revenue</span>
                    <span class="kpi-card__value" id="kpi-revenue">$0</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-card__title">Total Products</span>
                    <span class="kpi-card__value" id="kpi-products">0</span>
                </div>
                <div class="kpi-card">
                    <span class="kpi-card__title">Total Customers</span>
                    <span class="kpi-card__value" id="kpi-customers">0</span>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-container">
                    <h3 style="margin-bottom: var(--space-4);">Order Overview</h3>
                    <canvas id="orderChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3 style="margin-bottom: var(--space-4);">Product Stock Status</h3>
                    <canvas id="stockChart"></canvas>
                </div>
            </div>
        </main>
    </div>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../public/assets/js/admin-dashboard.js"></script>
</body>
</html>
