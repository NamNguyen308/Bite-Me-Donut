<?php
declare(strict_types=1);

/**
 * Admin Orders View + AJAX Status Update
 */

$activePage = 'orders';

function ao_project_root(): string
{
    return dirname(__DIR__, 2);
}

function ao_load_env(): array
{
    $envPath = ao_project_root() . DIRECTORY_SEPARATOR . '.env';
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

function ao_env(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = ao_load_env();
    }

    return $env[$key] ?? $default;
}

function ao_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = ao_env('DB_HOST', '127.0.0.1');
    $port = ao_env('DB_PORT', '3306');
    $name = ao_env('DB_NAME', 'ecommerce_security_platform');
    $user = ao_env('DB_USER', 'root');
    $pass = ao_env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function ao_app_base_path(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

function ao_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ao_read_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        ao_json([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function ao_current_admin_by_token(string $plainToken): ?array
{
    $tokenHash = hash('sha256', $plainToken);

    $stmt = ao_db()->prepare("
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

function ao_list_orders(): array
{
    $stmt = ao_db()->query("
        SELECT
            o.id,
            o.user_id,
            o.shipping_name,
            o.shipping_phone,
            o.shipping_address,
            o.payment_method,
            o.status,
            o.total,
            o.created_at,
            o.updated_at,
            u.name AS customer_name,
            u.email AS customer_email,
            u.phone AS customer_phone
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        ORDER BY o.id DESC
    ");

    return $stmt->fetchAll();
}

function ao_find_order(int $id): ?array
{
    $stmt = ao_db()->prepare("
        SELECT
            id,
            user_id,
            shipping_name,
            shipping_phone,
            shipping_address,
            payment_method,
            status,
            total,
            created_at,
            updated_at
        FROM orders
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id
    ]);

    $order = $stmt->fetch();

    return $order ?: null;
}

function ao_update_order_status(int $id, string $status): array
{
    if ($id <= 0) {
        ao_json([
            'success' => false,
            'error_code' => 'INVALID_ORDER_ID',
            'message' => 'Invalid order id'
        ], 400);
    }

    $status = strtoupper(trim($status));

    $allowedStatuses = [
        'PENDING',
        'PROCESSING',
        'SHIPPING',
        'COMPLETED',
        'CANCELLED'
    ];

    if (!in_array($status, $allowedStatuses, true)) {
        ao_json([
            'success' => false,
            'error_code' => 'INVALID_STATUS',
            'message' => 'Invalid order status'
        ], 400);
    }

    $order = ao_find_order($id);

    if (!$order) {
        ao_json([
            'success' => false,
            'error_code' => 'ORDER_NOT_FOUND',
            'message' => 'Order not found'
        ], 404);
    }

    $stmt = ao_db()->prepare("
        UPDATE orders
        SET status = :status,
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'status' => $status,
        'id' => $id
    ]);

    return ao_find_order($id) ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['admin_orders_ajax'] ?? '') === '1') {
    try {
        $input = ao_read_json();

        $action = trim((string)($input['action'] ?? ''));
        $token = trim((string)($input['access_token'] ?? ''));

        if ($token === '') {
            ao_json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Access token is required'
            ], 401);
        }

        $admin = ao_current_admin_by_token($token);

        if (!$admin) {
            ao_json([
                'success' => false,
                'error_code' => 'ADMIN_REQUIRED',
                'message' => 'Admin authentication is required'
            ], 403);
        }

        if ($action === 'list') {
            ao_json([
                'success' => true,
                'message' => 'ORDERS_LOADED',
                'data' => [
                    'orders' => ao_list_orders()
                ]
            ]);
        }

        if ($action === 'update_status') {
            $id = (int)($input['id'] ?? 0);
            $status = (string)($input['status'] ?? '');

            ao_json([
                'success' => true,
                'message' => 'ORDER_STATUS_UPDATED',
                'data' => [
                    'order' => ao_update_order_status($id, $status)
                ]
            ]);
        }

        ao_json([
            'success' => false,
            'error_code' => 'INVALID_ACTION',
            'message' => 'Invalid orders action'
        ], 400);
    } catch (Throwable $e) {
        ao_json([
            'success' => false,
            'error_code' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ], 500);
    }
}

$appBasePath = ao_app_base_path();
$publicBasePath = $appBasePath . '/public';

$adminOrdersProxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?admin_orders_ajax=1';
$loginUrl = $appBasePath . '/views/auth/user-login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Admin Panel</title>

    <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/root.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/admin.css">

    <style>
        #status-modal.order-modal-overlay {
            display: none !important;
            pointer-events: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }

        #status-modal.order-modal-overlay.active,
        #status-modal.order-modal-overlay.is-open {
            position: fixed !important;
            inset: 0 !important;
            z-index: 999999 !important;

            display: flex !important;
            align-items: center !important;
            justify-content: center !important;

            width: 100vw !important;
            height: 100vh !important;

            background: rgba(0, 0, 0, 0.35) !important;
            pointer-events: auto !important;
            visibility: visible !important;
            opacity: 1 !important;

            overflow-y: auto !important;
            padding: 32px 20px !important;
            box-sizing: border-box !important;
        }

        .order-modal-panel {
            width: min(460px, 100%) !important;
            max-width: 460px !important;
            background: #fff !important;
            border-radius: 18px !important;
            padding: 28px 32px !important;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.22) !important;
            box-sizing: border-box !important;
            position: relative !important;
        }

        .order-modal-header {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 24px !important;
            margin-bottom: 20px !important;
        }

        .order-modal-title {
            margin: 0 !important;
            font-family: var(--font-heading) !important;
            font-size: 28px !important;
            color: var(--color-text) !important;
        }

        .order-modal-close {
            border: 0 !important;
            background: transparent !important;
            cursor: pointer !important;
            font-size: 32px !important;
            line-height: 1 !important;
            color: var(--color-text-muted) !important;
        }

        #status-form {
            width: 100% !important;
            margin: 0 auto !important;
        }

        .btn-update-order,
        #status-form button {
            pointer-events: auto !important;
            cursor: pointer !important;
        }

        .order-items-small {
            color: var(--color-text-muted);
            font-size: 12px;
            margin-top: 4px;
            line-height: 1.4;
        }
    </style>
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
                        <tr>
                            <td colspan="8" style="text-align:center;">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div
        id="status-modal"
        class="order-modal-overlay"
        hidden
        aria-hidden="true"
        style="display:none !important; pointer-events:none !important; visibility:hidden !important; opacity:0 !important;"
    >
        <div class="order-modal-panel">
            <div class="order-modal-header">
                <h3 class="order-modal-title">Update Status</h3>
                <button type="button" class="order-modal-close" id="modal-close">&times;</button>
            </div>

            <form id="status-form">
                <input type="hidden" id="order-id">

                <div class="form-group">
                    <label class="form-label" for="o-status">New Status</label>
                    <select id="o-status" class="form-input">
                        <option value="PENDING">Pending</option>
                        <option value="PROCESSING">Processing</option>
                        <option value="SHIPPING">Shipping</option>
                        <option value="COMPLETED">Completed</option>
                        <option value="CANCELLED">Cancelled</option>
                    </select>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:var(--space-2); margin-top:var(--space-6);">
                    <button type="button" class="btn btn--outline" id="modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn--primary" id="btn-save-status">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.ADMIN_ORDERS_CONFIG = {
            ordersProxyUrl: <?= json_encode($adminOrdersProxyUrl) ?>,
            loginUrl: <?= json_encode($loginUrl) ?>
        };
    </script>

    <script src="<?= htmlspecialchars($publicBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/admin-orders.js?v=<?= time() ?>" defer></script>
</body>
</html>