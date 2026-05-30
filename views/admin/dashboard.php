<?php
declare(strict_types=1);

$activePage = 'admin-dashboard';

function ad_project_root(): string
{
    return dirname(__DIR__, 2);
}

function ad_load_env(): array
{
    $envPath = ad_project_root() . DIRECTORY_SEPARATOR . '.env';
    $env = [];

    if (!is_file($envPath)) {
        return $env;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim(trim($value), "\"'");
    }

    return $env;
}

function ad_env(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = ad_load_env();
    }

    return $env[$key] ?? $default;
}

function ad_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = ad_env('DB_HOST', '127.0.0.1');
    $port = ad_env('DB_PORT', '3306');
    $name = ad_env('DB_NAME', 'ecommerce_security_platform');
    $user = ad_env('DB_USER', 'root');
    $pass = ad_env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function ad_app_base_path(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

function ad_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ad_read_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        ad_json([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function ad_current_admin_by_token(string $plainToken): ?array
{
    $tokenHash = hash('sha256', $plainToken);

    $stmt = ad_db()->prepare("
        SELECT
            u.id,
            u.name,
            u.email,
            u.phone,
            u.role,
            u.is_active
        FROM tokens t
        INNER JOIN users u ON u.id = t.user_id
        WHERE t.token_hash = :token_hash
          AND t.revoked_at IS NULL
          AND t.expires_at > NOW()
        LIMIT 1
    ");

    $stmt->execute([
        'token_hash' => $tokenHash
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        return null;
    }

    if ((int)$user['is_active'] !== 1) {
        return null;
    }

    if (($user['role'] ?? '') !== 'admin') {
        return null;
    }

    return $user;
}

function ad_scalar(string $sql): float
{
    $stmt = ad_db()->query($sql);
    $value = $stmt->fetchColumn();

    return $value !== false ? (float)$value : 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['dashboard_ajax'] ?? '') === '1') {
    try {
        $input = ad_read_json();

        $action = trim((string)($input['action'] ?? ''));
        $token = trim((string)($input['access_token'] ?? ''));

        if ($token === '') {
            ad_json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Access token is required'
            ], 401);
        }

        $admin = ad_current_admin_by_token($token);

        if (!$admin) {
            ad_json([
                'success' => false,
                'error_code' => 'ADMIN_REQUIRED',
                'message' => 'Admin authentication is required'
            ], 403);
        }

        if ($action === 'dashboard') {
            $totalUsers = ad_scalar("SELECT COUNT(*) FROM users");
            $totalProducts = ad_scalar("SELECT COUNT(*) FROM products");
            $totalOrders = ad_scalar("SELECT COUNT(*) FROM orders");
            $totalRevenue = ad_scalar("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status <> 'CANCELLED'");
            $riskEventsToday = ad_scalar("SELECT COUNT(*) FROM risk_logs WHERE DATE(created_at) = CURDATE()");

            ad_json([
                'success' => true,
                'message' => 'DASHBOARD_LOADED',
                'data' => [
                    'dashboard' => [
                        'total_users' => (int)$totalUsers,
                        'total_products' => (int)$totalProducts,
                        'total_orders' => (int)$totalOrders,
                        'total_revenue' => $totalRevenue,
                        'risk_events_today' => (int)$riskEventsToday,
                    ],
                    'admin' => $admin
                ]
            ]);
        }

        ad_json([
            'success' => false,
            'error_code' => 'INVALID_ACTION',
            'message' => 'Invalid dashboard action'
        ], 400);
    } catch (Throwable $e) {
        ad_json([
            'success' => false,
            'error_code' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ], 500);
    }
}

$appBasePath = ad_app_base_path();
$publicBasePath = $appBasePath . '/public';

$dashboardProxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?dashboard_ajax=1';
$loginUrl = $appBasePath . '/views/auth/user-login.php';
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

        <script>
  window.ADMIN_DASHBOARD_CONFIG = {
    dashboardProxyUrl: <?= json_encode($dashboardProxyUrl) ?>,
    loginUrl: <?= json_encode($loginUrl) ?>
  };
</script>

<script src="<?= htmlspecialchars($publicBasePath) ?>/assets/js/admin-dashboard.js?v=<?= time() ?>" defer></script>
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>
