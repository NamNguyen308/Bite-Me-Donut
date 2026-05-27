<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Forgot Password';

function fpProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

function fpLoadEnv(): array
{
    $envPath = fpProjectRoot() . DIRECTORY_SEPARATOR . '.env';
    $env = [];

    if (!is_file($envPath)) {
        return $env;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);

        $env[trim($key)] = trim(trim($value), "\"'");
    }

    return $env;
}

function fpEnv(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = fpLoadEnv();
    }

    return $env[$key] ?? $default;
}

function fpDb(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = fpEnv('DB_HOST', '127.0.0.1');
    $port = fpEnv('DB_PORT', '3306');
    $name = fpEnv('DB_NAME', 'ecommerce_security_platform');
    $user = fpEnv('DB_USER', 'root');
    $pass = fpEnv('DB_PASS', '');

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function fpJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fpReadJson(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        fpJson([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function fpNormalizePhone(string $phone): string
{
    return preg_replace('/[\s\-\(\)]/', '', trim($phone));
}

function fpAppBasePath(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

/**
 * AJAX handler.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['forgot_password_ajax'] ?? '') === '1') {
    try {
        $input = fpReadJson();
        $action = $input['action'] ?? '';

        if ($action === 'verify_identity') {
            $email = trim((string)($input['email'] ?? ''));
            $phone = fpNormalizePhone((string)($input['phone'] ?? ''));

            $errors = [];

            if ($email === '') {
                $errors['email'] = 'Email is required';
            }

            if ($phone === '') {
                $errors['phone'] = 'Phone is required';
            }

            if (!empty($errors)) {
                fpJson([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                    'errors' => $errors
                ], 400);
            }

            $stmt = fpDb()->prepare("
                SELECT id, email, phone, is_active
                FROM users
                WHERE email = :email
                  AND phone = :phone
                LIMIT 1
            ");

            $stmt->execute([
                'email' => $email,
                'phone' => $phone,
            ]);

            $user = $stmt->fetch();

            if (!$user) {
                fpJson([
                    'success' => false,
                    'error_code' => 'USER_NOT_FOUND',
                    'message' => 'No account found with that email and phone combination'
                ], 404);
            }

            if ((int)$user['is_active'] !== 1) {
                fpJson([
                    'success' => false,
                    'error_code' => 'ACCOUNT_INACTIVE',
                    'message' => 'This account is inactive'
                ], 403);
            }

            $resetToken = bin2hex(random_bytes(32));

            $_SESSION['forgot_password'] = [
                'user_id' => (int)$user['id'],
                'token_hash' => hash('sha256', $resetToken),
                'expires_at' => time() + 600,
            ];

            fpJson([
                'success' => true,
                'message' => 'IDENTITY_VERIFIED',
                'data' => [
                    'reset_token' => $resetToken,
                    'expires_in' => 600
                ]
            ]);
        }

        if ($action === 'reset_password') {
            $resetToken = trim((string)($input['reset_token'] ?? ''));
            $newPassword = (string)($input['new_password'] ?? '');

            if ($resetToken === '' || $newPassword === '') {
                fpJson([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Reset token and new password are required'
                ], 400);
            }

            if (strlen($newPassword) < 6) {
                fpJson([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Password must be at least 6 characters'
                ], 400);
            }

            $sessionReset = $_SESSION['forgot_password'] ?? null;

            if (!is_array($sessionReset)) {
                fpJson([
                    'success' => false,
                    'error_code' => 'INVALID_RESET_TOKEN',
                    'message' => 'Your session has expired. Please start over.'
                ], 400);
            }

            if ((int)$sessionReset['expires_at'] < time()) {
                unset($_SESSION['forgot_password']);

                fpJson([
                    'success' => false,
                    'error_code' => 'INVALID_RESET_TOKEN',
                    'message' => 'Your session has expired. Please start over.'
                ], 400);
            }

            $expectedHash = (string)$sessionReset['token_hash'];
            $actualHash = hash('sha256', $resetToken);

            if (!hash_equals($expectedHash, $actualHash)) {
                fpJson([
                    'success' => false,
                    'error_code' => 'INVALID_RESET_TOKEN',
                    'message' => 'Invalid reset token'
                ], 400);
            }

            $userId = (int)$sessionReset['user_id'];
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = fpDb()->prepare("
                UPDATE users
                SET password_hash = :password_hash,
                    updated_at = NOW()
                WHERE id = :id
                LIMIT 1
            ");

            $stmt->execute([
                'password_hash' => $passwordHash,
                'id' => $userId,
            ]);

            unset($_SESSION['forgot_password']);

            fpJson([
                'success' => true,
                'message' => 'PASSWORD_RESET_SUCCESS',
                'data' => []
            ]);
        }

        fpJson([
            'success' => false,
            'error_code' => 'INVALID_ACTION',
            'message' => 'Invalid action'
        ], 400);
    } catch (Throwable $e) {
        fpJson([
            'success' => false,
            'error_code' => 'SERVER_ERROR',
            'message' => $e->getMessage()
        ], 500);
    }
}

$appBasePath = fpAppBasePath();
$publicBasePath = $appBasePath . '/public';
$proxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?forgot_password_ajax=1';
$loginUrl = $appBasePath . '/views/auth/user-login.php';

require_once __DIR__ . '/../layouts/header.php';
?>

<link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/root.css">
<link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/forgot_password.css">

<main class="auth-page">
  <section class="auth-card" aria-labelledby="auth-title">

    <header class="auth-card__head">
      <span class="auth-card__logo" aria-hidden="true">
        <svg viewBox="0 0 48 48" width="48" height="48" fill="none"
             xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Reset password">
          <rect x="8" y="22" width="32" height="20" rx="4"
                fill="var(--color-primary)" opacity="0.15"/>
          <rect x="8" y="22" width="32" height="20" rx="4"
                stroke="var(--color-primary)" stroke-width="2.5" fill="none"/>
          <path d="M16 22v-6a8 8 0 0 1 16 0" stroke="var(--color-primary)"
                stroke-width="2.5" stroke-linecap="round" fill="none"/>
          <circle cx="24" cy="32" r="3" fill="var(--color-primary)"/>
          <rect x="23" y="32" width="2" height="5" rx="1" fill="var(--color-primary)"/>
        </svg>
      </span>

      <h1 class="auth-card__title" id="auth-title">Forgot Password</h1>

      <p class="auth-card__subtitle" id="auth-subtitle">
        Enter your email and phone number to reset your password
      </p>
    </header>

    <div class="step-indicator" aria-label="Progress">
      <div class="step-indicator__track">
        <div class="step-dot step-dot--active" data-step="1">
          <span>1</span>
        </div>

        <div class="step-line" id="step-line"></div>

        <div class="step-dot" data-step="2">
          <span>2</span>
        </div>
      </div>

      <div class="step-indicator__labels">
        <span class="step-label step-label--active" id="label-1">Verify Identity</span>
        <span class="step-label" id="label-2">Reset Password</span>
      </div>
    </div>

    <div id="auth-alert" class="alert alert--danger hidden" role="alert" aria-live="polite"></div>

    <form id="verify-form" class="auth-form" novalidate autocomplete="on">
      <div class="form-group">
        <label class="form-label" for="fp-email">Email Address</label>

        <div class="input-wrap">
          <span class="input-wrap__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
          </span>

          <input
            type="email"
            id="fp-email"
            name="email"
            class="form-input has-icon"
            placeholder="Enter your email address"
            autocomplete="email"
            inputmode="email"
            required>
        </div>

        <p class="form-error hidden" data-error-for="email"></p>
      </div>

      <div class="form-group">
        <label class="form-label" for="fp-phone">Phone Number</label>

        <div class="input-wrap">
          <span class="input-wrap__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2
                       19.79-19.79 0 0 1-8.63-3.07
                       19.5 19.5 0 0 1-6-6
                       19.79 19.79 0 0 1-3.07-8.67
                       A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72
                       12.84 12.84 0 0 0 .7 2.81
                       2 2 0 0 1-.45 2.11L8.09 9.91
                       a16 16 0 0 0 6 6l1.27-1.27
                       a2 2 0 0 1 2.11-.45
                       12.84 12.84 0 0 0 2.81.7
                       A2 2 0 0 1 22 16.92z"/>
            </svg>
          </span>

          <input
            type="tel"
            id="fp-phone"
            name="phone"
            class="form-input has-icon"
            placeholder="Enter your phone number"
            autocomplete="tel"
            inputmode="tel"
            required>
        </div>

        <p class="form-error hidden" data-error-for="phone"></p>
      </div>

      <button type="submit" class="btn btn--primary btn--lg w-full" id="verify-submit">
        <span class="btn-label">Verify Identity</span>
        <span class="spinner spinner--btn hidden" aria-hidden="true"></span>
      </button>
    </form>

    <form id="reset-form" class="auth-form hidden" novalidate autocomplete="off">
      <div class="identity-verified-badge">
        <svg viewBox="0 0 20 20" width="18" height="18" fill="none"
             stroke="var(--color-success)" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 6L9 17l-5-5"/>
        </svg>

        <span>Identity verified — set your new password below</span>
      </div>

      <div class="form-group">
        <label class="form-label" for="fp-new-password">New Password</label>

        <div class="input-wrap">
          <span class="input-wrap__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>

          <input
            type="password"
            id="fp-new-password"
            name="new_password"
            class="form-input has-icon has-toggle"
            placeholder="Enter new password (min. 6 characters)"
            autocomplete="new-password"
            minlength="6"
            required>

          <button type="button" class="input-wrap__toggle" data-toggle-for="fp-new-password"
                  aria-label="Show password" aria-pressed="false">
            <svg class="icon-eye" viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>

            <svg class="icon-eye-off hidden" viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </button>
        </div>

        <p class="form-error hidden" data-error-for="new_password"></p>
      </div>

      <div class="password-strength" id="password-strength" aria-hidden="true">
        <div class="password-strength__track">
          <div class="password-strength__bar" id="strength-bar"></div>
        </div>

        <span class="password-strength__label" id="strength-label"></span>
      </div>

      <div class="form-group">
        <label class="form-label" for="fp-confirm-password">Confirm New Password</label>

        <div class="input-wrap">
          <span class="input-wrap__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 12l2 2 4-4"/>
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>

          <input
            type="password"
            id="fp-confirm-password"
            name="confirm_password"
            class="form-input has-icon has-toggle"
            placeholder="Re-enter your new password"
            autocomplete="new-password"
            required>

          <button type="button" class="input-wrap__toggle" data-toggle-for="fp-confirm-password"
                  aria-label="Show confirm password" aria-pressed="false">
            <svg class="icon-eye" viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>

            <svg class="icon-eye-off hidden" viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </button>
        </div>

        <p class="form-error hidden" data-error-for="confirm_password"></p>
      </div>

      <button type="submit" class="btn btn--primary btn--lg w-full" id="reset-submit">
        <span class="btn-label">Reset Password</span>
        <span class="spinner spinner--btn hidden" aria-hidden="true"></span>
      </button>
    </form>

    <footer class="auth-card__foot">
      <p class="text-muted text-center">
        Remember your password?
        <a class="auth-link" href="<?= htmlspecialchars($loginUrl) ?>">Back to Login</a>
      </p>
    </footer>

  </section>
</main>

<script>
  window.FORGOT_PASSWORD_CONFIG = {
    proxyUrl: <?= json_encode($proxyUrl) ?>,
    loginUrl: <?= json_encode($loginUrl) ?>
  };
</script>

<script src="<?= htmlspecialchars($publicBasePath) ?>/assets/js/forgot_password.js" defer></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>