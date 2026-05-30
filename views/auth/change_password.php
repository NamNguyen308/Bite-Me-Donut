<?php
declare(strict_types=1);

/**
 * Change Password Page
 * Rule:
 * - User must have valid access_token in localStorage.
 * - JS sends token to this file through change_password.php?change_ajax=1.
 * - PHP verifies token against tokens table.
 * - PHP verifies current password.
 * - PHP updates users.password_hash.
 * - Current access token is NOT revoked after password change.
 */

$activePage = 'change-password';

function cp_project_root(): string
{
    return dirname(__DIR__, 2);
}

function cp_load_env(): array
{
    $envPath = cp_project_root() . DIRECTORY_SEPARATOR . '.env';
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

function cp_env(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = cp_load_env();
    }

    return $env[$key] ?? $default;
}

function cp_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = cp_env('DB_HOST', '127.0.0.1');
    $port = cp_env('DB_PORT', '3306');
    $name = cp_env('DB_NAME', 'ecommerce_security_platform');
    $user = cp_env('DB_USER', 'root');
    $pass = cp_env('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function cp_app_base_path(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

function cp_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cp_read_json(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        cp_json([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function cp_find_user_by_token(string $plainToken): ?array
{
    $tokenHash = hash('sha256', $plainToken);

    $stmt = cp_db()->prepare("
        SELECT
            u.id,
            u.name,
            u.email,
            u.phone,
            u.password_hash,
            u.role,
            u.is_active,
            u.created_at,
            u.updated_at,
            t.id AS token_id,
            t.expires_at,
            t.revoked_at
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

    return $user;
}

function cp_public_user(array $user): array
{
    return [
        'id' => (int)$user['id'],
        'name' => $user['name'] ?? null,
        'email' => $user['email'] ?? null,
        'phone' => $user['phone'] ?? null,
        'role' => $user['role'] ?? 'customer',
        'is_active' => (int)($user['is_active'] ?? 0),
        'created_at' => $user['created_at'] ?? null,
        'updated_at' => $user['updated_at'] ?? null,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['change_ajax'] ?? '') === '1') {
    try {
        $input = cp_read_json();

        $action = trim((string)($input['action'] ?? ''));
        $token = trim((string)($input['access_token'] ?? ''));

        if ($token === '') {
            cp_json([
                'success' => false,
                'error_code' => 'UNAUTHENTICATED',
                'message' => 'Access token is required'
            ], 401);
        }

        $user = cp_find_user_by_token($token);

        if (!$user) {
            cp_json([
                'success' => false,
                'error_code' => 'TOKEN_INVALID',
                'message' => 'Your login session has expired. Please log in again.'
            ], 401);
        }

        if ($action === 'me') {
            cp_json([
                'success' => true,
                'message' => 'USER_LOADED',
                'data' => [
                    'user' => cp_public_user($user)
                ]
            ]);
        }

        if ($action === 'change_password') {
            $currentPassword = (string)($input['current_password'] ?? '');
            $newPassword = (string)($input['new_password'] ?? '');
            $confirmPassword = (string)($input['confirm_password'] ?? '');

            if ($currentPassword === '') {
                cp_json([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Current password is required',
                    'field' => 'current_password'
                ], 400);
            }

            if ($newPassword === '' || strlen($newPassword) < 8) {
                cp_json([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'New password must be at least 8 characters',
                    'field' => 'new_password'
                ], 400);
            }

            if ($confirmPassword === '' || $newPassword !== $confirmPassword) {
                cp_json([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'New password confirmation does not match',
                    'field' => 'confirm_password'
                ], 400);
            }

            if (!password_verify($currentPassword, (string)$user['password_hash'])) {
                cp_json([
                    'success' => false,
                    'error_code' => 'CURRENT_PASSWORD_INCORRECT',
                    'message' => 'Current password is incorrect',
                    'field' => 'current_password'
                ], 400);
            }

            if (password_verify($newPassword, (string)$user['password_hash'])) {
                cp_json([
                    'success' => false,
                    'error_code' => 'PASSWORD_SAME_AS_OLD',
                    'message' => 'New password must be different from current password',
                    'field' => 'new_password'
                ], 400);
            }

            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = cp_db()->prepare("
                UPDATE users
                SET password_hash = :password_hash,
                    updated_at = NOW()
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                'password_hash' => $newPasswordHash,
                'id' => (int)$user['id']
            ]);

            /*
             * Important:
             * Do NOT revoke current access token here.
             * User stays logged in after password change.
             */

            cp_json([
                'success' => true,
                'message' => 'PASSWORD_CHANGED',
                'data' => [
                    'user' => cp_public_user($user),
                    'keep_session' => true
                ]
            ]);
        }

        if ($action === 'logout') {
            $tokenHash = hash('sha256', $token);

            $stmt = cp_db()->prepare("
                UPDATE tokens
                SET revoked_at = NOW()
                WHERE token_hash = :token_hash
                  AND revoked_at IS NULL
                LIMIT 1
            ");

            $stmt->execute([
                'token_hash' => $tokenHash
            ]);

            cp_json([
                'success' => true,
                'message' => 'LOGGED_OUT'
            ]);
        }

        cp_json([
            'success' => false,
            'error_code' => 'INVALID_ACTION',
            'message' => 'Invalid action'
        ], 400);
    } catch (Throwable $e) {
        cp_json([
            'success' => false,
            'error_code' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ], 500);
    }
}

$appBasePath = cp_app_base_path();
$publicBasePath = $appBasePath . '/public';

$changeProxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?change_ajax=1';

$loginUrl = $appBasePath . '/views/auth/user-login.php';
$profileUrl = $appBasePath . '/views/user/customer_profile.php';
$ordersUrl = $appBasePath . '/views/user/customer_orders.php';
$changePasswordUrl = $appBasePath . '/views/auth/change_password.php';

$avatarUrl = $publicBasePath . '/assets/img/user.jpg';
$avatarFallbackUrl = $publicBasePath . '/assets/img/user-default.svg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title>Change Password — Bite-Me-Donut</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
  />

  <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/root.css" />
  <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/customer_profile.css" />
  <link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/change_password.css" />
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

        <a href="<?= htmlspecialchars($ordersUrl) ?>" class="sidebar-nav__item" data-page="orders">
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

        <a href="<?= htmlspecialchars($changePasswordUrl) ?>" class="sidebar-nav__item active" data-page="change-password">
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

    <main class="profile-main">
      <div class="loading-overlay" id="pageLoader">
        <div class="spinner"></div>
      </div>

      <div class="profile-error hidden" id="authError">
        <div class="alert alert--danger" id="authErrorMsg">
          You must be logged in to change your password.
        </div>

        <a href="<?= htmlspecialchars($loginUrl) ?>" class="btn btn--primary" style="margin-top: var(--space-4);">
          Log in
        </a>
      </div>

      <div class="cp-content hidden" id="cpContent">
        <div class="cp-page-header">
          <div class="cp-page-header__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28"
                 viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </div>

          <div>
            <h1 class="cp-page-header__title">Change Password</h1>
            <p class="cp-page-header__subtitle">Keep your account safe by using a strong password</p>
          </div>
        </div>

        <div class="cp-card">
          <div class="cp-tips">
            <span class="cp-tips__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                   viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
              </svg>
            </span>

            <div class="cp-tips__body">
              <p class="cp-tips__title">Password Requirements</p>
              <ul class="cp-tips__list">
                <li id="tip-length">
                  <span class="cp-tip-dot cp-tip-dot--neutral"></span>
                  At least 8 characters
                </li>
                <li id="tip-upper">
                  <span class="cp-tip-dot cp-tip-dot--neutral"></span>
                  At least one uppercase letter
                </li>
                <li id="tip-number">
                  <span class="cp-tip-dot cp-tip-dot--neutral"></span>
                  At least one number
                </li>
                <li id="tip-match">
                  <span class="cp-tip-dot cp-tip-dot--neutral"></span>
                  New passwords must match
                </li>
              </ul>
            </div>
          </div>

          <form class="cp-form" id="cpForm" novalidate>
            <div class="form-group">
              <label class="form-label" for="currentPassword">Current Password</label>

              <div class="cp-input-wrap">
                <span class="cp-input-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                       viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                  </svg>
                </span>

                <input
                  type="password"
                  class="form-input cp-input"
                  id="currentPassword"
                  placeholder="Enter your current password"
                  autocomplete="current-password"
                  required
                />

                <button type="button" class="cp-toggle-btn" data-target="currentPassword" aria-label="Toggle visibility">
                  <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                       viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>

                  <svg class="icon-eye-off hidden" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                       viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                </button>
              </div>

              <span class="form-error hidden" id="currentPasswordError"></span>
            </div>

            <div class="cp-divider">
              <span>New Password</span>
            </div>

            <div class="form-group">
              <label class="form-label" for="newPassword">New Password</label>

              <div class="cp-input-wrap">
                <span class="cp-input-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                       viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                  </svg>
                </span>

                <input
                  type="password"
                  class="form-input cp-input"
                  id="newPassword"
                  placeholder="Enter your new password"
                  autocomplete="new-password"
                  required
                />

                <button type="button" class="cp-toggle-btn" data-target="newPassword" aria-label="Toggle visibility">
                  <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                       viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>

                  <svg class="icon-eye-off hidden" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                       viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                </button>
              </div>

              <div class="cp-strength" id="strengthMeter">
                <div class="cp-strength__bar">
                  <div class="cp-strength__fill" id="strengthFill"></div>
                </div>

                <span class="cp-strength__label" id="strengthLabel"></span>
              </div>

              <span class="form-error hidden" id="newPasswordError"></span>
            </div>

            <div class="form-group">
              <label class="form-label" for="confirmPassword">Confirm New Password</label>

              <div class="cp-input-wrap">
                <span class="cp-input-icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                       viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                  </svg>
                </span>

                <input
                  type="password"
                  class="form-input cp-input"
                  id="confirmPassword"
                  placeholder="Re-enter your new password"
                  autocomplete="new-password"
                  required
                />

                <button type="button" class="cp-toggle-btn" data-target="confirmPassword" aria-label="Toggle visibility">
                  <svg class="icon-eye" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                       viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>

                  <svg class="icon-eye-off hidden" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                       viewBox="0 0 24 24" fill="none" stroke="currentColor"
                       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                  </svg>
                </button>
              </div>

              <span class="form-error hidden" id="confirmPasswordError"></span>
            </div>

            <div id="formAlert" class="hidden" style="margin-bottom: var(--space-4);"></div>

            <div class="cp-actions">
              <button type="submit" class="btn btn--primary btn--lg cp-submit-btn" id="submitBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>

                <span id="submitBtnText">Update Password</span>
                <div class="spinner cp-btn-spinner hidden" id="submitSpinner"></div>
              </button>

              <a href="<?= htmlspecialchars($profileUrl) ?>" class="btn btn--ghost btn--lg">
                Cancel
              </a>
            </div>
          </form>

          <div class="cp-success hidden" id="cpSuccess">
            <div class="cp-success__icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"
                   viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
              </svg>
            </div>

            <h2 class="cp-success__title">Password Updated!</h2>
            <p class="cp-success__desc">
              Your password has been changed successfully. Your current login session is still active.
            </p>

            <a href="<?= htmlspecialchars($profileUrl) ?>" class="btn btn--primary">
              Back to Profile
            </a>
          </div>
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
    window.CHANGE_PASSWORD_CONFIG = {
      changeProxyUrl: <?= json_encode($changeProxyUrl) ?>,
      loginUrl: <?= json_encode($loginUrl) ?>,
      profileUrl: <?= json_encode($profileUrl) ?>,
      ordersUrl: <?= json_encode($ordersUrl) ?>,
      changePasswordUrl: <?= json_encode($changePasswordUrl) ?>
    };
  </script>

  <script src="<?= htmlspecialchars($publicBasePath) ?>/assets/js/change_password.js?v=<?= time() ?>" defer></script>
</body>
</html>