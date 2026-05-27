<?php
declare(strict_types=1);

$activePage = 'profile';

function profile_project_root(): string
{
    return dirname(__DIR__, 2);
}

function profile_load_env(): array
{
    $envPath = profile_project_root() . DIRECTORY_SEPARATOR . '.env';
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

function profile_env(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = profile_load_env();
    }

    return $env[$key] ?? $default;
}

function profile_app_base_path(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

function profile_scheme(): string
{
    $https = $_SERVER['HTTPS'] ?? '';

    return ($https !== '' && $https !== 'off') ? 'https' : 'http';
}

function profile_api_base_url(): string
{
    $appUrl = profile_env('APP_URL', '');

    if ($appUrl !== null && trim($appUrl) !== '') {
        return rtrim(trim($appUrl), '/') . '/api';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return profile_scheme() . '://' . $host . profile_app_base_path() . '/api';
}

function profile_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function profile_read_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        profile_json([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function profile_call_api(string $method, string $endpoint, string $token, array $payload = []): array
{
    $url = profile_api_base_url() . $endpoint;

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
    curl_setopt_array($ch, $options);

    $rawResponse = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $body = json_decode((string)$rawResponse, true);

    return [
        'http_code' => $httpCode,
        'body' => is_array($body) ? $body : []
    ];
}

function profile_extract_user(array $response): ?array
{
    if (isset($response['data']['user']) && is_array($response['data']['user'])) {
        return $response['data']['user'];
    }

    if (isset($response['data']) && is_array($response['data']) && isset($response['data']['id'])) {
        return $response['data'];
    }

    if (isset($response['user']) && is_array($response['user'])) {
        return $response['user'];
    }

    if (isset($response['id'])) {
        return $response;
    }

    return null;
}
function profile_forward_to_api(string $method, string $endpoint, string $token): void
{
    $url = profile_api_base_url() . $endpoint;

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
        $options[CURLOPT_POSTFIELDS] = '{}';
    }

    $ch = curl_init($url);

    if ($ch === false) {
        profile_json([
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
        profile_json([
            'success' => false,
            'error_code' => 'API_PROXY_CURL_ERROR',
            'message' => $curlError !== '' ? $curlError : 'Cannot call backend API',
            'api_url' => $url
        ], 502);
    }

    $decoded = json_decode($rawResponse, true);

    if (!is_array($decoded)) {
        profile_json([
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

function profile_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = profile_env('DB_HOST', '127.0.0.1');
    $port = profile_env('DB_PORT', '3306');
    $name = profile_env('DB_NAME', 'ecommerce_security_platform');
    $user = profile_env('DB_USER', 'root');
    $pass = profile_env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function profile_get_current_user_by_token(string $plainToken): ?array
{
    $tokenHash = hash('sha256', $plainToken);

    $stmt = profile_db()->prepare("
        SELECT 
            u.id,
            u.name,
            u.email,
            u.phone,
            u.role,
            u.is_active,
            u.created_at,
            u.updated_at
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

    if ((int) $user['is_active'] !== 1) {
        return null;
    }

    return $user;
}

function profile_email_used_by_other_user(string $email, int $userId): bool
{
    $stmt = profile_db()->prepare("
        SELECT id
        FROM users
        WHERE email = :email
          AND id <> :id
        LIMIT 1
    ");

    $stmt->execute([
        'email' => $email,
        'id' => $userId
    ]);

    return (bool) $stmt->fetch();
}

function profile_update_user(int $userId, string $name, string $email): array
{
    $stmt = profile_db()->prepare("
        UPDATE users
        SET name = :name,
            email = :email,
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'id' => $userId
    ]);

    $stmt = profile_db()->prepare("
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
        'id' => $userId
    ]);

    return $stmt->fetch() ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['profile_ajax'] ?? '') === '1') {
    $input = profile_read_json();

    $action = trim((string)($input['action'] ?? ''));
    $token = trim((string)($input['access_token'] ?? ''));

    if ($token === '') {
        profile_json([
            'success' => false,
            'error_code' => 'UNAUTHENTICATED',
            'message' => 'Access token is required'
        ], 401);
    }

    if ($action === 'me') {
        profile_forward_to_api('GET', '/users/me', $token);
    }

    if ($action === 'update_profile') {
        $name = trim((string)($input['name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));

        if ($name === '' || mb_strlen($name) < 2) {
            profile_json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Full name is required'
            ], 400);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            profile_json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Valid email is required'
            ], 400);
        }

        $tokenHash = hash('sha256', $token);

        $stmt = profile_db()->prepare("
            SELECT 
                u.id,
                u.name,
                u.email,
                u.phone,
                u.role,
                u.is_active,
                u.created_at,
                u.updated_at
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

        $currentUser = $stmt->fetch();

        if (!$currentUser) {
            profile_json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Your login session has expired. Please log in again.'
            ], 401);
        }

        if ((int)$currentUser['is_active'] !== 1) {
            profile_json([
                'success' => false,
                'error_code' => 'ACCOUNT_INACTIVE',
                'message' => 'This account is inactive'
            ], 403);
        }

        $userId = (int)$currentUser['id'];

        $stmt = profile_db()->prepare("
            SELECT id
            FROM users
            WHERE email = :email
              AND id <> :id
            LIMIT 1
        ");

        $stmt->execute([
            'email' => $email,
            'id' => $userId
        ]);

        $emailOwner = $stmt->fetch();

        if ($emailOwner) {
            profile_json([
                'success' => false,
                'error_code' => 'EMAIL_ALREADY_EXISTS',
                'message' => 'This email is already used by another account'
            ], 409);
        }

        try {
            $stmt = profile_db()->prepare("
                UPDATE users
                SET name = :name,
                    email = :email,
                    updated_at = NOW()
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'id' => $userId
            ]);

            $stmt = profile_db()->prepare("
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
                'id' => $userId
            ]);

            $updatedUser = $stmt->fetch();

            profile_json([
                'success' => true,
                'message' => 'PROFILE_UPDATED',
                'data' => [
                    'user' => $updatedUser
                ]
            ]);
        } catch (Throwable $e) {
            profile_json([
                'success' => false,
                'error_code' => 'PROFILE_UPDATE_FAILED',
                'message' => 'Cannot update profile'
            ], 500);
        }
    }

    if ($action === 'logout') {
        profile_forward_to_api('POST', '/auth/logout', $token);
    }

    profile_json([
        'success' => false,
        'error_code' => 'INVALID_ACTION',
        'message' => 'Invalid profile action'
    ], 400);
}

$appBasePath = profile_app_base_path();
$publicBasePath = $appBasePath . '/public';

$profileProxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?profile_ajax=1';
$loginUrl = $appBasePath . '/views/auth/user-login.php';
$homeUrl = $appBasePath . '/views/user/home.php';
$ordersUrl = $appBasePath . '/views/user/customer_orders.php';
$changePasswordUrl = $appBasePath . '/views/auth/change_password.php';
?>


<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Profile — Bite-Me-Donut</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/root.css" />
  <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/customer_profile.css" />
</head>
<body>

  <?php include __DIR__ . '/../layouts/header.php'; ?>

  <!-- ══════════════════════════════════════════════
       MAIN LAYOUT
  ══════════════════════════════════════════════ -->
  <div class="profile-layout">

    <!-- ── SIDEBAR ── -->
    <aside class="profile-sidebar" id="profileSidebar">

      <!-- Mini avatar in sidebar -->
      <div class="sidebar-avatar-block">
        <div class="sidebar-avatar-ring">
          <img
  src="<?= htmlspecialchars($publicBasePath) ?>/assets/img/user.jpg"
  alt="Ảnh đại diện"
  class="sidebar-avatar-img"
  id="sidebarAvatar"
  onerror="this.src='<?= htmlspecialchars($publicBasePath) ?>/assets/img/user-default.svg'"
/>
        </div>
        <p class="sidebar-avatar-name" id="sidebarName">—</p>
        <span class="badge badge--primary sidebar-role" id="sidebarRole">Customer</span>
      </div>

      <nav class="sidebar-nav">

        <!-- My Profile -->
        <a href="<?= htmlspecialchars($appBasePath) ?>/views/user/customer_profile.php" class="sidebar-nav__item active" data-page="profile">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </span>
          <span class="sidebar-nav__label">My Profile</span>
        </a>

        <!-- My Orders -->
        <a href="<?= htmlspecialchars($ordersUrl) ?>"
   class="sidebar-nav__item"
   data-page="orders">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          </span>
          <span class="sidebar-nav__label">My Orders</span>
        </a>

        <!-- Change Password -->
        <a href="<?= htmlspecialchars($changePasswordUrl) ?>"
   class="sidebar-nav__item"
   data-page="change-password">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </span>
          <span class="sidebar-nav__label">Change Password</span>
        </a>

        <div class="sidebar-nav__divider"></div>

        <!-- Logout -->
        <button class="sidebar-nav__item sidebar-nav__item--logout" id="sidebarLogoutBtn">
          <span class="sidebar-nav__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </span>
          <span class="sidebar-nav__label">Logout</span>
        </button>

      </nav>
    </aside><!-- /.profile-sidebar -->

    <!-- ── MAIN CONTENT ── -->
    <main class="profile-main">

      <!-- Loading state -->
      <div class="loading-overlay" id="pageLoader">
        <div class="spinner"></div>
      </div>

      <!-- Error -->
      <div class="profile-error hidden" id="profileError">
        <div class="alert alert--danger" id="profileErrorMsg">
          Cannot load profile. Please log in again.
        </div>
        <a href="<?= htmlspecialchars($loginUrl) ?>"
   class="btn btn--primary"
   style="margin-top: var(--space-4);">
  Log in
</a>
      </div>

      <!-- Profile Content -->
      <div class="profile-content hidden" id="profileContent">

        <!-- Breadcrumb -->
        <!-- <nav class="breadcrumb">
          <div class="breadcrumb__item">
            <a href="/index.php" class="breadcrumb__link">Trang chủ</a>
            <span class="breadcrumb__separator">›</span>
          </div>
          <div class="breadcrumb__item">
            <span class="breadcrumb__current">Hồ sơ của tôi</span>
          </div>
        </nav> -->

        <!-- ── HERO BANNER ── -->
        <div class="profile-hero">
          <div class="profile-hero__bg"></div>
          <div class="profile-hero__content">

            <div class="avatar-wrapper">
              <div class="avatar-ring">
                <img
  src="<?= htmlspecialchars($publicBasePath) ?>/assets/img/user.jpg"
  alt="Ảnh đại diện"
  class="avatar-img"
  id="avatarImg"
  onerror="this.src='<?= htmlspecialchars($publicBasePath) ?>/assets/img/user-default.svg'"
/>
              </div>
              <div class="avatar-status" title="Đang hoạt động"></div>
            </div>

            <div class="profile-hero__info">
              <h1 class="profile-hero__name" id="heroName">—</h1>
              <div class="profile-hero__meta">
                <span class="badge badge--primary" id="heroRole">Customer</span>
                <span class="profile-hero__join" id="heroJoin"></span>
              </div>
              <p class="profile-hero__phone" id="heroPhone"></p>
            </div>

          </div>
        </div><!-- /.profile-hero -->

        <!-- ── TWO-COLUMN: Info + Security ── -->
        <div class="profile-grid">

          <!-- Personal Info card -->
          <section class="profile-card" id="infoCard">
            <div class="profile-card__header">
              <span class="profile-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </span>
              <h2 class="profile-card__title">Personal Information</h2>
              <button class="btn btn--outline btn--sm" id="editBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
              </button>
            </div>

            <!-- View mode -->
            <div class="profile-info-list" id="infoView">
              <div class="info-row">
                <span class="info-row__label">Full name</span>
                <span class="info-row__value" id="infoName">—</span>
              </div>
              <div class="info-row">
                <span class="info-row__label">Email</span>
                <span class="info-row__value" id="infoEmail">—</span>
              </div>
              <div class="info-row">
                <span class="info-row__label">Phone Number</span>
                <span class="info-row__value" id="infoPhone">—</span>
              </div>
              <div class="info-row">
                <span class="info-row__label">Role</span>
                <span class="info-row__value" id="infoRole">—</span>
              </div>
              <div class="info-row">
                <span class="info-row__label">Status</span>
                <span class="info-row__value" id="infoStatus">—</span>
              </div>
              <div class="info-row">
                <span class="info-row__label">Joined Date</span>
                <span class="info-row__value" id="infoCreated">—</span>
              </div>
            </div>

            <!-- Edit mode -->
<form class="profile-edit-form hidden" id="editForm" novalidate>
  <div class="form-group">
    <label class="form-label" for="editName">Full name</label>
    <input
      type="text"
      class="form-input"
      id="editName"
      name="name"
      placeholder="Enter your full name"
      required
    />
  </div>

  <div class="form-group">
    <label class="form-label" for="editEmail">Email</label>
    <input
      type="email"
      class="form-input"
      id="editEmail"
      name="email"
      placeholder="Enter your email"
      required
    />
    <span class="form-hint">Email is used to login and receive notifications</span>
  </div>

  <div class="form-group">
    <label class="form-label" for="editPhone">Phone Number</label>
    <input
      type="text"
      class="form-input"
      id="editPhone"
      name="phone"
      readonly
    />
    <span class="form-hint">Phone Number cannot be changed</span>
  </div>

  <div class="edit-actions">
    <button class="btn btn--primary" id="saveProfileBtn" type="submit">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15"
           viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
        <polyline points="17 21 17 13 7 13 7 21"/>
        <polyline points="7 3 7 8 15 8"/>
      </svg>
      Save Changes
    </button>

    <button class="btn btn--ghost" id="cancelEditBtn" type="button">
      Cancel
    </button>
  </div>

  <div id="editAlert" class="hidden" style="margin-top: var(--space-4);"></div>
</form>
          </section>

          <!-- Security card -->
          <section class="profile-card profile-card--security">
            <div class="profile-card__header">
              <span class="profile-card__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              </span>
              <h2 class="profile-card__title">Account Security</h2>
            </div>
            <div class="security-list">

              <!-- OTP -->
              <div class="security-item">
                <div class="security-item__left">
                  <span class="security-item__icon security-item__icon--ok">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-success)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  </span>
                  <div>
                    <p class="security-item__title">OTP Verification</p>
                    <p class="security-item__desc">Two-factor protection via call</p>
                  </div>
                </div>
                <span class="badge badge--success">Enabled</span>
              </div>

              <!-- Change password -->
              <div class="security-item">
                <div class="security-item__left">
                  <span class="security-item__icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-text-muted)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                  </span>
                  <div>
                    <p class="security-item__title">Password</p>
                    <p class="security-item__desc">Update password regularly</p>
                  </div>
                </div>
                <a href="<?= htmlspecialchars($changePasswordUrl) ?>"
   class="btn btn--outline btn--sm">
  Change
</a>
              </div>

              <!-- Session -->
              <div class="security-item">
                <div class="security-item__left">
                  <span class="security-item__icon" id="sessionIcon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-success)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  </span>
                  <div>
                    <p class="security-item__title">Login Session</p>
                    <p class="security-item__desc" id="sessionDesc">Token is valid</p>
                  </div>
                </div>
                <button class="btn btn--danger btn--sm" id="revokeBtn">
                  <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                  Logout
                </button>
              </div>

            </div>
          </section>

        </div><!-- /.profile-grid -->

      </div><!-- /#profileContent -->

    </main><!-- /.profile-main -->

  </div><!-- /.profile-layout -->

  <!-- ══════════════════════════════════════════════
       LOGOUT MODAL
  ══════════════════════════════════════════════ -->
  <div class="modal-overlay" id="logoutModal">
    <div class="modal">
      <div class="modal__header">
        <h3 class="modal__title">Logout</h3>
        <button class="modal__close" id="logoutModalClose">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <p style="margin-bottom: var(--space-6); color: var(--color-text-muted); line-height: 1.7;">
        Are you sure you want to logout?<br/>Your token will be revoked immediately.
      </p>
      <div class="flex gap-4">
        <button class="btn btn--danger w-full" id="confirmLogoutBtn">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Logout
        </button>
        <button class="btn btn--ghost w-full" id="cancelLogoutBtn">Cancel</button>
      </div>
    </div>
  </div>

  <!-- TOAST -->
  <div class="toast-container" id="toastContainer"></div>
  <?php include __DIR__ . '/../layouts/footer.php'; ?>
  

  <script>
  window.CUSTOMER_PROFILE_CONFIG = {
    profileProxyUrl: <?= json_encode($profileProxyUrl) ?>,
    loginUrl: <?= json_encode($loginUrl) ?>,
    homeUrl: <?= json_encode($homeUrl) ?>
  };
</script>

<script src="<?= htmlspecialchars($publicBasePath) ?>/assets/js/customer_profile.js?v=<?= time() ?>" defer></script>  
</body>
</html>
