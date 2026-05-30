<?php
declare(strict_types=1);

/**
 * Admin Customers View + AJAX CRUD
 */

$activePage = 'customers';

function ac_project_root(): string
{
    return dirname(__DIR__, 2);
}

function ac_load_env(): array
{
    $envPath = ac_project_root() . DIRECTORY_SEPARATOR . '.env';
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

function ac_env(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = ac_load_env();
    }

    return $env[$key] ?? $default;
}

function ac_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = ac_env('DB_HOST', '127.0.0.1');
    $port = ac_env('DB_PORT', '3306');
    $name = ac_env('DB_NAME', 'ecommerce_security_platform');
    $user = ac_env('DB_USER', 'root');
    $pass = ac_env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function ac_app_base_path(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

function ac_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ac_read_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        ac_json([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function ac_current_admin_by_token(string $plainToken): ?array
{
    $tokenHash = hash('sha256', $plainToken);

    $stmt = ac_db()->prepare("
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

function ac_list_customers(): array
{
    $stmt = ac_db()->query("
        SELECT
            id,
            name,
            email,
            phone,
            role,
            is_active,
            created_at,
            updated_at
        FROM users
        ORDER BY id ASC
    ");

    return $stmt->fetchAll();
}

function ac_find_user_by_id(int $id): ?array
{
    $stmt = ac_db()->prepare("
        SELECT
            id,
            name,
            email,
            phone,
            role,
            is_active,
            created_at,
            updated_at
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id
    ]);

    $user = $stmt->fetch();

    return $user ?: null;
}

function ac_phone_exists(string $phone): bool
{
    $stmt = ac_db()->prepare("
        SELECT id
        FROM users
        WHERE phone = :phone
        LIMIT 1
    ");

    $stmt->execute([
        'phone' => $phone
    ]);

    return (bool)$stmt->fetch();
}

function ac_email_exists(string $email): bool
{
    if ($email === '') {
        return false;
    }

    $stmt = ac_db()->prepare("
        SELECT id
        FROM users
        WHERE email = :email
        LIMIT 1
    ");

    $stmt->execute([
        'email' => $email
    ]);

    return (bool)$stmt->fetch();
}

function ac_create_customer(array $input): array
{
    $name = trim((string)($input['name'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $role = trim((string)($input['role'] ?? 'customer'));
    $isActive = (int)($input['is_active'] ?? 1);

    if ($name === '') {
        ac_json([
            'success' => false,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'Name is required'
        ], 400);
    }

    if ($phone === '') {
        ac_json([
            'success' => false,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'Phone is required'
        ], 400);
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ac_json([
            'success' => false,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'Invalid email format'
        ], 400);
    }

    if (strlen($password) < 6) {
        ac_json([
            'success' => false,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'Password must be at least 6 characters'
        ], 400);
    }

    if (!in_array($role, ['customer', 'admin'], true)) {
        $role = 'customer';
    }

    if (!in_array($isActive, [0, 1], true)) {
        $isActive = 1;
    }

    if (ac_phone_exists($phone)) {
        ac_json([
            'success' => false,
            'error_code' => 'PHONE_EXISTS',
            'message' => 'Phone already exists'
        ], 409);
    }

    if (ac_email_exists($email)) {
        ac_json([
            'success' => false,
            'error_code' => 'EMAIL_EXISTS',
            'message' => 'Email already exists'
        ], 409);
    }

    $stmt = ac_db()->prepare("
        INSERT INTO users (
            name,
            email,
            phone,
            password_hash,
            role,
            is_active,
            created_at,
            updated_at
        ) VALUES (
            :name,
            :email,
            :phone,
            :password_hash,
            :role,
            :is_active,
            NOW(),
            NULL
        )
    ");

    $stmt->execute([
        'name' => $name,
        'email' => $email !== '' ? $email : null,
        'phone' => $phone,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'is_active' => $isActive
    ]);

    $id = (int)ac_db()->lastInsertId();

    return ac_find_user_by_id($id) ?? ['id' => $id];
}

function ac_deactivate_customer(int $id, int $adminId): array
{
    if ($id <= 0) {
        ac_json([
            'success' => false,
            'error_code' => 'INVALID_USER_ID',
            'message' => 'Invalid user id'
        ], 400);
    }

    if ($id === $adminId) {
        ac_json([
            'success' => false,
            'error_code' => 'CANNOT_DEACTIVATE_SELF',
            'message' => 'You cannot deactivate your own admin account'
        ], 403);
    }

    $user = ac_find_user_by_id($id);

    if (!$user) {
        ac_json([
            'success' => false,
            'error_code' => 'USER_NOT_FOUND',
            'message' => 'User not found'
        ], 404);
    }

    $stmt = ac_db()->prepare("
        UPDATE users
        SET is_active = 0,
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $id
    ]);

    return ac_find_user_by_id($id) ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['admin_customers_ajax'] ?? '') === '1') {
    try {
        $input = ac_read_json();

        $action = trim((string)($input['action'] ?? ''));
        $token = trim((string)($input['access_token'] ?? ''));

        if ($token === '') {
            ac_json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Access token is required'
            ], 401);
        }

        $admin = ac_current_admin_by_token($token);

        if (!$admin) {
            ac_json([
                'success' => false,
                'error_code' => 'ADMIN_REQUIRED',
                'message' => 'Admin authentication is required'
            ], 403);
        }

        if ($action === 'list') {
            ac_json([
                'success' => true,
                'message' => 'CUSTOMERS_LOADED',
                'data' => [
                    'customers' => ac_list_customers()
                ]
            ]);
        }

        if ($action === 'create') {
            ac_json([
                'success' => true,
                'message' => 'CUSTOMER_CREATED',
                'data' => [
                    'customer' => ac_create_customer($input)
                ]
            ], 201);
        }

        if ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);

            ac_json([
                'success' => true,
                'message' => 'CUSTOMER_DEACTIVATED',
                'data' => [
                    'customer' => ac_deactivate_customer($id, (int)$admin['id'])
                ]
            ]);
        }

        ac_json([
            'success' => false,
            'error_code' => 'INVALID_ACTION',
            'message' => 'Invalid customers action'
        ], 400);
    } catch (Throwable $e) {
        ac_json([
            'success' => false,
            'error_code' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ], 500);
    }
}

$appBasePath = ac_app_base_path();
$publicBasePath = $appBasePath . '/public';

$adminCustomersProxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?admin_customers_ajax=1';
$loginUrl = $appBasePath . '/views/auth/user-login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Admin Panel</title>

    <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/root.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/admin.css">

    <style>
        #customer-modal {
    display: none !important;
    pointer-events: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
}

#customer-modal.active,
#customer-modal.is-open {
    display: flex !important;
    pointer-events: auto !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.modal {
    position: fixed !important;
    inset: 0 !important;
    z-index: 999999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: #fff !important;
    overflow-y: auto !important;
}

.modal__content {
    width: min(900px, calc(100vw - 48px)) !important;
    max-width: 900px !important;
    margin: auto !important;
    padding: 48px 0 !important;
    background: #fff !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}

.modal__header {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    width: 100% !important;
    margin: 0 auto 32px auto !important;
}

#customer-form {
    width: 100% !important;
    max-width: 900px !important;
    margin: 0 auto !important;
}

.modal__close {
    border: 0;
    background: transparent;
    cursor: pointer;
    font-size: 28px;
    line-height: 1;
}

#btn-add-customer,
.btn-delete-customer,
#customer-form button {
    pointer-events: auto !important;
    cursor: pointer !important;
}
    </style>
</head>

<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/../layouts/admin-sidebar.php'; ?>

        <main class="admin-main">
            <header class="admin-header">
                <h1 style="font-family: var(--font-heading); font-size: var(--text-2xl);">Customers</h1>
                <button type="button" class="btn btn--primary" id="btn-add-customer">Add New Customer</button>
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
                        <tr>
                            <td colspan="8" style="text-align: center;">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div class="modal" id="customer-modal" hidden aria-hidden="true" style="display:none; pointer-events:none;">
        <div class="modal__content">
            <div class="modal__header">
                <h3 class="modal__title" id="modal-title">Add Customer</h3>
                <button type="button" class="modal__close" id="modal-close">&times;</button>
            </div>

            <form id="customer-form">
                <div class="form-group">
                    <label class="form-label" for="c-name">Name</label>
                    <input type="text" id="c-name" class="form-input" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label" for="c-phone">Phone</label>
                        <input type="text" id="c-phone" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="c-email">Email</label>
                        <input type="email" id="c-email" class="form-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="c-password">Password</label>
                    <input type="password" id="c-password" class="form-input" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label" for="c-role">Role</label>
                        <select id="c-role" class="form-input">
                            <option value="customer">Customer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="c-is_active">Status</label>
                        <select id="c-is_active" class="form-input">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: var(--space-2); margin-top: var(--space-6);">
                    <button type="button" class="btn btn--outline" id="modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn--primary" id="btn-save-customer">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.ADMIN_CUSTOMERS_CONFIG = {
            customersProxyUrl: <?= json_encode($adminCustomersProxyUrl) ?>,
            loginUrl: <?= json_encode($loginUrl) ?>
        };
    </script>

    <script src="<?= htmlspecialchars($publicBasePath) ?>/assets/js/admin-customers.js?v=<?= time() ?>" defer></script>
</body>
</html>