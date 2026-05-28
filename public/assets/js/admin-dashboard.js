document.addEventListener('DOMContentLoaded', async () => {
    // Check auth
    const token = localStorage.getItem('bmd_access_token');
    if (!token) {
        window.location.href = '../../views/admin/admin-login.php';
        return;
    }

    const userStr = localStorage.getItem('bmd_user');
    if (userStr) {
        const user = JSON.parse(userStr);
        document.getElementById('admin-user-info').textContent = `Hello, ${user.name}`;
    }

    // Fetch dashboard data
    try {
        const response = await fetch('/Bite-Me-Donut/public/api/admin/dashboard', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        if (response.status === 401 || response.status === 403) {
            localStorage.removeItem('bmd_access_token');
            window.location.href = '../../views/admin/admin-login.php';
            return;
        }

        const data = await response.json();
        
        if (data.success) {
            const d = data.data.dashboard;
            document.getElementById('kpi-orders').textContent = d.total_orders;
            document.getElementById('kpi-revenue').textContent = '$' + Number(d.total_revenue).toFixed(2);
            document.getElementById('kpi-products').textContent = d.total_products;
            document.getElementById('kpi-customers').textContent = d.total_users;
        }
    } catch (err) {
        console.error('Failed to load dashboard data', err);
    }

    // Render Dummy Charts for visual representation
    renderCharts();
});

function renderCharts() {
    // Order Chart
    const ctxOrder = document.getElementById('orderChart').getContext('2d');
    new Chart(ctxOrder, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Processing', 'Completed', 'Cancelled'],
            datasets: [{
                data: [12, 19, 30, 5],
                backgroundColor: ['#ffb74d', '#4fc3f7', '#81c784', '#e57373']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // Stock Chart
    const ctxStock = document.getElementById('stockChart').getContext('2d');
    new Chart(ctxStock, {
        type: 'bar',
        data: {
            labels: ['Classic Glazed', 'Chocolate', 'Strawberry', 'Matcha', 'Vanilla'],
            datasets: [{
                label: 'Stock Quantity',
                data: [45, 30, 25, 10, 50],
                backgroundColor: '#f48fb1' // Primary color
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}
