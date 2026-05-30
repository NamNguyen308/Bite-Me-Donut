<?php
declare(strict_types=1);

$pageTitle = 'Login';

function loginProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

function loginLoadEnv(): array
{
    $envPath = loginProjectRoot() . DIRECTORY_SEPARATOR . '.env';
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

function loginEnv(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = loginLoadEnv();
    }

    return $env[$key] ?? $default;
}

function loginJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function loginReadJson(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        loginJson([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function loginAppBasePath(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

function loginScheme(): string
{
    $https = $_SERVER['HTTPS'] ?? '';
    return ($https !== '' && $https !== 'off') ? 'https' : 'http';
}

function loginApiBaseUrl(): string
{
    $appUrl = loginEnv('APP_URL', '');

    if ($appUrl !== null && trim($appUrl) !== '') {
        return rtrim(trim($appUrl), '/') . '/api';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return loginScheme() . '://' . $host . loginAppBasePath() . '/api';
}

function loginForwardPostToApi(string $endpoint, array $payload): void
{
    $url = loginApiBaseUrl() . $endpoint;
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($jsonPayload === false) {
        loginJson([
            'success' => false,
            'error_code' => 'JSON_ENCODE_ERROR',
            'message' => 'Cannot encode request payload'
        ], 500);
    }

    $ch = curl_init($url);

    if ($ch === false) {
        loginJson([
            'success' => false,
            'error_code' => 'CURL_INIT_FAILED',
            'message' => 'Cannot initialize cURL'
        ], 500);
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_TIMEOUT => 25
    ]);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($rawResponse === false) {
        loginJson([
            'success' => false,
            'error_code' => 'API_PROXY_CURL_ERROR',
            'message' => $curlError !== '' ? $curlError : 'Cannot call backend API',
            'api_url' => $url
        ], 502);
    }

    $decoded = json_decode($rawResponse, true);

    if (!is_array($decoded)) {
        loginJson([
            'success' => false,
            'error_code' => 'API_PROXY_INVALID_JSON',
            'message' => 'Backend API did not return valid JSON',
            'api_url' => $url,
            'raw_response' => $rawResponse
        ], 502);
    }

    http_response_code($httpCode > 0 ? $httpCode : 200);
    header('Content-Type: application/json; charset=utf-8');

    echo $rawResponse;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['login_ajax'] ?? '') === '1') {
    $input = loginReadJson();

    $identifier = trim((string)($input['identifier'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($identifier === '' || $password === '') {
        loginJson([
            'success' => false,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'Phone/email and password are required'
        ], 400);
    }

    $payload = [
        'password' => $password
    ];

    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        $payload['email'] = $identifier;
    } else {
        $payload['phone'] = preg_replace('/[\s\-\(\)]/', '', $identifier);
    }

    loginForwardPostToApi('/auth/login', $payload);
}

$appBasePath = loginAppBasePath();
$publicBasePath = $appBasePath . '/public';
$loginProxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?login_ajax=1';
$otpPageUrl = $appBasePath . '/views/auth/otp.php';
$forgotPasswordUrl = $appBasePath . '/views/auth/forgot_password.php';
$registerUrl = $appBasePath . '/views/auth/user-register.php';

require_once __DIR__ . '/../layouts/header.php';
?>

<link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/root.css">
<link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/user-login.css">

<main class="auth-page">
  <section class="auth-card" aria-labelledby="auth-title">

    <header class="auth-card__head">
      <span class="auth-card__logo" aria-hidden="true">
        <svg viewBox="0 0 48 48" width="48" height="48" role="img" aria-label="Bite-Me-Donut">
          <circle cx="24" cy="24" r="20" fill="var(--color-primary)"></circle>
          <circle cx="24" cy="24" r="7.5" fill="var(--color-bg-card)"></circle>
          <g fill="var(--color-bg-card)">
            <circle cx="24" cy="7.5" r="1.8"></circle>
            <circle cx="36" cy="13" r="1.8"></circle>
            <circle cx="40.5" cy="24" r="1.8"></circle>
            <circle cx="36" cy="35" r="1.8"></circle>
            <circle cx="12" cy="35" r="1.8"></circle>
            <circle cx="7.5" cy="24" r="1.8"></circle>
            <circle cx="12" cy="13" r="1.8"></circle>
          </g>
        </svg>
      </span>

      <h1 class="auth-card__title" id="auth-title">Login</h1>
      <p class="auth-card__subtitle">Welcome back to Bite-Me-Donut</p>
    </header>

    <div id="auth-alert" class="alert alert--danger hidden" role="alert" aria-live="polite"></div>

    <form id="login-form" class="auth-form" novalidate autocomplete="on">
      <div class="form-group">
        <label class="form-label" for="login-identifier">Phone Number or Email</label>

        <div class="input-wrap">
          <span class="input-wrap__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </span>

          <input
            type="text"
            id="login-identifier"
            name="login"
            class="form-input has-icon"
            placeholder="Enter your phone number or email"
            autocomplete="username"
            inputmode="text"
            required>
        </div>

        <p class="form-error hidden" data-error-for="login"></p>
      </div>

      <div class="form-group">
        <label class="form-label" for="login-password">Password</label>

        <div class="input-wrap">
          <span class="input-wrap__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
          </span>

          <input
            type="password"
            id="login-password"
            name="password"
            class="form-input has-icon has-toggle"
            placeholder="Enter your password"
            autocomplete="current-password"
            required>

          <button type="button" class="input-wrap__toggle" id="toggle-password"
                  aria-label="Show password" aria-pressed="false">
            <svg class="icon-eye" viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>

            <svg class="icon-eye-off hidden" viewBox="0 0 24 24" width="20" height="20" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
              <line x1="1" y1="1" x2="23" y2="23"></line>
            </svg>
          </button>
        </div>

        <p class="form-error hidden" data-error-for="password"></p>
      </div>

      <div class="auth-form__row">
        <a class="auth-link" href="<?= htmlspecialchars($forgotPasswordUrl) ?>">Forgot your password?</a>
      </div>

      <button type="submit" class="btn btn--primary btn--lg w-full" id="login-submit">
        <span class="btn-label">Login</span>
        <span class="spinner spinner--btn hidden" id="login-spinner" aria-hidden="true"></span>
      </button>
    </form>

    <footer class="auth-card__foot">
      <p class="text-muted text-center">
        Don't have an account?
        <a class="auth-link" href="<?= htmlspecialchars($registerUrl) ?>">Register now</a>
      </p>
    </footer>

  </section>
</main>

<script>
  window.USER_LOGIN_CONFIG = {
    loginProxyUrl: <?= json_encode($loginProxyUrl) ?>,
    otpPageUrl: <?= json_encode($otpPageUrl) ?>,
    adminDashboardUrl: <?= json_encode($appBasePath . '/views/admin/dashboard.php') ?>
  };
</script>

<script src="<?= htmlspecialchars($publicBasePath) ?>/assets/js/user-login.js?v=<?= time() ?>" defer></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>