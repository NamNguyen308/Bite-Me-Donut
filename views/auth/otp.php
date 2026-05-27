<?php
declare(strict_types=1);

$pageTitle = 'Verify OTP';

function otpProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

function otpLoadEnv(): array
{
    $envPath = otpProjectRoot() . DIRECTORY_SEPARATOR . '.env';
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

function otpEnv(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = otpLoadEnv();
    }

    return $env[$key] ?? $default;
}

function otpJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function otpReadJson(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        otpJson([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function otpAppBasePath(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

function otpScheme(): string
{
    $https = $_SERVER['HTTPS'] ?? '';
    return ($https !== '' && $https !== 'off') ? 'https' : 'http';
}

function otpApiBaseUrl(): string
{
    $appUrl = otpEnv('APP_URL', '');

    if ($appUrl !== null && trim($appUrl) !== '') {
        return rtrim(trim($appUrl), '/') . '/api';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return otpScheme() . '://' . $host . otpAppBasePath() . '/api';
}

function otpForwardPostToApi(string $endpoint, array $payload): void
{
    $url = otpApiBaseUrl() . $endpoint;
    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($jsonPayload === false) {
        otpJson([
            'success' => false,
            'error_code' => 'JSON_ENCODE_ERROR',
            'message' => 'Cannot encode request payload'
        ], 500);
    }

    if (!function_exists('curl_init')) {
        otpJson([
            'success' => false,
            'error_code' => 'CURL_NOT_AVAILABLE',
            'message' => 'PHP cURL extension is not available'
        ], 500);
    }

    $ch = curl_init($url);

    if ($ch === false) {
        otpJson([
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
        CURLOPT_TIMEOUT => 30
    ]);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($rawResponse === false) {
        otpJson([
            'success' => false,
            'error_code' => 'API_PROXY_CURL_ERROR',
            'message' => $curlError !== '' ? $curlError : 'Cannot call backend API',
            'api_url' => $url
        ], 502);
    }

    $decoded = json_decode($rawResponse, true);

    if (!is_array($decoded)) {
        otpJson([
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['otp_ajax'] ?? '') === '1') {
    $input = otpReadJson();
    $action = $input['action'] ?? '';

    $challengeId = trim((string)($input['login_challenge_id'] ?? ''));

    if ($challengeId === '') {
        otpJson([
            'success' => false,
            'error_code' => 'VALIDATION_ERROR',
            'message' => 'login_challenge_id is required'
        ], 400);
    }

    if ($action === 'request_otp') {
        otpForwardPostToApi('/otp/request', [
            'login_challenge_id' => $challengeId
        ]);
    }

    if ($action === 'verify_otp') {
        $otpCode = trim((string)($input['otp_code'] ?? ''));

        otpForwardPostToApi('/otp/verify', [
            'login_challenge_id' => $challengeId,
            'otp_code' => $otpCode
        ]);
    }

    if ($action === 'complete_login') {
        otpForwardPostToApi('/auth/complete-login', [
            'login_challenge_id' => $challengeId
        ]);
    }

    otpJson([
        'success' => false,
        'error_code' => 'INVALID_ACTION',
        'message' => 'Invalid OTP action'
    ], 400);
}

$appBasePath = otpAppBasePath();
$publicBasePath = $appBasePath . '/public';

$otpProxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?otp_ajax=1';
$loginUrl = $appBasePath . '/views/auth/user-login.php';
$homeUrl = $appBasePath . '/views/user/home.php';

require_once __DIR__ . '/../layouts/header.php';
?>

<link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/root.css">
<link rel="stylesheet" href="<?= htmlspecialchars($publicBasePath) ?>/assets/css/otp.css">

<main class="auth-page">
  <section class="otp-card" aria-labelledby="otp-title">

    <header class="auth-card__head">
      <span class="auth-card__logo" aria-hidden="true">
        <svg viewBox="0 0 48 48" width="48" height="48" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="8" y="12" width="32" height="28" rx="6" fill="var(--color-primary)" opacity="0.12"/>
          <rect x="8" y="12" width="32" height="28" rx="6" stroke="var(--color-primary)" stroke-width="2.5"/>
          <path d="M16 22h16M16 28h10" stroke="var(--color-primary)" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
      </span>

      <h1 class="auth-card__title" id="otp-title">OTP Verification</h1>
      <p class="auth-card__subtitle">
        We will call your phone and read a 6-digit verification code.
      </p>
    </header>

    <div id="otp-alert" class="alert alert--danger hidden" role="alert" aria-live="polite"></div>

    <div id="call-status-banner" class="call-status-banner call-status-banner--calling">
      <span id="call-status-text">Preparing verification call…</span>
    </div>

    <div id="otp-countdown-wrap" class="otp-countdown-wrap hidden">
      Code expires in <strong id="otp-countdown">05:00</strong>
    </div>

    <form id="otp-form" class="otp-form" novalidate autocomplete="off">
      <div class="otp-inputs" aria-label="Enter 6-digit OTP">
        <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 1">
        <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 2">
        <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 3">
        <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 4">
        <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 5">
        <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 6">
      </div>

      <button type="submit" class="btn btn--primary btn--lg w-full" id="otp-submit" disabled>
        <span class="btn-label">Verify OTP</span>
        <span class="spinner spinner--btn hidden" id="otp-spinner" aria-hidden="true"></span>
      </button>
    </form>

    <button type="button" class="btn btn--secondary w-full hidden" id="resend-btn">
      Request another call
    </button>

    <footer class="auth-card__foot">
      <p class="text-muted text-center">
        Wrong account?
        <a class="auth-link" href="<?= htmlspecialchars($loginUrl) ?>">Back to Login</a>
      </p>
    </footer>

  </section>
</main>

<script>
  window.OTP_CONFIG = {
    proxyUrl: <?= json_encode($otpProxyUrl) ?>,
    loginUrl: <?= json_encode($loginUrl) ?>,
    homeUrl: <?= json_encode($homeUrl) ?>
  };
</script>

<script src="<?= htmlspecialchars($publicBasePath) ?>/assets/js/otp.js" defer></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>