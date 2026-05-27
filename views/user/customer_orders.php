<?php
declare(strict_types=1);

/**
 * Customer Orders Page
 * Flow:
 * - JS đọc access_token từ localStorage
 * - JS gọi customer_orders.php?orders_ajax=1
 * - PHP proxy sang backend API:
 *   + GET  /api/users/me
 *   + GET  /api/orders
 *   + POST /api/auth/logout
 */

$activePage = 'orders';

function orders_project_root(): string
{
    return dirname(__DIR__, 2);
}

function orders_load_env(): array
{
    $envPath = orders_project_root() . DIRECTORY_SEPARATOR . '.env';
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

function orders_env(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = orders_load_env();
    }

    return $env[$key] ?? $default;
}

function orders_app_base_path(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

function orders_scheme(): string
{
    $https = $_SERVER['HTTPS'] ?? '';

    return ($https !== '' && $https !== 'off') ? 'https' : 'http';
}

function orders_api_base_url(): string
{
    $appUrl = orders_env('APP_URL', '');

    if ($appUrl !== null && trim($appUrl) !== '') {
        return rtrim(trim($appUrl), '/') . '/api';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return orders_scheme() . '://' . $host . orders_app_base_path() . '/api';
}

function orders_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function orders_read_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        orders_json([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function orders_forward_to_api(string $method, string $endpoint, string $token, array $payload = []): void
{
    $url = orders_api_base_url() . $endpoint;

    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . $token
    ];

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => $headers
    ];

    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/json';

        $options[CURLOPT_POST] = true;
        $options[CURLOPT_HTTPHEADER] = $headers;
        $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $ch = curl_init($url);

    if ($ch === false) {
        orders_json([
            'success' => false,
            'error_code' => 'CURL_INIT_FAILED',
            'message' => 'Cannot initialize cURL'
        ], 500);
    }

    curl_setopt_array($ch, $options);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($rawResponse === false) {
        orders_json([
            'success' => false,
            'error_code' => 'API_PROXY_CURL_ERROR',
            'message' => $curlError !== '' ? $curlError : 'Cannot call backend API',
            'api_url' => $url
        ], 502);
    }

    $decoded = json_decode($rawResponse, true);

    if (!is_array($decoded)) {
        orders_json([
            'success' => false,
            'error_code' => 'API_PROXY_INVALID_JSON',
            'message' => 'Backend API did not return JSON',
            'api_url' => $url,
            'raw_response' => $rawResponse
        ], 502);
    }

    http_response_code($httpCode > 0 ? $httpCode : 200);
    header('Content-Type: application/json; charset=utf-8');

    echo $rawResponse;
    exit;
}

/**
 * AJAX proxy.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['orders_ajax'] ?? '') === '1') {
    $input = orders_read_json();

    $action = trim((string)($input['action'] ?? ''));
    $token = trim((string)($input['access_token'] ?? ''));

    if ($token === '') {
        orders_json([
            'success' => false,
            'error_code' => 'UNAUTHENTICATED',
            'message' => 'Access token is required'
        ], 401);
    }

    if ($action === 'me') {
        orders_forward_to_api('GET', '/users/me', $token);
    }

    if ($action === 'orders') {
        orders_forward_to_api('GET', '/orders', $token);
    }

    if ($action === 'logout') {
        orders_forward_to_api('POST', '/auth/logout', $token);
    }

    orders_json([
        'success' => false,
        'error_code' => 'INVALID_ACTION',
        'message' => 'Invalid orders action'
    ], 400);
}

$appBasePath = orders_app_base_path();
$publicBasePath = $appBasePath . '/public';

$ordersProxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?orders_ajax=1';

$loginUrl = $appBasePath . '/views/auth/user-login.php';
$profileUrl = $appBasePath . '/views/user/customer_profile.php';
$ordersUrl = $appBasePath . '/views/user/customer_orders.php';
$changePasswordUrl = $appBasePath . '/views/auth/change_password.php';
$productsUrl = $appBasePath . '/views/user/products.php';

$avatarUrl = $publicBasePath . '/assets/img/user.jpg';
$avatarFallbackUrl = $publicBasePath . '/assets/img/user-default.svg';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>My Orders — Bite-Me-Donut</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
  />

  <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/root.css" />
  <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/customer_profile.css" />
  <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/customer_orders.css" />
</head>

<body>
  <?php include __DIR__ . '/../layouts/header.php'; ?>

  <div class="profile-layout">

    <aside class="profile-sidebar" id="profileSidebar">
      <div class="sidebar-avatar-block">
        <div class="sidebar-avatar-ring">
          <img
            src="<?= htmlspecialchars($avatarUrl) ?>"
            alt="User avatar"
            class="sidebar-avatar-img"
            id="sidebarAvatar"
            onerror="this.src='<?= htmlspecialchars($avatarFallbackUrl) ?>'"
          />
        </div>

        <p class="sidebar-avatar-name" id="sidebarName">—</p>
        <span class="badge badge--primary sidebar-role" id="sidebarRole">CUSTOMER</span>
      </div>

      <nav class="sidebar-nav">
        <a href="<?= htmlspecialchars($profileUrl) ?>" class="sidebar-nav__item" data-page="profile">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
              <circle cx="12" cy="7" r="4"/>
            </svg>
          </span>
          <span class="sidebar-nav__label">My Profile</span>
        </a>

        <a href="<?= htmlspecialchars($ordersUrl) ?>" class="sidebar-nav__item active" data-page="orders">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
              <line x1="3" y1="6" x2="21" y2="6"/>
              <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
          </span>
          <span class="sidebar-nav__label">My Orders</span>
        </a>

        <a href="<?= htmlspecialchars($changePasswordUrl) ?>" class="sidebar-nav__item" data-page="change-password">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>
          <span class="sidebar-nav__label">Change Password</span>
        </a>

        <div class="sidebar-nav__divider"></div>

        <button type="button" class="sidebar-nav__item sidebar-nav__item--logout" id="sidebarLogoutBtn">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
              <polyline points="16 17 21 12 16 7"/>
              <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
          </span>
          <span class="sidebar-nav__label">Logout</span>
        </button>
      </nav>
    </aside>

    <main class="profile-main orders-main">
      <div class="loading-overlay" id="pageLoader">
        <div class="spinner"></div>
      </div>

      <div class="profile-error hidden" id="profileError">
        <div class="alert alert--danger" id="profileErrorMsg">
          Unable to load orders. Please log in again.
        </div>

        <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn--primary" style="margin-top: var(--space-4);">
          Log in
        </a>
      </div>

      <div class="orders-content hidden" id="ordersContent">
        <div class="orders-page-header">
          <div class="orders-page-header__left">
            <div class="orders-page-header__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26"
                   viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 0 1-8 0"/>
              </svg>
            </div>

            <div>
              <h1 class="orders-page-header__title">Order History</h1>
              <p class="orders-page-header__subtitle">Track and review all your past purchases</p>
            </div>
          </div>

          <div class="orders-summary-chips" id="ordersSummaryChips">
            <div class="summary-chip">
              <span class="summary-chip__value" id="chipTotal">—</span>
              <span class="summary-chip__label">Total Orders</span>
            </div>

            <div class="summary-chip summary-chip--pink">
              <span class="summary-chip__value" id="chipSpent">—</span>
              <span class="summary-chip__label">Total Spent</span>
            </div>
          </div>
        </div>

        <div class="orders-filter-bar">
          <div class="orders-search-wrap">
            <span class="orders-search-wrap__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                   viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
              </svg>
            </span>

            <input
              type="text"
              class="orders-search-input"
              id="ordersSearch"
              placeholder="Search by order ID or note…"
              autocomplete="off"
            />
          </div>

          <div class="orders-status-tabs" id="ordersStatusTabs">
            <button type="button" class="status-tab active" data-status="all">All</button>
            <button type="button" class="status-tab" data-status="pending">Pending</button>
            <button type="button" class="status-tab" data-status="processing">Processing</button>
            <button type="button" class="status-tab" data-status="shipping">Shipping</button>
            <button type="button" class="status-tab" data-status="completed">Completed</button>
            <button type="button" class="status-tab" data-status="cancelled">Cancelled</button>
          </div>
        </div>

        <div class="orders-list" id="ordersList"></div>

        <div class="orders-empty hidden" id="ordersEmpty">
          <div class="orders-empty__illustration">
            <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"
                 style="opacity:0.25;">
              <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
              <line x1="3" y1="6" x2="21" y2="6"/>
              <path d="M16 10a4 4 0 0 1-8 0"/>
            </svg>
          </div>

          <h3 class="orders-empty__title" id="ordersEmptyTitle">No orders found</h3>
          <p class="orders-empty__desc" id="ordersEmptyDesc">
            You haven't placed any orders yet. Start shopping!
          </p>

          <a href="<?= htmlspecialchars($productsUrl) ?>" class="btn btn--primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Browse Products
          </a>
        </div>
      </div>
    </main>
  </div>

  <div class="modal-overlay" id="logoutModal">
    <div class="modal">
      <div class="modal__header">
        <h3 class="modal__title">Logout</h3>

        <button type="button" class="modal__close" id="logoutModalClose">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
               viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <p style="margin-bottom: var(--space-6); color: var(--color-text-muted); line-height: 1.7;">
        Are you sure you want to logout?<br/>Your token will be revoked immediately.
      </p>

      <div class="flex gap-4">
        <button type="button" class="btn btn--danger w-full" id="confirmLogoutBtn">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"
               viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
          Logout
        </button>

        <button type="button" class="btn btn--ghost w-full" id="cancelLogoutBtn">
          Cancel
        </button>
      </div>
    </div>
  </div>

  <div class="toast-container" id="toastContainer"></div>

  <?php include __DIR__ . '/../layouts/footer.php'; ?>

  <script>
    window.CUSTOMER_ORDERS_CONFIG = {
      ordersProxyUrl: <?= json_encode($ordersProxyUrl) ?>,
      loginUrl: <?= json_encode($loginUrl) ?>,
      profileUrl: <?= json_encode($profileUrl) ?>,
      ordersUrl: <?= json_encode($ordersUrl) ?>,
      changePasswordUrl: <?= json_encode($changePasswordUrl) ?>
    };
  </script>

  <script src="<?= htmlspecialchars($publicBasePath) ?>/assets/js/customer_orders.js?v=<?= time() ?>" defer></script>
</body>
</html>