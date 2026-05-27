<?php
declare(strict_types=1);

function homeProjectRoot(): string
{
    return dirname(__DIR__, 2);
}

function homeLoadEnv(): array
{
    $envPath = homeProjectRoot() . DIRECTORY_SEPARATOR . '.env';
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

function homeEnv(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = homeLoadEnv();
    }

    return $env[$key] ?? $default;
}

function homeAppBasePath(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = preg_replace('#/(views|public)/.*$#', '', $scriptName);

    if ($basePath === null || $basePath === $scriptName) {
        return '';
    }

    return rtrim($basePath, '/');
}

function homeScheme(): string
{
    $https = $_SERVER['HTTPS'] ?? '';
    return ($https !== '' && $https !== 'off') ? 'https' : 'http';
}

function homeApiBaseUrl(): string
{
    $appUrl = homeEnv('APP_URL', '');

    if ($appUrl !== null && trim($appUrl) !== '') {
        return rtrim(trim($appUrl), '/') . '/api';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return homeScheme() . '://' . $host . homeAppBasePath() . '/api';
}

function homeJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function homeReadJson(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        homeJson([
            'success' => false,
            'error_code' => 'INVALID_JSON',
            'message' => 'Invalid JSON request body'
        ], 400);
    }

    return $data;
}

