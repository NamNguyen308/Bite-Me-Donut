<?php
declare(strict_types=1);

$activePage = 'products';

function products_project_root(): string
{
    return dirname(__DIR__, 2);
}

function products_load_env(): array
{
    $envPath = products_project_root() . DIRECTORY_SEPARATOR . '.env';
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

function products_env(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = products_load_env();
    }

    return $env[$key] ?? $default;
}

function products_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = products_env('DB_HOST', '127.0.0.1');
    $port = products_env('DB_PORT', '3306');
    $name = products_env('DB_NAME', 'ecommerce_security_platform');
    $user = products_env('DB_USER', 'root');
    $pass = products_env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function products_app_base_path(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

function products_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function products_read_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        products_json([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function products_current_user_by_token(string $plainToken): ?array
{
    $tokenHash = hash('sha256', $plainToken);

    $stmt = products_db()->prepare("
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

function products_list(): array
{
    $stmt = products_db()->query("
        SELECT
            id,
            name,
            description,
            short_description,
            ingredients,
            price,
            stock,
            image,
            is_active,
            created_at,
            updated_at
        FROM products
        ORDER BY id ASC
    ");

    return $stmt->fetchAll();
}

function products_add_to_cart(int $userId, int $productId, int $quantity): array
{
    if ($quantity <= 0) {
        $quantity = 1;
    }

    $db = products_db();

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
        products_json([
            'success' => false,
            'error_code' => 'PRODUCT_NOT_AVAILABLE',
            'message' => 'Product is not available'
        ], 404);
    }

    if ((int)$product['stock'] <= 0) {
        products_json([
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

        products_json([
            'success' => false,
            'error_code' => 'CART_ADD_FAILED',
            'message' => 'Cannot add product to cart'
        ], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['products_ajax'] ?? '') === '1') {
    try {
        $input = products_read_json();

        $action = trim((string)($input['action'] ?? ''));

        if ($action === 'products') {
            products_json([
                'success' => true,
                'message' => 'PRODUCTS_LOADED',
                'data' => products_list()
            ]);
        }

        if ($action === 'add_cart') {
            $token = trim((string)($input['access_token'] ?? ''));
            $productId = (int)($input['product_id'] ?? 0);
            $quantity = (int)($input['quantity'] ?? 1);

            if ($token === '') {
                products_json([
                    'success' => false,
                    'error_code' => 'UNAUTHENTICATED',
                    'message' => 'Please login before adding products to cart'
                ], 401);
            }

            $user = products_current_user_by_token($token);

            if (!$user) {
                products_json([
                    'success' => false,
                    'error_code' => 'TOKEN_INVALID',
                    'message' => 'Your session has expired. Please login again'
                ], 401);
            }

            if ($productId <= 0) {
                products_json([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid product id'
                ], 400);
            }

            $cartData = products_add_to_cart((int)$user['id'], $productId, $quantity);

            products_json([
                'success' => true,
                'message' => 'CART_ITEM_ADDED',
                'data' => $cartData
            ]);
        }

        products_json([
            'success' => false,
            'error_code' => 'INVALID_ACTION',
            'message' => 'Invalid products action'
        ], 400);
    } catch (Throwable $e) {
        products_json([
            'success' => false,
            'error_code' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ], 500);
    }
}

$appBasePath = products_app_base_path();
$publicBasePath = $appBasePath . '/public';

$productsProxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?products_ajax=1';

$loginUrl = $appBasePath . '/views/auth/user-login.php';
$productsUrl = $appBasePath . '/views/user/products.php';
$productDetailUrl = $appBasePath . '/views/user/product-detail.php';
$cartUrl = $appBasePath . '/views/user/cart.php';

$productImageBaseUrl = $publicBasePath . '/assets/img/product';
$defaultProductImageUrl = $publicBasePath . '/assets/img/product/default-product.png';
?>

<!DOCTYPE html>
<html lang="en"> 
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Our Menu - Bite Me Donut</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet"
  >

  <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/root.css">

  <style>
    .menu-page {
      background: var(--color-bg);
      min-height: 100vh;
    }

    .menu-section {
      max-width: var(--container-max, 1200px);
      margin: 0 auto;
      padding: 72px 24px 96px;
    }

    .page-title {
      font-family: var(--font-heading);
      font-size: clamp(42px, 5vw, 68px);
      text-align: center;
      color: var(--color-text);
      margin: 0 0 16px;
    }

    .page-subtitle {
      text-align: center;
      color: var(--color-text-muted);
      font-size: 18px;
      line-height: 1.7;
      margin: 0 auto 42px;
      max-width: 680px;
    }

    .grid-cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 24px;
    }

    .product-card {
      display: flex;
      flex-direction: column;
      background: #fff;
      border: 1px solid var(--color-border);
      border-radius: 22px;
      overflow: hidden;
      box-shadow: 0 16px 34px rgba(0,0,0,0.05);
      transition: transform 160ms ease, box-shadow 160ms ease;
    }

    .product-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 22px 44px rgba(0,0,0,0.10);
    }

    .product-card__image-link {
      display: block;
      background: var(--color-primary-light);
    }

    .product-card__image {
      width: 100%;
      aspect-ratio: 1 / 1;
      object-fit: cover;
      display: block;
    }

    .product-card__body {
      padding: 20px;
      flex: 1;
    }

    .product-card__name {
      font-family: var(--font-heading);
      font-size: 22px;
      line-height: 1.2;
      color: var(--color-text);
      margin: 0 0 10px;
    }

    .product-card__description {
      color: var(--color-text-muted);
      font-size: 15px;
      line-height: 1.6;
      margin: 0;
    }

    .product-card__footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      padding: 16px 20px 20px;
      border-top: 1px solid var(--color-border);
    }

    .product-card__price {
      font-weight: 900;
      color: var(--color-primary);
      font-size: 18px;
    }

    .btn--add-cart {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background: var(--color-primary);
      color: #fff;
      border: none;
      font-size: 24px;
      line-height: 1;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: transform 160ms ease, background 160ms ease;
    }

    .btn--add-cart:hover:not(:disabled) {
      background: var(--color-primary-dark);
      transform: scale(1.08);
    }

    .btn--add-cart:disabled {
      background: var(--color-border);
      cursor: not-allowed;
    }

    .empty-state {
      text-align: center;
      padding: 64px 24px;
      grid-column: 1 / -1;
    }

    .empty-state__title {
      font-family: var(--font-heading);
      font-size: 32px;
      margin: 0 0 10px;
    }

    .empty-state__desc {
      color: var(--color-text-muted);
      margin: 0;
      line-height: 1.7;
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

    .alert--warning {
      background: #fff8e1;
      border-left-color: #ff9800;
      color: #8a5200;
    }

    .loading-overlay {
      display: flex;
      justify-content: center;
      padding: 64px 24px;
      grid-column: 1 / -1;
    }

    .spinner {
      width: 28px;
      height: 28px;
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
  </style>
</head>

<body>
  <?php include __DIR__ . '/../layouts/header.php'; ?>

  <main class="menu-page">
    <section class="menu-section">
      <h1 class="page-title">Our Sweet Menu</h1>
      <p class="page-subtitle">
        Pick your favorite donuts and add them to your cart. Freshly baked, sweetly packed.
      </p>

      <div id="alert-container"></div>

      <div id="products-grid" class="grid-cards">
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
    window.PRODUCTS_CONFIG = {
      productsProxyUrl: <?= json_encode($productsProxyUrl) ?>,
      loginUrl: <?= json_encode($loginUrl) ?>,
      productsUrl: <?= json_encode($productsUrl) ?>,
      productDetailUrl: <?= json_encode($productDetailUrl) ?>,
      cartUrl: <?= json_encode($cartUrl) ?>,
      imageBaseUrl: <?= json_encode($productImageBaseUrl) ?>,
      defaultProductImageUrl: <?= json_encode($defaultProductImageUrl) ?>
    };
  </script>

  <script src="<?= htmlspecialchars($publicBasePath) ?>/assets/js/products.js?v=<?= time() ?>" defer></script>
</body>
</html>