<?php
declare(strict_types=1);

$activePage = 'products';

function pd_project_root(): string
{
    return dirname(__DIR__, 2);
}

function pd_load_env(): array
{
    $envPath = pd_project_root() . DIRECTORY_SEPARATOR . '.env';
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

function pd_env(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = pd_load_env();
    }

    return $env[$key] ?? $default;
}

function pd_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = pd_env('DB_HOST', '127.0.0.1');
    $port = pd_env('DB_PORT', '3306');
    $name = pd_env('DB_NAME', 'ecommerce_security_platform');
    $user = pd_env('DB_USER', 'root');
    $pass = pd_env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function pd_app_base_path(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

function pd_json(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pd_read_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        pd_json([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function pd_has_column(string $table, string $column): bool
{
    $stmt = pd_db()->prepare("
        SHOW COLUMNS FROM {$table} LIKE :column_name
    ");

    $stmt->execute([
        'column_name' => $column
    ]);

    return (bool)$stmt->fetch();
}

function pd_find_product(int $productId): ?array
{
    $hasShortDescription = pd_has_column('products', 'short_description');
    $hasIngredient = pd_has_column('products', 'ingredient');
    $hasIngredients = pd_has_column('products', 'ingredients');

    $extraColumns = [];

    if ($hasShortDescription) {
        $extraColumns[] = 'short_description';
    }

    if ($hasIngredient) {
        $extraColumns[] = 'ingredient';
    }

    if ($hasIngredients) {
        $extraColumns[] = 'ingredients';
    }

    $extraSelect = $extraColumns ? ', ' . implode(', ', $extraColumns) : '';

    $stmt = pd_db()->prepare("
        SELECT
            id,
            name,
            description,
            price,
            stock,
            image,
            is_active,
            created_at,
            updated_at
            {$extraSelect}
        FROM products
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $productId
    ]);

    $product = $stmt->fetch();

    return $product ?: null;
}

function pd_current_user_by_token(string $plainToken): ?array
{
    $tokenHash = hash('sha256', $plainToken);

    $stmt = pd_db()->prepare("
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

    if (!$user || (int)$user['is_active'] !== 1) {
        return null;
    }

    return $user;
}

function pd_add_to_cart(int $userId, int $productId, int $quantity): array
{
    if ($quantity <= 0) {
        $quantity = 1;
    }

    $db = pd_db();

    $stmt = $db->prepare("
        SELECT id, name, price, stock, is_active
        FROM products
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'id' => $productId
    ]);

    $product = $stmt->fetch();

    if (!$product || (int)$product['is_active'] !== 1) {
        pd_json([
            'success' => false,
            'error_code' => 'PRODUCT_NOT_AVAILABLE',
            'message' => 'Product is not available'
        ], 404);
    }

    if ((int)$product['stock'] <= 0) {
        pd_json([
            'success' => false,
            'error_code' => 'OUT_OF_STOCK',
            'message' => 'Product is out of stock'
        ], 400);
    }

    $quantity = min($quantity, (int)$product['stock']);

    $db->beginTransaction();

    try {
        $stmt = $db->prepare("
            SELECT id
            FROM carts
            WHERE user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        $cart = $stmt->fetch();

        if ($cart) {
            $cartId = (int)$cart['id'];
        } else {
            $stmt = $db->prepare("
                INSERT INTO carts (user_id, created_at, updated_at)
                VALUES (:user_id, NOW(), NULL)
            ");

            $stmt->execute([
                'user_id' => $userId
            ]);

            $cartId = (int)$db->lastInsertId();
        }

        $stmt = $db->prepare("
            SELECT id, quantity
            FROM cart_items
            WHERE cart_id = :cart_id
              AND product_id = :product_id
            LIMIT 1
        ");

        $stmt->execute([
            'cart_id' => $cartId,
            'product_id' => $productId
        ]);

        $cartItem = $stmt->fetch();

        if ($cartItem) {
            $newQuantity = min((int)$cartItem['quantity'] + $quantity, (int)$product['stock']);

            $stmt = $db->prepare("
                UPDATE cart_items
                SET quantity = :quantity,
                    updated_at = NOW()
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                'quantity' => $newQuantity,
                'id' => (int)$cartItem['id']
            ]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO cart_items (
                    cart_id,
                    product_id,
                    quantity,
                    created_at,
                    updated_at
                ) VALUES (
                    :cart_id,
                    :product_id,
                    :quantity,
                    NOW(),
                    NULL
                )
            ");

            $stmt->execute([
                'cart_id' => $cartId,
                'product_id' => $productId,
                'quantity' => $quantity
            ]);
        }

        $stmt = $db->prepare("
            UPDATE carts
            SET updated_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $cartId
        ]);

        $db->commit();

        return [
            'cart_id' => $cartId,
            'product_id' => $productId,
            'quantity' => $quantity
        ];
    } catch (Throwable $e) {
        $db->rollBack();

        pd_json([
            'success' => false,
            'error_code' => 'CART_ADD_FAILED',
            'message' => 'Cannot add product to cart'
        ], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['product_detail_ajax'] ?? '') === '1') {
    try {
        $input = pd_read_json();

        $action = trim((string)($input['action'] ?? ''));

        if ($action === 'product') {
            $productId = (int)($input['product_id'] ?? 0);

            if ($productId <= 0) {
                pd_json([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid product id'
                ], 400);
            }

            $product = pd_find_product($productId);

            if (!$product) {
                pd_json([
                    'success' => false,
                    'error_code' => 'PRODUCT_NOT_FOUND',
                    'message' => 'Product not found'
                ], 404);
            }

            pd_json([
                'success' => true,
                'message' => 'PRODUCT_LOADED',
                'data' => $product
            ]);
        }

        if ($action === 'add_cart') {
            $token = trim((string)($input['access_token'] ?? ''));
            $productId = (int)($input['product_id'] ?? 0);
            $quantity = (int)($input['quantity'] ?? 1);

            if ($token === '') {
                pd_json([
                    'success' => false,
                    'error_code' => 'UNAUTHENTICATED',
                    'message' => 'Please login before adding products to cart'
                ], 401);
            }

            $user = pd_current_user_by_token($token);

            if (!$user) {
                pd_json([
                    'success' => false,
                    'error_code' => 'TOKEN_INVALID',
                    'message' => 'Your session has expired. Please login again'
                ], 401);
            }

            if ($productId <= 0) {
                pd_json([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid product id'
                ], 400);
            }

            $cartData = pd_add_to_cart((int)$user['id'], $productId, $quantity);

            pd_json([
                'success' => true,
                'message' => 'CART_ITEM_ADDED',
                'data' => $cartData
            ]);
        }

        pd_json([
            'success' => false,
            'error_code' => 'INVALID_ACTION',
            'message' => 'Invalid product detail action'
        ], 400);
    } catch (Throwable $e) {
        pd_json([
            'success' => false,
            'error_code' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ], 500);
    }
}

$appBasePath = pd_app_base_path();
$publicBasePath = $appBasePath . '/public';

$productDetailProxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?product_detail_ajax=1';

$loginUrl = $appBasePath . '/views/auth/user-login.php';
$productsUrl = $appBasePath . '/views/user/products.php';
$cartUrl = $appBasePath . '/views/user/cart.php';

$productImageBaseUrl = $publicBasePath . '/assets/img/product';
$defaultProductImageUrl = $publicBasePath . '/assets/img/product/default-product.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Product Detail - Bite Me Donut</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet"
  >

  <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/root.css">

  <style>
    .product-detail-page {
      background: var(--color-bg);
      min-height: 100vh;
    }

    .product-detail-section {
      max-width: var(--container-max, 1200px);
      margin: 0 auto;
      padding: 64px 24px 96px;
    }

    .product-detail-back {
      margin-bottom: 32px;
      font-weight: 900;
    }

    .product-detail-back a {
      color: var(--color-text-muted);
      text-decoration: none;
    }

    .product-detail-back a:hover {
      color: var(--color-primary);
    }

    .product-detail-grid {
      display: grid;
      grid-template-columns: minmax(300px, 0.95fr) minmax(300px, 1.05fr);
      gap: 40px;
      align-items: stretch;
    }

    .product-image-card {
      background: #fff;
      border: 1px solid var(--color-border);
      border-radius: 24px;
      padding: 32px;
      min-height: 520px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 18px 42px rgba(0,0,0,0.06);
    }

    .product-image-card img {
      width: 100%;
      max-height: 460px;
      object-fit: contain;
    }

    .product-info-panel {
      background: #fff;
      border: 1px solid var(--color-border);
      border-radius: 24px;
      padding: 36px;
      box-shadow: 0 18px 42px rgba(0,0,0,0.06);
    }

    .product-title {
      font-family: var(--font-heading);
      font-size: clamp(38px, 5vw, 62px);
      line-height: 1.05;
      margin: 18px 0 14px;
      color: var(--color-text);
    }

    .product-price {
      font-size: 28px;
      font-weight: 900;
      color: var(--color-primary);
      margin-bottom: 24px;
    }

    .product-description {
      color: var(--color-text-muted);
      font-size: 17px;
      line-height: 1.75;
      margin-bottom: 18px;
    }

    .product-ingredient {
      color: var(--color-text-muted);
      line-height: 1.7;
      margin-bottom: 24px;
    }

    .badge {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      padding: 8px 14px;
      font-size: 12px;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .badge--success {
      background: #e8f5e9;
      color: #1b5e20;
    }

    .badge--danger {
      background: #ffebee;
      color: #b71c1c;
    }

    .divider {
      border-top: 1px solid var(--color-border);
      margin: 28px 0;
    }

    .qty-row {
      display: flex;
      align-items: center;
      gap: 18px;
      flex-wrap: wrap;
    }

    .qty-selector {
      display: inline-flex;
      align-items: center;
      border: 1px solid var(--color-border);
      border-radius: 12px;
      overflow: hidden;
      background: var(--color-bg);
    }

    .qty-selector__btn {
      width: 44px;
      height: 44px;
      border: 0;
      background: transparent;
      cursor: pointer;
      font-size: 20px;
      font-weight: 900;
      color: var(--color-text);
    }

    .qty-selector__btn:hover:not(:disabled) {
      color: var(--color-primary);
      background: var(--color-primary-light);
    }

    .qty-selector__btn:disabled {
      opacity: 0.45;
      cursor: not-allowed;
    }

    .qty-selector__value {
      width: 56px;
      text-align: center;
      font-weight: 900;
      border-left: 1px solid var(--color-border);
      border-right: 1px solid var(--color-border);
      line-height: 44px;
      background: #fff;
    }

    .alert {
      padding: 16px 18px;
      border-radius: 12px;
      font-weight: 800;
      margin-bottom: 24px;
      border-left: 4px solid transparent;
    }

    .alert--success {
      background: #e8f5e9;
      border-left-color: #43a047;
      color: #1b5e20;
    }

    .alert--danger {
      background: #ffebee;
      border-left-color: #e53935;
      color: #b71c1c;
    }

    .empty-state {
      text-align: center;
      padding: 64px 24px;
      grid-column: 1 / -1;
    }

    .empty-state__title {
      font-family: var(--font-heading);
      font-size: 32px;
      margin: 0 0 12px;
    }

    .loading-overlay {
      display: flex;
      justify-content: center;
      padding: 80px 24px;
      grid-column: 1 / -1;
    }

    .spinner {
      width: 30px;
      height: 30px;
      border: 3px solid var(--color-border);
      border-top-color: var(--color-primary);
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    @media (max-width: 900px) {
      .product-detail-grid {
        grid-template-columns: 1fr;
      }

      .product-image-card {
        min-height: 360px;
      }
    }
  </style>
</head>

<body>
  <?php include __DIR__ . '/../layouts/header.php'; ?>

  <main class="product-detail-page">
    <section class="product-detail-section">
      <div class="product-detail-back">
        <a href="<?= htmlspecialchars($productsUrl) ?>">&larr; Back to Menu</a>
      </div>

      <div id="alert-container"></div>

      <div class="product-detail-grid" id="product-detail-container">
        <div class="loading-overlay">
          <div class="spinner"></div>
        </div>
      </div>
    </section>
  </main>

  <?php
  $footerPath = __DIR__ . '/../layouts/footer.php';

  if (is_file($footerPath)) {
      include $footerPath;
  }
  ?>

  <script>
    window.PRODUCT_DETAIL_CONFIG = {
      productDetailProxyUrl: <?= json_encode($productDetailProxyUrl) ?>,
      loginUrl: <?= json_encode($loginUrl) ?>,
      productsUrl: <?= json_encode($productsUrl) ?>,
      cartUrl: <?= json_encode($cartUrl) ?>,
      imageBaseUrl: <?= json_encode($productImageBaseUrl) ?>,
      defaultProductImageUrl: <?= json_encode($defaultProductImageUrl) ?>
    };
  </script>

  <script src="<?= htmlspecialchars($publicBasePath) ?>/assets/js/product-detail.js?v=<?= time() ?>" defer></script>
</body>
</html>