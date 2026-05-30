<?php
declare(strict_types=1);

/**
 * Admin Products View + AJAX CRUD
 */

$activePage = 'products';

function ap_project_root(): string
{
    return dirname(__DIR__, 2);
}

function ap_load_env(): array
{
    $envPath = ap_project_root() . DIRECTORY_SEPARATOR . '.env';
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

function ap_env(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = ap_load_env();
    }

    return $env[$key] ?? $default;
}

function ap_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = ap_env('DB_HOST', '127.0.0.1');
    $port = ap_env('DB_PORT', '3306');
    $name = ap_env('DB_NAME', 'ecommerce_security_platform');
    $user = ap_env('DB_USER', 'root');
    $pass = ap_env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function ap_app_base_path(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

function ap_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ap_read_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        ap_json([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function ap_has_column(string $table, string $column): bool
{
    $stmt = ap_db()->prepare("SHOW COLUMNS FROM {$table} LIKE :column_name");
    $stmt->execute(['column_name' => $column]);

    return (bool)$stmt->fetch();
}

function ap_current_admin_by_token(string $plainToken): ?array
{
    $tokenHash = hash('sha256', $plainToken);

    $stmt = ap_db()->prepare("
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

    $stmt->execute(['token_hash' => $tokenHash]);
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

function ap_product_columns(): array
{
    $columns = [
        'id',
        'name',
        'description',
        'price',
        'stock',
        'image',
        'is_active',
        'created_at',
        'updated_at'
    ];

    if (ap_has_column('products', 'short_description')) {
        $columns[] = 'short_description';
    }

    if (ap_has_column('products', 'ingredient')) {
        $columns[] = 'ingredient';
    }

    if (ap_has_column('products', 'ingredients')) {
        $columns[] = 'ingredients';
    }

    return $columns;
}

function ap_list_products(): array
{
    $columns = implode(', ', ap_product_columns());

    $stmt = ap_db()->query("
        SELECT {$columns}
        FROM products
        ORDER BY id ASC
    ");

    return $stmt->fetchAll();
}

function ap_find_product(int $id): ?array
{
    $columns = implode(', ', ap_product_columns());

    $stmt = ap_db()->prepare("
        SELECT {$columns}
        FROM products
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute(['id' => $id]);

    $product = $stmt->fetch();

    return $product ?: null;
}

function ap_build_product_payload(array $input): array
{
    $name = trim((string)($input['name'] ?? ''));
    $price = (float)($input['price'] ?? 0);
    $stock = (int)($input['stock'] ?? 0);

    if ($name === '') {
        ap_json([
            'success' => false,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'Product name is required'
        ], 400);
    }

    if ($price < 0) {
        ap_json([
            'success' => false,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'Price must be greater than or equal to 0'
        ], 400);
    }

    if ($stock < 0) {
        ap_json([
            'success' => false,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'Stock must be greater than or equal to 0'
        ], 400);
    }

    $payload = [
        'name' => $name,
        'description' => trim((string)($input['description'] ?? '')),
        'price' => $price,
        'stock' => $stock,
        'image' => trim((string)($input['image'] ?? '')),
        'is_active' => (int)($input['is_active'] ?? 1),
    ];

    if (ap_has_column('products', 'short_description')) {
        $payload['short_description'] = trim((string)($input['short_description'] ?? $input['short_desc'] ?? ''));
    }

    if (ap_has_column('products', 'ingredient')) {
        $payload['ingredient'] = trim((string)($input['ingredient'] ?? $input['ingredients'] ?? ''));
    }

    if (ap_has_column('products', 'ingredients')) {
        $payload['ingredients'] = trim((string)($input['ingredients'] ?? $input['ingredient'] ?? ''));
    }

    return $payload;
}

function ap_create_product(array $input): array
{
    $payload = ap_build_product_payload($input);

    $columns = array_keys($payload);
    $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);

    $sql = "
        INSERT INTO products (
            " . implode(', ', $columns) . ",
            created_at,
            updated_at
        ) VALUES (
            " . implode(', ', $placeholders) . ",
            NOW(),
            NULL
        )
    ";

    $stmt = ap_db()->prepare($sql);
    $stmt->execute($payload);

    $id = (int)ap_db()->lastInsertId();

    return ap_find_product($id) ?? ['id' => $id];
}

function ap_update_product(int $id, array $input): array
{
    if ($id <= 0) {
        ap_json([
            'success' => false,
            'error_code' => 'INVALID_PRODUCT_ID',
            'message' => 'Invalid product id'
        ], 400);
    }

    if (!ap_find_product($id)) {
        ap_json([
            'success' => false,
            'error_code' => 'PRODUCT_NOT_FOUND',
            'message' => 'Product not found'
        ], 404);
    }

    $payload = ap_build_product_payload($input);
    $payload['id'] = $id;

    $sets = [];

    foreach (array_keys($payload) as $column) {
        if ($column !== 'id') {
            $sets[] = "{$column} = :{$column}";
        }
    }

    $sql = "
        UPDATE products
        SET " . implode(', ', $sets) . ",
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ";

    $stmt = ap_db()->prepare($sql);
    $stmt->execute($payload);

    return ap_find_product($id) ?? [];
}

function ap_deactivate_product(int $id): array
{
    if ($id <= 0) {
        ap_json([
            'success' => false,
            'error_code' => 'INVALID_PRODUCT_ID',
            'message' => 'Invalid product id'
        ], 400);
    }

    if (!ap_find_product($id)) {
        ap_json([
            'success' => false,
            'error_code' => 'PRODUCT_NOT_FOUND',
            'message' => 'Product not found'
        ], 404);
    }

    $stmt = ap_db()->prepare("
        UPDATE products
        SET is_active = 0,
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute(['id' => $id]);

    return ap_find_product($id) ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['admin_products_ajax'] ?? '') === '1') {
    try {
        $input = ap_read_json();

        $action = trim((string)($input['action'] ?? ''));
        $token = trim((string)($input['access_token'] ?? ''));

        if ($token === '') {
            ap_json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Access token is required'
            ], 401);
        }

        $admin = ap_current_admin_by_token($token);

        if (!$admin) {
            ap_json([
                'success' => false,
                'error_code' => 'ADMIN_REQUIRED',
                'message' => 'Admin authentication is required'
            ], 403);
        }

        if ($action === 'list') {
            ap_json([
                'success' => true,
                'message' => 'PRODUCTS_LOADED',
                'data' => [
                    'products' => ap_list_products()
                ]
            ]);
        }

        if ($action === 'create') {
            ap_json([
                'success' => true,
                'message' => 'PRODUCT_CREATED',
                'data' => [
                    'product' => ap_create_product($input)
                ]
            ], 201);
        }

        if ($action === 'update') {
            $id = (int)($input['id'] ?? 0);

            ap_json([
                'success' => true,
                'message' => 'PRODUCT_UPDATED',
                'data' => [
                    'product' => ap_update_product($id, $input)
                ]
            ]);
        }

        if ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);

            ap_json([
                'success' => true,
                'message' => 'PRODUCT_DEACTIVATED',
                'data' => [
                    'product' => ap_deactivate_product($id)
                ]
            ]);
        }

        ap_json([
            'success' => false,
            'error_code' => 'INVALID_ACTION',
            'message' => 'Invalid products action'
        ], 400);
    } catch (Throwable $e) {
        ap_json([
            'success' => false,
            'error_code' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ], 500);
    }
}

$appBasePath = ap_app_base_path();
$publicBasePath = $appBasePath . '/public';

$adminProductsProxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?admin_products_ajax=1';
$loginUrl = $appBasePath . '/views/auth/user-login.php';
$productImageBaseUrl = $publicBasePath . '/assets/img/product';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Admin Panel</title>

    <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/root.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/css/admin.css">

    <style>
        #product-modal.product-modal-overlay {
            display: none !important;
            pointer-events: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }

        #product-modal.product-modal-overlay.active,
        #product-modal.product-modal-overlay.is-open {
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

        .product-modal-panel {
            width: min(780px, 100%) !important;
            max-width: 780px !important;
            max-height: calc(100vh - 64px) !important;
            overflow-y: auto !important;

            background: #fff !important;
            border-radius: 18px !important;
            padding: 28px 32px !important;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.22) !important;

            box-sizing: border-box !important;
            position: relative !important;
        }

        .product-modal-header {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 24px !important;
            margin-bottom: 20px !important;
        }

        .product-modal-title {
            margin: 0 !important;
            font-family: var(--font-heading) !important;
            font-size: 30px !important;
            color: var(--color-text) !important;
        }

        .product-modal-close {
            border: 0 !important;
            background: transparent !important;
            cursor: pointer !important;
            font-size: 32px !important;
            line-height: 1 !important;
            color: var(--color-text-muted) !important;
        }

        #product-form {
            width: 100% !important;
            margin: 0 auto !important;
        }

        #btn-add-product,
        .btn-edit-product,
        .btn-delete-product,
        #product-form button {
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
                <h1 style="font-family: var(--font-heading); font-size: var(--text-2xl);">Products</h1>
                <button type="button" class="btn btn--primary" id="btn-add-product">Add New Product</button>
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
                        <tr>
                            <td colspan="7" style="text-align:center;">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div
        id="product-modal"
        class="product-modal-overlay"
        hidden
        aria-hidden="true"
        style="display:none !important; pointer-events:none !important; visibility:hidden !important; opacity:0 !important;"
    >
        <div class="product-modal-panel">
            <div class="product-modal-header">
                <h3 class="product-modal-title" id="modal-title">Add Product</h3>
                <button type="button" class="product-modal-close" id="modal-close">&times;</button>
            </div>

            <form id="product-form">
                <input type="hidden" id="product-id">

                <div class="form-group">
                    <label class="form-label" for="p-name">Name</label>
                    <input type="text" id="p-name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="p-short_desc">Short Description</label>
                    <input type="text" id="p-short_desc" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label" for="p-desc">Description</label>
                    <textarea id="p-desc" class="form-input" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="p-ingredient">Ingredient</label>
                    <input type="text" id="p-ingredient" class="form-input">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4);">
                    <div class="form-group">
                        <label class="form-label" for="p-price">Price</label>
                        <input type="number" step="0.01" id="p-price" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="p-stock">Stock</label>
                        <input type="number" id="p-stock" class="form-input" value="0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="p-image">Image file name</label>
                    <input type="text" id="p-image" class="form-input" placeholder="matcha-cloud.jpg">
                </div>

                <div class="form-group">
                    <label class="form-label" for="p-is_active">Status</label>
                    <select id="p-is_active" class="form-input">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:var(--space-2); margin-top:var(--space-6);">
                    <button type="button" class="btn btn--outline" id="modal-cancel">Cancel</button>
                    <button type="submit" class="btn btn--primary" id="btn-save-product">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.ADMIN_PRODUCTS_CONFIG = {
            productsProxyUrl: <?= json_encode($adminProductsProxyUrl) ?>,
            loginUrl: <?= json_encode($loginUrl) ?>,
            imageBaseUrl: <?= json_encode($productImageBaseUrl) ?>
        };
    </script>

    <script src="<?= htmlspecialchars($publicBasePath, ENT_QUOTES, 'UTF-8') ?>/assets/js/admin-products.js?v=<?= time() ?>" defer></script>
</body>
</html>