function homeForwardToApi(string $method, string $endpoint, string $token, array $payload = []): void
{
    $url = homeApiBaseUrl() . $endpoint;

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
        homeJson([
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
        homeJson([
            'success' => false,
            'error_code' => 'API_PROXY_CURL_ERROR',
            'message' => $curlError !== '' ? $curlError : 'Cannot call backend API',
            'api_url' => $url
        ], 502);
    }

    $decoded = json_decode($rawResponse, true);

    if (!is_array($decoded)) {
        homeJson([
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['home_ajax'] ?? '') === '1') {
    $input = homeReadJson();

    $action = $input['action'] ?? '';
    $token = trim((string)($input['access_token'] ?? ''));

    if ($token === '') {
        homeJson([
            'success' => false,
            'error_code' => 'UNAUTHENTICATED',
            'message' => 'Access token is required'
        ], 401);
    }

    if ($action === 'me') {
        homeForwardToApi('GET', '/users/me', $token);
    }

    if ($action === 'logout') {
        homeForwardToApi('POST', '/auth/logout', $token, []);
    }

    homeJson([
        'success' => false,
        'error_code' => 'INVALID_ACTION',
        'message' => 'Invalid home action'
    ], 400);
}

$appBasePath = homeAppBasePath();
$publicBasePath = $appBasePath . '/public';

$homeProxyUrl = ($_SERVER['SCRIPT_NAME'] ?? '') . '?home_ajax=1';
$loginUrl = $appBasePath . '/views/auth/user-login.php';
$homeUrl = $appBasePath . '/views/user/home.php';
$accountAnchorUrl = $homeUrl . '#account';
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Bite-Me Donuts — Baked Fresh, Delivered Sweet</title>
  <meta name="description" content="Handcrafted donuts made fresh every day. Order online and get them delivered to your door or pick up in-store." />
  <link rel="stylesheet" href="../../public/assets/css/root.css" />
  <link rel="stylesheet" href="../../public/assets/css/home.css" />
</head>
<body>

  <?php include __DIR__ . '/../layouts/header.php'; ?>

  <!-- ======================================================
       1. HERO
  ====================================================== -->
  <section class="home-hero" aria-label="Hero banner">

    <!-- Left: text -->
    <div class="home-hero__content">
      <span class="home-hero__eyebrow">
        <span class="home-hero__eyebrow-dot" aria-hidden="true"></span>
        Fresh baked daily in Ho Chi Minh City
      </span>

      <h1 class="home-hero__title">
        Life is Short,
        <span class="home-hero__title-accent">Eat the Donut.</span>
      </h1>

      <p class="home-hero__desc">
        Handcrafted with real ingredients, zero preservatives, and an unreasonable amount of love.
        Pick up in-store or get them delivered while they're still warm.
      </p>

      <div class="home-hero__actions">
        <a href="../../views/user/products.php" class="btn btn--primary btn--lg">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;" aria-hidden="true">
            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
          </svg>
          Order Now
        </a>
      </div>

      <div class="home-hero__trust" aria-label="Key stats">
        <div class="home-hero__trust-item">
          <span class="home-hero__trust-num">50+</span>
          <span class="home-hero__trust-label">Flavors</span>
        </div>
        <div class="home-hero__trust-sep" aria-hidden="true"></div>
        <div class="home-hero__trust-item">
          <span class="home-hero__trust-num">5K+</span>
          <span class="home-hero__trust-label">Happy Customers</span>
        </div>
        <div class="home-hero__trust-sep" aria-hidden="true"></div>
        <div class="home-hero__trust-item">
          <span class="home-hero__trust-num">100%</span>
          <span class="home-hero__trust-label">Fresh Daily</span>
        </div>
        <div class="home-hero__trust-sep" aria-hidden="true"></div>
        <div class="home-hero__trust-item">
          <span class="home-hero__trust-num">4.9</span>
          <span class="home-hero__trust-label">Rating</span>
        </div>
      </div>
    </div>

    <!-- Right: image -->
    <div class="home-hero__visual">
      <img
        class="home-hero__main-img"
        src="../../public/assets/img/home1.jpg"
        alt="Assorted fresh Bite-Me donuts on display"
        width="900"
        height="700"
        loading="eager"
      />

      <div class="home-hero__pill" aria-hidden="true">
        New: Matcha Cloud
      </div>

      <div class="home-hero__badge">
        <div class="home-hero__badge-icon" aria-hidden="true">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
          </svg>
        </div>
        <div class="home-hero__badge-text">
          <strong>Free Delivery</strong>
          <span>On orders of 12+ donuts</span>
        </div>
      </div>
    </div>

  </section>

  <!-- ======================================================
       2. MARQUEE TICKER
  ====================================================== -->
  <div class="home-ticker" aria-hidden="true">
    <div class="home-ticker__track">
      <?php
      $tickerItems = [
        'Fresh Glazed',
        'Strawberry Dream',
        'Matcha Cloud',
        'Classic Chocolate',
        'Salted Caramel',
        'Blueberry Bliss',
        'Ube Swirl',
        'Lemon Zest',
        'Custom Boxes Available',
        'Free Delivery on 12+',
      ];
      // Duplicate for seamless loop
      $allItems = array_merge($tickerItems, $tickerItems);
      foreach ($allItems as $item): ?>
        <span class="home-ticker__item">
          <span class="home-ticker__dot"></span>
          <?= htmlspecialchars($item) ?>
        </span>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ======================================================
       3. ABOUT / STORY
  ====================================================== -->
  <section class="home-about section" aria-labelledby="about-heading">
    <div class="container">
      <div class="home-about__layout">

        <!-- Image collage -->
        <div class="home-about__images" aria-hidden="true">
          <img class="home-about__img-main"  src="../../public/assets/img/home2.jpg" alt="Our team hand-decorating donuts" width="480" height="400" loading="lazy" />
          <img class="home-about__img-secondary" src="../../public/assets/img/home3.jpg" alt="Close-up of a glazed donut" width="280" height="280" loading="lazy" />
          <img class="home-about__img-accent" src="../../public/assets/img/home4.jpg" alt="Donut sprinkles detail" width="100" height="100" loading="lazy" />
          <div class="home-about__years-badge" aria-label="5 years of sweetness">
            <span class="home-about__years-badge-num">5</span>
            <span class="home-about__years-badge-text">Years of<br>Sweetness</span>
          </div>
        </div>

        <!-- Text -->
        <div class="home-about__text">
          <span class="home-about__eyebrow" id="about-heading">Our Story</span>
          <h2 class="home-about__title">Made with Real Ingredients, Not Just Promises</h2>
          <p class="home-about__desc">
            Bite-Me Donuts started in a tiny kitchen in District 1 with one mission: make the best donut in Saigon.
            No shortcuts. No artificial dyes. No day-old stock. Just dough that we proof overnight, glaze that we make from scratch, and fillings you can actually taste.
          </p>
          <p class="home-about__desc">
            Five years and 5,000+ happy customers later, we still bake every single batch by hand — and we always will.
          </p>

          <div class="home-about__values">
            <div class="home-about__value-item">
              <div class="home-about__value-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
              </div>
              <div class="home-about__value-text">
                <strong>Premium Quality</strong>
                <p>Only real butter, farm eggs, and natural flavors.</p>
              </div>
            </div>
            <div class="home-about__value-item">
              <div class="home-about__value-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
              </div>
              <div class="home-about__value-text">
                <strong>Baked Fresh Daily</strong>
                <p>Every donut is made the morning of — never frozen.</p>
              </div>
            </div>
            <div class="home-about__value-item">
              <div class="home-about__value-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
              </div>
              <div class="home-about__value-text">
                <strong>Safe Packaging</strong>
                <p>Cushioned pink boxes — no squished glazes on delivery.</p>
              </div>
            </div>
            <div class="home-about__value-item">
              <div class="home-about__value-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
              </div>
              <div class="home-about__value-text">
                <strong>Made with Love</strong>
                <p>A small team that genuinely cares about every bite.</p>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>


  <!-- ======================================================
       6. FEATURE BANNER — Custom Orders
  ====================================================== -->
  <section class="home-banner" aria-labelledby="banner-heading">
    <img
      class="home-banner__bg"
      src="../../public/assets/img/home5.jpg"
      alt=""
      aria-hidden="true"
      width="1440"
      height="420"
      loading="lazy"
    />
    <div class="home-banner__overlay" aria-hidden="true"></div>
    <div class="container" style="position:relative; z-index:2; width:100%;">
      <div class="home-banner__content">
        <p class="home-banner__eyebrow">Custom Orders</p>
        <h2 class="home-banner__title" id="banner-heading">Build Your Dream Donut Box</h2>
        <p class="home-banner__desc">
          Birthdays, weddings, team parties — we craft custom boxes, towers, and branded collections
          that taste as incredible as they look. Minimum 12 donuts, fully customisable. 
        </p>
        <div style="display:flex; gap:var(--space-4); flex-wrap:wrap;">
          <a href="../../views/user/products.php" class="btn btn--outline btn--lg">Request a Custom Box</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ======================================================
       7. HOW IT WORKS
  ====================================================== -->
  <section class="home-steps" aria-labelledby="steps-heading">
    <div class="container">
      <div class="home-steps__header">
        <span class="home-steps__eyebrow">How It Works</span>
        <h2 class="home-steps__title" id="steps-heading">From Our Oven to Your Door</h2>
      </div>

      <div class="home-steps__grid">
        <?php
        $steps = [
          [
            'num'   => 1,
            'title' => 'Pick Your Donuts',
            'desc'  => 'Browse our menu and choose your favorites — or let us surprise you with a mystery box.',
            'svg'   => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
          ],
          [
            'num'   => 2,
            'title' => 'Place Your Order',
            'desc'  => 'Check out securely online. Pay by card, ATM transfer, or cash on delivery.',
            'svg'   => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
          ],
          [
            'num'   => 3,
            'title' => 'We Bake Fresh',
            'desc'  => 'Your order triggers our kitchen. Every donut is made fresh on the day — never pre-made.',
            'svg'   => '<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>',
          ],
          [
            'num'   => 4,
            'title' => 'Enjoy!',
            'desc'  => 'Pick up in-store or receive doorstep delivery in our signature pink box.',
            'svg'   => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
          ],
        ];
        foreach ($steps as $step): ?>
        <div class="home-step">
          <div class="home-step__num-wrap" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <?= $step['svg'] ?>
            </svg>
            <span class="home-step__num-badge"><?= $step['num'] ?></span>
          </div>
          <h3 class="home-step__title"><?= htmlspecialchars($step['title']) ?></h3>
          <p class="home-step__desc"><?= htmlspecialchars($step['desc']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ======================================================
       8. GALLERY MOSAIC
  ====================================================== -->
  <section class="home-gallery" aria-labelledby="gallery-heading">
    <div class="container">
      <div class="home-gallery__header">
        <span class="home-gallery__eyebrow">Donut Gallery</span>
        <h2 class="home-gallery__title" id="gallery-heading">Too Pretty to Eat? We Dare You.</h2>
        <p class="home-gallery__sub">Tag us @bitemedonuts for a chance to be featured here.</p>
      </div>

      <div class="home-gallery__mosaic" role="list" aria-label="Photo gallery">
        <!-- cell 1: tall (spans 2 rows) -->
        <div class="home-gallery__cell home-gallery__cell--tall" role="listitem">
          <img class="home-gallery__img" src="../../public/assets/img/home6.jpg" alt="Tower of assorted donuts" width="320" height="460" loading="lazy" />
          <div class="home-gallery__cell-overlay" aria-hidden="true">
            
          </div>
        </div>
        <!-- cell 2 -->
        <div class="home-gallery__cell" role="listitem">
          <img class="home-gallery__img" src="../../public/assets/img/home7.jpg" alt="Pink glazed donut close-up" width="320" height="220" loading="lazy" />
          <div class="home-gallery__cell-overlay" aria-hidden="true">
            
          </div>
        </div>
        <!-- cell 3: wide (spans 2 cols) -->
        <div class="home-gallery__cell home-gallery__cell--wide" role="listitem">
          <img class="home-gallery__img" src="../../public/assets/img/home8.jpg" alt="Full donut menu flat-lay" width="640" height="220" loading="lazy" />
          <div class="home-gallery__cell-overlay" aria-hidden="true">
            
          </div>
        </div>
        <!-- cell 4 -->
        <div class="home-gallery__cell" role="listitem">
          <img class="home-gallery__img" src="../../public/assets/img/home9.jpg" alt="Chocolate frosted donut with sprinkles" width="320" height="220" loading="lazy" />
          <div class="home-gallery__cell-overlay" aria-hidden="true">
            
          </div>
        </div>
        <!-- cell 5 -->
        <div class="home-gallery__cell" role="listitem">
          <img class="home-gallery__img" src="../../public/assets/img/home10.jpg" alt="Matcha glazed donut" width="320" height="220" loading="lazy" />
          <div class="home-gallery__cell-overlay" aria-hidden="true">
            
          </div>
        </div>
        <!-- cell 6 -->
        <div class="home-gallery__cell" role="listitem">
          <img class="home-gallery__img" src="../../public/assets/img/home11.jpg" alt="Donut gift box packaging" width="320" height="220" loading="lazy" />
          <div class="home-gallery__cell-overlay" aria-hidden="true">
          </div>
        </div>
      </div><!-- /.home-gallery__mosaic -->
    </div>
  </section>

  <!-- ======================================================
       9. TESTIMONIALS
  ====================================================== -->
  <section class="home-reviews" aria-labelledby="reviews-heading">
    <div class="container">
      <div class="home-reviews__header">
        <span class="home-reviews__eyebrow">Customer Love</span>
        <h2 class="home-reviews__title" id="reviews-heading">Don't Take Our Word for It</h2>
      </div>

      <div class="home-reviews__grid">
        <?php
        $reviews = [
          [
            'quote'  => 'I ordered a custom birthday box for my daughter and it was absolutely stunning. Everyone at the party couldn\'t stop talking about how good they tasted.',
            'name'   => 'Linh T.',
            'label'  => 'Regular customer since 2022',
            'stars'  => 5,
          ],
          [
            'quote'  => 'Best donuts in Saigon, no contest. The matcha glaze is unlike anything I\'ve had elsewhere. Fresh, soft, and not too sweet. Will order every week.',
            'name'   => 'Marco R.',
            'label'  => 'Food blogger',
            'stars'  => 5,
          ],
          [
            'quote'  => 'Delivery was fast and the packaging kept everything in perfect shape. The salted caramel filled one is criminally good. 10/10 would recommend.',
            'name'   => 'Phuong N.',
            'label'  => 'Verified buyer',
            'stars'  => 5,
          ],
        ];
        // Star SVG path
        $starPath = '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>';
        foreach ($reviews as $review): ?>
        <article class="home-review-card">
          <div class="home-review-card__stars" aria-label="<?= $review['stars'] ?> out of 5 stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="<?= $i <= $review['stars'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <?= $starPath ?>
            </svg>
            <?php endfor; ?>
          </div>
          <blockquote class="home-review-card__quote">"<?= htmlspecialchars($review['quote']) ?>"</blockquote>
          <div class="home-review-card__author">
            <img
              class="home-review-card__avatar"
              src="../../public/assets/img/home12.jpg"
              alt="Photo of <?= htmlspecialchars($review['name']) ?>"
              width="44"
              height="44"
              loading="lazy"
              aria-hidden="true"
            />
            <div>
              <p class="home-review-card__author-name"><?= htmlspecialchars($review['name']) ?></p>
              <p class="home-review-card__author-label"><?= htmlspecialchars($review['label']) ?></p>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php include __DIR__ . '/../layouts/footer.php'; ?>

  <!-- ======================================================
       JAVASCRIPT
  ====================================================== -->
  

  <script>
  window.HOME_CONFIG = {
    homeProxyUrl: <?= json_encode($homeProxyUrl) ?>,
    loginUrl: <?= json_encode($loginUrl) ?>,
    homeUrl: <?= json_encode($homeUrl) ?>,
    accountUrl: <?= json_encode($accountAnchorUrl) ?>
  };
</script>

<script src="<?= htmlspecialchars($publicBasePath) ?>/assets/js/home.js?v=<?= time() ?>" defer></script>

</body>
</html>