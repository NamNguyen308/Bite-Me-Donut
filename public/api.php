<?php

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Core\Config;
use App\Core\Request;
use App\Core\Router;

Config::load(__DIR__ . '/../.env');
date_default_timezone_set(Config::get('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'));

$router = new Router();

require_once __DIR__ . '/../routes/api.php';

$request = new Request();

$router->dispatch($request);