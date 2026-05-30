<?php
declare(strict_types=1);

$activePage = 'cart';

function cart_project_root(): string
{
    return dirname(__DIR__, 2);
}

function cart_load_env(): array
{
    $envPath = cart_project_root() . DIRECTORY_SEPARATOR . '.env';
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

function cart_env(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = cart_load_env();
    }

    return $env[$key] ?? $default;
}

function cart_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = cart_env('DB_HOST', '127.0.0.1');
    $port = cart_env('DB_PORT', '3306');
    $name = cart_env('DB_NAME', 'ecommerce_security_platform');
    $user = cart_env('DB_USER', 'root');
    $pass = cart_env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function cart_app_base_path(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

function cart_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cart_read_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        cart_json([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function cart_current_user_by_token(string $plainToken): ?array
{
    $tokenHash = hash('sha256', $plainToken);

    $stmt = cart_db()->prepare("
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

function cart_get_or_create_cart_id(int $userId): int
{
    $db = cart_db();

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
        return (int)$cart['id'];
    }

    $stmt = $db->prepare("
        INSERT INTO carts (user_id, created_at, updated_at)
        VALUES (:user_id, NOW(), NULL)
    ");

    $stmt->execute([
        'user_id' => $userId
    ]);

    return (int)$db->lastInsertId();
}

function cart_items_for_user(int $userId): array
{
    $stmt = cart_db()->prepare("
        SELECT
            ci.id AS cart_item_id,
            ci.cart_id,
            ci.product_id,
            ci.quantity,
            p.name AS product_name,
            p.description,
            p.price,
            p.stock,
            p.image,
            p.is_active,
            (p.price * ci.quantity) AS subtotal
        FROM carts c
        INNER JOIN cart_items ci ON ci.cart_id = c.id
        INNER JOIN products p ON p.id = ci.product_id
        WHERE c.user_id = :user_id
        ORDER BY ci.id ASC
    ");

    $stmt->execute([
        'user_id' => $userId
    ]);

    return $stmt->fetchAll();
}

function cart_build_payload(int $userId): array
{
    $items = cart_items_for_user($userId);

    $total = 0;

    foreach ($items as $item) {
        $total += (float)$item['subtotal'];
    }

    return [
        'items' => $items,
        'total' => $total,
        'count' => count($items)
    ];
}

function cart_update_quantity(int $userId, int $cartItemId, int $quantity): array
{
    if ($quantity <= 0) {
        $quantity = 1;
    }

    $db = cart_db();

    $stmt = $db->prepare("
        SELECT
            ci.id,
            ci.product_id,
            p.stock
        FROM cart_items ci
        INNER JOIN carts c ON c.id = ci.cart_id
        INNER JOIN products p ON p.id = ci.product_id
        WHERE ci.id = :cart_item_id
          AND c.user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        'cart_item_id' => $cartItemId,
        'user_id' => $userId
    ]);

    $item = $stmt->fetch();

    if (!$item) {
        cart_json([
            'success' => false,
            'error_code' => 'CART_ITEM_NOT_FOUND',
            'message' => 'Cart item not found'
        ], 404);
    }

    $quantity = min($quantity, max(1, (int)$item['stock']));

    $stmt = $db->prepare("
        UPDATE cart_items
        SET quantity = :quantity,
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'quantity' => $quantity,
        'id' => $cartItemId
    ]);

    return cart_build_payload($userId);
}

function cart_remove_item(int $userId, int $cartItemId): array
{
    $stmt = cart_db()->prepare("
        DELETE ci
        FROM cart_items ci
        INNER JOIN carts c ON c.id = ci.cart_id
        WHERE ci.id = :cart_item_id
          AND c.user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        'cart_item_id' => $cartItemId,
        'user_id' => $userId
    ]);

    return cart_build_payload($userId);
}

function cart_clear(int $userId): array
{
    $stmt = cart_db()->prepare("
        DELETE ci
        FROM cart_items ci
        INNER JOIN carts c ON c.id = ci.cart_id
        WHERE c.user_id = :user_id
    ");

    $stmt->execute([
        'user_id' => $userId
    ]);

    return cart_build_payload($userId);
}

function cart_checkout(int $userId, array $input): array
{
    $shippingName = trim((string)($input['shipping_name'] ?? ''));
    $shippingPhone = trim((string)($input['shipping_phone'] ?? ''));
    $shippingAddress = trim((string)($input['shipping_address'] ?? ''));
    $shippingCity = trim((string)($input['shipping_city'] ?? ''));
    $shippingDistrict = trim((string)($input['shipping_district'] ?? ''));

    if ($shippingName === '' || $shippingPhone === '' || $shippingAddress === '' || $shippingCity === '') {
        cart_json([
            'success' => false,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'Shipping name, phone, address and city are required'
        ], 400);
    }

    $fullAddress = $shippingAddress;

    if ($shippingDistrict !== '') {
        $fullAddress .= ', ' . $shippingDistrict;
    }

    if ($shippingCity !== '') {
        $fullAddress .= ', ' . $shippingCity;
    }

    $db = cart_db();

    $items = cart_items_for_user($userId);

    if (!$items) {
        cart_json([
            'success' => false,
            'error_code' => 'CART_EMPTY',
            'message' => 'Cart is empty'
        ], 400);
    }

    $total = 0;

    foreach ($items as $item) {
        if ((int)$item['is_active'] !== 1) {
            cart_json([
                'success' => false,
                'error_code' => 'PRODUCT_NOT_AVAILABLE',
                'message' => $item['product_name'] . ' is not available'
            ], 400);
        }

        if ((int)$item['quantity'] > (int)$item['stock']) {
            cart_json([
                'success' => false,
                'error_code' => 'OUT_OF_STOCK',
                'message' => $item['product_name'] . ' does not have enough stock'
            ], 400);
        }

        $total += (float)$item['subtotal'];
    }

    $db->beginTransaction();

    try {
        $stmt = $db->prepare("
            INSERT INTO orders (
                user_id,
                shipping_name,
                shipping_phone,
                shipping_address,
                payment_method,
                status,
                total,
                created_at,
                updated_at
            ) VALUES (
                :user_id,
                :shipping_name,
                :shipping_phone,
                :shipping_address,
                'COD',
                'PENDING',
                :total,
                NOW(),
                NULL
            )
        ");

        $stmt->execute([
            'user_id' => $userId,
            'shipping_name' => $shippingName,
            'shipping_phone' => $shippingPhone,
            'shipping_address' => $fullAddress,
            'total' => $total
        ]);

        $orderId = (int)$db->lastInsertId();

        foreach ($items as $item) {
            $stmt = $db->prepare("
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    product_name,
                    price,
                    quantity,
                    subtotal,
                    created_at
                ) VALUES (
                    :order_id,
                    :product_id,
                    :product_name,
                    :price,
                    :quantity,
                    :subtotal,
                    NOW()
                )
            ");

            $stmt->execute([
                'order_id' => $orderId,
                'product_id' => (int)$item['product_id'],
                'product_name' => $item['product_name'],
                'price' => (float)$item['price'],
                'quantity' => (int)$item['quantity'],
                'subtotal' => (float)$item['subtotal']
            ]);

            $stmt = $db->prepare("
                UPDATE products
                SET stock = stock - :quantity,
                    updated_at = NOW()
                WHERE id = :product_id
                LIMIT 1
            ");

            $stmt->execute([
                'quantity' => (int)$item['quantity'],
                'product_id' => (int)$item['product_id']
            ]);
        }

        cart_clear($userId);

        $db->commit();

        return [
            'order' => [
                'id' => $orderId,
                'total' => $total,
                'status' => 'PENDING'
            ]
        ];
    } catch (Throwable $e) {
        $db->rollBack();

        cart_json([
            'success' => false,
            'error_code' => 'ORDER_CREATE_FAILED',
            'message' => 'Cannot create order'
        ], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['cart_ajax'] ?? '') === '1') {
    try {
        $input = cart_read_json();

        $action = trim((string)($input['action'] ?? ''));
        $token = trim((string)($input['access_token'] ?? ''));

        if ($token === '') {
            cart_json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Access token is required'
            ], 401);
        }

        $user = cart_current_user_by_token($token);

        if (!$user) {
            cart_json([
                'success' => false,
                'error_code' => 'TOKEN_INVALID',
                'message' => 'Your session has expired. Please login again'
            ], 401);
        }

        $userId = (int)$user['id'];

        if ($action === 'cart') {
            cart_get_or_create_cart_id($userId);

            cart_json([
                'success' => true,
                'message' => 'CART_LOADED',
                'data' => cart_build_payload($userId)
            ]);
        }

        if ($action === 'update') {
            $cartItemId = (int)($input['cart_item_id'] ?? 0);
            $quantity = (int)($input['quantity'] ?? 1);

            if ($cartItemId <= 0) {
                cart_json([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid cart item id'
                ], 400);
            }

            cart_json([
                'success' => true,
                'message' => 'CART_UPDATED',
                'data' => cart_update_quantity($userId, $cartItemId, $quantity)
            ]);
        }

        if ($action === 'remove') {
            $cartItemId = (int)($input['cart_item_id'] ?? 0);

            if ($cartItemId <= 0) {
                cart_json([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid cart item id'
                ], 400);
            }

            cart_json([
                'success' => true,
                'message' => 'CART_ITEM_REMOVED',
                'data' => cart_remove_item($userId, $cartItemId)
            ]);
        }

        if ($action === 'clear') {
            cart_json([
                'success' => true,
                'message' => 'CART_CLEARED',
                'data' => cart_clear($userId)
            ]);
        }

        if ($action === 'checkout') {
            cart_json([
                'success' => true,
                'message' => 'ORDER_CREATED',
                'data' => cart_checkout($userId, $input)
            ]);
        }

        cart_json([
            'success' => false,
            'error_code' => 'INVALID_ACTION',
            'message' => 'Invalid cart action'
        ], 400);
    } catch (Throwable $e) {
        cart_json([
            'success' => false,
            'error_code' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ], 500);
    }
}

$appBasePath = cart_app_base_path();
$publicBasePath = $appBasePath . '/public';

$cartProxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?cart_ajax=1';

$loginUrl = $appBasePath . '/views/auth/user-login.php';
$productsUrl = $appBasePath . '/views/user/products.php';
$ordersUrl = $appBasePath . '/views/user/customer_orders.php';
$productImageBaseUrl = $publicBasePath . '/assets/img/product';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>Your Cart — Bite-Me-Donut</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800;900&display=swap"
    rel="stylesheet"
  />

  <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/root.css" />

  <?php if (is_file(cart_project_root() . '/public/assets/css/cart.css')): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/cart.css" />
  <?php endif; ?>

  <style>
    .cart-page {
      min-height: 100vh;
      background: var(--color-bg);
      padding: 64px 24px 96px;
    }

    .cart-container {
      max-width: 1180px;
      margin: 0 auto;
    }

    .cart-page__title {
      font-family: var(--font-heading);
      font-size: clamp(42px, 5vw, 68px);
      color: var(--color-text);
      margin: 0 0 32px;
    }

    .cart-layout {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 360px;
      gap: 28px;
      align-items: start;
    }

    .cart-items-panel,
    .cart-summary,
    .cart-empty,
    .cart-auth {
      background: #fff;
      border: 1px solid var(--color-border);
      border-radius: 24px;
      box-shadow: 0 18px 42px rgba(0,0,0,0.06);
    }

    .cart-items-panel {
      overflow: hidden;
    }

    .cart-item {
      display: grid;
      grid-template-columns: 120px 1fr auto;
      gap: 20px;
      padding: 22px;
      border-bottom: 1px solid var(--color-border);
      align-items: center;
    }

    .cart-item:last-child {
      border-bottom: 0;
    }

    .cart-item__image-wrap {
      width: 120px;
      height: 120px;
      border-radius: 18px;
      background: var(--color-primary-light);
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .cart-item__image {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .cart-item__name {
      font-family: var(--font-heading);
      font-size: 24px;
      color: var(--color-text);
      margin-bottom: 8px;
    }

    .cart-item__desc {
      color: var(--color-text-muted);
      line-height: 1.5;
      margin-bottom: 14px;
    }

    .cart-item__meta {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }

    .cart-qty {
      display: inline-flex;
      align-items: center;
      border: 1px solid var(--color-border);
      border-radius: 12px;
      overflow: hidden;
      background: var(--color-bg);
    }

    .cart-qty__btn {
      width: 38px;
      height: 38px;
      border: 0;
      background: transparent;
      cursor: pointer;
      font-size: 20px;
      font-weight: 900;
    }

    .cart-qty__btn:hover:not(:disabled) {
      color: var(--color-primary);
      background: var(--color-primary-light);
    }

    .cart-qty__btn:disabled {
      opacity: 0.45;
      cursor: not-allowed;
    }

    .cart-qty__val {
      width: 48px;
      text-align: center;
      line-height: 38px;
      font-weight: 900;
      background: #fff;
      border-left: 1px solid var(--color-border);
      border-right: 1px solid var(--color-border);
    }

    .cart-item__price {
      font-weight: 900;
      color: var(--color-primary);
      font-size: 18px;
    }

    .cart-item__remove {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      border: 0;
      cursor: pointer;
      background: #ffebee;
      color: #c62828;
      font-size: 24px;
      line-height: 1;
      font-weight: 900;
    }

    .cart-summary {
      padding: 24px;
      position: sticky;
      top: calc(var(--navbar-height) + 24px);
    }

    .cart-summary__title {
      font-family: var(--font-heading);
      font-size: 30px;
      margin: 0 0 22px;
    }

    .cart-summary__row {
      display: flex;
      justify-content: space-between;
      gap: 16px;
      padding: 14px 0;
      border-bottom: 1px solid var(--color-border);
    }

    .cart-summary__label {
      color: var(--color-text-muted);
      font-weight: 800;
    }

    .cart-summary__value {
      font-weight: 900;
      color: var(--color-text);
    }

    .cart-summary__total {
      font-size: 24px;
      color: var(--color-primary);
    }

    .cart-actions {
      display: flex;
      gap: 12px;
      margin-top: 18px;
      flex-wrap: wrap;
    }

    .cart-empty,
    .cart-auth {
      padding: 54px 28px;
      text-align: center;
    }

    .cart-empty__title,
    .cart-auth__title {
      font-family: var(--font-heading);
      font-size: 34px;
      margin: 0 0 12px;
    }

    .cart-empty__desc,
    .cart-auth__desc {
      color: var(--color-text-muted);
      line-height: 1.7;
      margin-bottom: 22px;
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

    .shipping-modal {
      position: fixed;
      inset: 0;
      z-index: 2000;
      background: rgba(0,0,0,0.35);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .shipping-modal.is-open {
      display: flex;
    }

    .shipping-modal__box {
      width: min(680px, 100%);
      background: #fff;
      border-radius: 24px;
      padding: 28px;
      box-shadow: 0 24px 64px rgba(0,0,0,0.18);
    }

    .shipping-modal__header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 22px;
    }

    .shipping-modal__title {
      font-family: var(--font-heading);
      font-size: 32px;
      margin: 0;
    }

    .shipping-modal__close {
      width: 38px;
      height: 38px;
      border: 0;
      border-radius: 50%;
      cursor: pointer;
      font-size: 24px;
      background: var(--color-primary-light);
      color: var(--color-primary);
    }

    .shipping-form__row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    .form-group {
      margin-bottom: 16px;
    }

    .form-label {
      display: block;
      font-weight: 900;
      margin-bottom: 8px;
    }

    .form-input,
    .form-textarea {
      width: 100%;
      border: 1px solid var(--color-border);
      border-radius: 12px;
      padding: 13px 14px;
      font: inherit;
      background: #fff;
    }

    .form-textarea {
      resize: vertical;
    }

    .toast-container {
      position: fixed;
      right: 24px;
      bottom: 24px;
      z-index: 3000;
      display: grid;
      gap: 10px;
    }

    .toast {
      padding: 14px 18px;
      border-radius: 12px;
      background: #fff;
      border-left: 4px solid var(--color-primary);
      box-shadow: 0 12px 32px rgba(0,0,0,0.14);
      font-weight: 800;
    }

    .toast--danger {
      border-left-color: #e53935;
    }

    .hidden {
      display: none !important;
    }

    @media (max-width: 900px) {
      .cart-layout {
        grid-template-columns: 1fr;
      }

      .cart-summary {
        position: static;
      }
    }

    @media (max-width: 640px) {
      .cart-item {
        grid-template-columns: 1fr;
      }

      .cart-item__image-wrap {
        width: 100%;
        height: 220px;
      }

      .shipping-form__row {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <?php include __DIR__ . '/../layouts/header.php'; ?>

  <main class="cart-page">
    <div class="cart-container">
      <h1 class="cart-page__title">Your cart</h1>

      <div id="alert-container"></div>

      <section id="cart-auth" class="cart-auth hidden">
        <h2 class="cart-auth__title">Please login</h2>
        <p class="cart-auth__desc">You need to login to view your cart.</p>
        <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn--primary btn--lg">Login</a>
      </section>

      <section id="cart-empty" class="cart-empty hidden">
        <h2 class="cart-empty__title">Your cart is empty!</h2>
        <p class="cart-empty__desc">
          Oops! It looks like your cart is still empty. Let's explore our yummy menu together.
        </p>
        <a href="<?= htmlspecialchars($productsUrl) ?>" class="btn btn--primary btn--lg">Shop Now</a>
      </section>

      <section id="cart-content" class="cart-layout hidden">
        <div>
          <div class="cart-items-panel" id="cart-items-panel"></div>

          <div class="cart-actions">
            <a href="<?= htmlspecialchars($productsUrl) ?>" class="btn btn--secondary">
              Continue Shopping
            </a>

            <button type="button" class="btn btn--ghost" id="btn-clear-cart">
              Clear Cart
            </button>
          </div>
        </div>

        <aside class="cart-summary">
          <h2 class="cart-summary__title">Summary</h2>

          <div class="cart-summary__row">
            <span class="cart-summary__label">Subtotal</span>
            <span class="cart-summary__value" id="summary-subtotal">0 VND</span>
          </div>

          <div class="cart-summary__row">
            <span class="cart-summary__label">Delivery</span>
            <span class="cart-summary__value">FREE</span>
          </div>

          <div class="cart-summary__row">
            <span class="cart-summary__label">Total</span>
            <span class="cart-summary__value cart-summary__total" id="summary-total">0 VND</span>
          </div>

          <button type="button" class="btn btn--primary btn--lg" id="btn-checkout" style="width:100%; margin-top:22px;">
            Checkout
          </button>
        </aside>
      </section>
    </div>
  </main>

  <div class="shipping-modal" id="shipping-modal" role="dialog" aria-modal="true">
    <div class="shipping-modal__box">
      <div class="shipping-modal__header">
        <h2 class="shipping-modal__title">Thông tin giao hàng</h2>
        <button type="button" class="shipping-modal__close" id="btn-close-shipping">×</button>
      </div>

      <div class="shipping-form">
        <div class="shipping-form__row">
          <div class="form-group">
            <label class="form-label" for="ship-name">Họ tên *</label>
            <input type="text" id="ship-name" class="form-input" required />
          </div>

          <div class="form-group">
            <label class="form-label" for="ship-phone">Số điện thoại *</label>
            <input type="tel" id="ship-phone" class="form-input" required />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="ship-address">Địa chỉ *</label>
          <input type="text" id="ship-address" class="form-input" required />
        </div>

        <div class="shipping-form__row">
          <div class="form-group">
            <label class="form-label" for="ship-city">Thành phố *</label>
            <input type="text" id="ship-city" class="form-input" value="TP. Hồ Chí Minh" required />
          </div>

          <div class="form-group">
            <label class="form-label" for="ship-district">Quận / Huyện</label>
            <input type="text" id="ship-district" class="form-input" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="ship-note">Ghi chú</label>
          <textarea id="ship-note" class="form-textarea" rows="3"></textarea>
        </div>

        <div id="shipping-error" class="alert alert--danger hidden"></div>

        <div class="cart-actions">
          <button type="button" class="btn btn--ghost" id="btn-cancel-shipping">Huỷ</button>
          <button type="button" class="btn btn--primary" id="btn-place-order">Đặt hàng</button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast-container" id="toast-container"></div>

  <?php
  $footerPath = __DIR__ . '/../layouts/footer.php';

  if (is_file($footerPath)) {
      include $footerPath;
  }
  ?>

  <script>
    window.CART_CONFIG = {
      cartProxyUrl: <?= json_encode($cartProxyUrl) ?>,
      loginUrl: <?= json_encode($loginUrl) ?>,
      productsUrl: <?= json_encode($productsUrl) ?>,
      ordersUrl: <?= json_encode($ordersUrl) ?>,
      productImageBaseUrl: <?= json_encode($productImageBaseUrl) ?>
    };
  </script>

  <script>
  document.addEventListener('DOMContentLoaded', async () => {
    'use strict';

    const CONFIG = window.CART_CONFIG || {};

    const CART_PROXY_URL = CONFIG.cartProxyUrl || `${window.location.pathname}?cart_ajax=1`;
    const LOGIN_URL = CONFIG.loginUrl || '../auth/user-login.php';
    const PRODUCTS_URL = CONFIG.productsUrl || './products.php';
    const ORDERS_URL = CONFIG.ordersUrl || './customer_orders.php';
    const IMAGE_BASE_URL = CONFIG.productImageBaseUrl || '../../public/assets/img/product';

    const TOKEN_KEYS = ['access_token', 'auth_token'];
    const USER_KEYS = ['auth_user', 'current_user', 'user'];

    const authSection = document.getElementById('cart-auth');
    const emptySection = document.getElementById('cart-empty');
    const contentSection = document.getElementById('cart-content');
    const itemsPanel = document.getElementById('cart-items-panel');
    const summarySubtotal = document.getElementById('summary-subtotal');
    const summaryTotal = document.getElementById('summary-total');
    const alertContainer = document.getElementById('alert-container');
    const toastContainer = document.getElementById('toast-container');

    const shippingModal = document.getElementById('shipping-modal');
    const btnCheckout = document.getElementById('btn-checkout');
    const btnClearCart = document.getElementById('btn-clear-cart');
    const btnCloseShipping = document.getElementById('btn-close-shipping');
    const btnCancelShipping = document.getElementById('btn-cancel-shipping');
    const btnPlaceOrder = document.getElementById('btn-place-order');
    const shippingError = document.getElementById('shipping-error');

    let currentCart = {
      items: [],
      total: 0,
      count: 0
    };

    function getToken() {
      for (const key of TOKEN_KEYS) {
        const value = localStorage.getItem(key) || sessionStorage.getItem(key);

        if (value) {
          return value;
        }
      }

      return null;
    }

    function clearSession() {
      TOKEN_KEYS.forEach((key) => {
        localStorage.removeItem(key);
        sessionStorage.removeItem(key);
      });

      USER_KEYS.forEach((key) => {
        localStorage.removeItem(key);
        sessionStorage.removeItem(key);
      });

      localStorage.removeItem('is_logged_in');
    }

    function getCachedUser() {
      for (const key of USER_KEYS) {
        const raw = localStorage.getItem(key);

        if (!raw) continue;

        try {
          const user = JSON.parse(raw);

          if (user && typeof user === 'object') {
            return user;
          }
        } catch (error) {
          localStorage.removeItem(key);
        }
      }

      return null;
    }

    function escapeHtml(value) {
      return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    function formatMoney(value) {
      return Number(value || 0).toLocaleString('vi-VN') + ' VND';
    }

    function fallbackImageDataUri() {
      const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">
          <rect width="600" height="600" fill="#fdf3c8"/>
          <circle cx="300" cy="300" r="145" fill="#e91e8c" opacity="0.18"/>
          <circle cx="300" cy="300" r="72" fill="#fef9e7"/>
          <text x="300" y="475" text-anchor="middle" font-family="Arial" font-size="34" font-weight="700" fill="#2b1a0e">Bite Me Donut</text>
        </svg>
      `;

      return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
    }

    function imageUrl(image) {
      const value = String(image || '').trim();

      if (!value) {
        return fallbackImageDataUri();
      }

      if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/')) {
        return value;
      }

      return `${IMAGE_BASE_URL}/${encodeURIComponent(value)}`;
    }

    function showToast(message, type = 'success') {
      if (!toastContainer) return;

      const toast = document.createElement('div');
      toast.className = `toast ${type === 'danger' ? 'toast--danger' : ''}`;
      toast.textContent = message;

      toastContainer.appendChild(toast);

      setTimeout(() => {
        toast.remove();
      }, 3000);
    }

    function showAlert(message, type = 'danger') {
      if (!alertContainer) return;

      alertContainer.innerHTML = `<div class="alert alert--${type}">${escapeHtml(message)}</div>`;

      setTimeout(() => {
        alertContainer.innerHTML = '';
      }, 3500);
    }

    function showOnly(section) {
      [authSection, emptySection, contentSection].forEach((el) => {
        el?.classList.add('hidden');
      });

      section?.classList.remove('hidden');
    }

    async function postCart(action, payload = {}) {
      const token = getToken();

      if (!token) {
        return {
          ok: false,
          status: 401,
          data: {
            success: false,
            error_code: 'UNAUTHENTICATED',
            message: 'Missing access token'
          }
        };
      }

      const body = {
        action,
        access_token: token,
        ...payload
      };

      console.log('[CART POST]', CART_PROXY_URL, body);

      const response = await fetch(CART_PROXY_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(body)
      });

      const rawText = await response.text();

      console.log('[CART STATUS]', response.status);
      console.log('[CART RAW]', rawText);

      let data;

      try {
        data = JSON.parse(rawText);
      } catch (error) {
        return {
          ok: false,
          status: response.status,
          data: {
            success: false,
            error_code: 'INVALID_JSON_RESPONSE',
            message: 'Server returned invalid JSON response',
            raw_response: rawText
          }
        };
      }

      return {
        ok: response.ok && data.success !== false,
        status: response.status,
        data
      };
    }

    function renderCart(payload) {
      currentCart = payload || {
        items: [],
        total: 0,
        count: 0
      };

      const items = currentCart.items || [];

      if (!items.length) {
        showOnly(emptySection);
        return;
      }

      showOnly(contentSection);

      itemsPanel.innerHTML = items.map((item) => {
        const cartItemId = Number(item.cart_item_id);
        const quantity = Number(item.quantity || 1);
        const price = Number(item.price || 0);
        const subtotal = Number(item.subtotal || price * quantity);
        const stock = Number(item.stock || 0);

        return `
          <article class="cart-item" id="cart-item-${cartItemId}">
            <div class="cart-item__image-wrap">
              <img
                src="${escapeHtml(imageUrl(item.image))}"
                alt="${escapeHtml(item.product_name)}"
                class="cart-item__image"
                onerror="this.onerror=null; this.src='${fallbackImageDataUri()}'"
              />
            </div>

            <div class="cart-item__info">
              <div class="cart-item__name">${escapeHtml(item.product_name)}</div>

              <div class="cart-item__desc">${escapeHtml(item.description || '')}</div>

              <div class="cart-item__meta">
                <div class="cart-qty" data-item-id="${cartItemId}" data-stock="${stock}">
                  <button type="button" class="cart-qty__btn" data-action="decrease" ${quantity <= 1 ? 'disabled' : ''}>−</button>
                  <span class="cart-qty__val">${quantity}</span>
                  <button type="button" class="cart-qty__btn" data-action="increase" ${quantity >= stock ? 'disabled' : ''}>+</button>
                </div>

                <span class="cart-item__price">${escapeHtml(formatMoney(subtotal))}</span>
              </div>
            </div>

            <button
              type="button"
              class="cart-item__remove"
              data-item-id="${cartItemId}"
              aria-label="Remove item"
            >
              ×
            </button>
          </article>
        `;
      }).join('');

      summarySubtotal.textContent = formatMoney(currentCart.total);
      summaryTotal.textContent = formatMoney(currentCart.total);

      bindCartItemEvents();
    }

    function bindCartItemEvents() {
      document.querySelectorAll('.cart-qty__btn').forEach((button) => {
        button.addEventListener('click', async () => {
          const qtyEl = button.closest('.cart-qty');
          const itemId = Number(qtyEl?.dataset.itemId || 0);
          const stock = Number(qtyEl?.dataset.stock || 0);
          const valueEl = qtyEl?.querySelector('.cart-qty__val');
          const currentQty = Number(valueEl?.textContent || 1);
          const action = button.dataset.action;

          let nextQty = action === 'increase' ? currentQty + 1 : currentQty - 1;

          if (nextQty < 1) nextQty = 1;
          if (stock > 0 && nextQty > stock) nextQty = stock;

          if (nextQty === currentQty) return;

          button.disabled = true;

          try {
            const response = await postCart('update', {
              cart_item_id: itemId,
              quantity: nextQty
            });

            if (!response.ok) {
              showAlert(response.data?.message || 'Cannot update cart.');
              return;
            }

            renderCart(response.data.data);
          } finally {
            button.disabled = false;
          }
        });
      });

      document.querySelectorAll('.cart-item__remove').forEach((button) => {
        button.addEventListener('click', async () => {
          const itemId = Number(button.dataset.itemId || 0);

          if (!confirm('Bạn muốn xoá sản phẩm này khỏi giỏ hàng?')) {
            return;
          }

          button.disabled = true;

          try {
            const response = await postCart('remove', {
              cart_item_id: itemId
            });

            if (!response.ok) {
              showAlert(response.data?.message || 'Cannot remove cart item.');
              return;
            }

            renderCart(response.data.data);
            showToast('Đã xoá sản phẩm khỏi giỏ hàng.');
          } finally {
            button.disabled = false;
          }
        });
      });
    }

    async function loadCart() {
      if (!getToken()) {
        showOnly(authSection);
        return;
      }

      const response = await postCart('cart');

      if (!response.ok) {
        if (['UNAUTHENTICATED', 'TOKEN_INVALID', 'TOKEN_EXPIRED', 'TOKEN_REVOKED'].includes(response.data?.error_code)) {
          clearSession();
          showOnly(authSection);
          return;
        }

        showAlert(response.data?.message || 'Cannot load cart.');
        showOnly(emptySection);
        return;
      }

      renderCart(response.data.data);
    }

    function openShippingModal() {
      const user = getCachedUser();

      document.getElementById('ship-name').value = user?.name || user?.full_name || '';
      document.getElementById('ship-phone').value = user?.phone || '';

      shippingError?.classList.add('hidden');
      shippingModal?.classList.add('is-open');
    }

    function closeShippingModal() {
      shippingModal?.classList.remove('is-open');
    }

    btnClearCart?.addEventListener('click', async () => {
      if (!confirm('Bạn chắc chắn muốn xoá toàn bộ giỏ hàng?')) {
        return;
      }

      const response = await postCart('clear');

      if (!response.ok) {
        showAlert(response.data?.message || 'Cannot clear cart.');
        return;
      }

      renderCart(response.data.data);
      showToast('Giỏ hàng đã được xoá.');
    });

    btnCheckout?.addEventListener('click', () => {
      if (!currentCart.items || currentCart.items.length === 0) {
        showAlert('Giỏ hàng đang trống.', 'warning');
        return;
      }

      openShippingModal();
    });

    btnCloseShipping?.addEventListener('click', closeShippingModal);
    btnCancelShipping?.addEventListener('click', closeShippingModal);

    shippingModal?.addEventListener('click', (event) => {
      if (event.target === shippingModal) {
        closeShippingModal();
      }
    });

    btnPlaceOrder?.addEventListener('click', async () => {
      const shippingName = document.getElementById('ship-name').value.trim();
      const shippingPhone = document.getElementById('ship-phone').value.trim();
      const shippingAddress = document.getElementById('ship-address').value.trim();
      const shippingCity = document.getElementById('ship-city').value.trim();
      const shippingDistrict = document.getElementById('ship-district').value.trim();
      const note = document.getElementById('ship-note').value.trim();

      if (!shippingName || !shippingPhone || !shippingAddress || !shippingCity) {
        shippingError.textContent = 'Vui lòng nhập đầy đủ họ tên, số điện thoại, địa chỉ và thành phố.';
        shippingError.classList.remove('hidden');
        return;
      }

      shippingError.classList.add('hidden');
      btnPlaceOrder.disabled = true;
      btnPlaceOrder.textContent = 'Đang xử lý...';

      try {
        const response = await postCart('checkout', {
          shipping_name: shippingName,
          shipping_phone: shippingPhone,
          shipping_address: shippingAddress,
          shipping_city: shippingCity,
          shipping_district: shippingDistrict,
          note
        });

        if (!response.ok) {
          shippingError.textContent = response.data?.message || 'Đặt hàng thất bại.';
          shippingError.classList.remove('hidden');
          return;
        }

        closeShippingModal();
        showToast('Đặt hàng thành công!');

        setTimeout(() => {
          window.location.href = ORDERS_URL;
        }, 900);
      } finally {
        btnPlaceOrder.disabled = false;
        btnPlaceOrder.textContent = 'Đặt hàng';
      }
    });

    if (typeof window.updateGlobalHeaderAuth === 'function') {
      window.updateGlobalHeaderAuth(getCachedUser());
    }

    await loadCart();
  });
  </script>
</body>
</html>