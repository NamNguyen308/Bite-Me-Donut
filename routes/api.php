<?php

use App\Core\Request;
use App\Core\Response;
use App\Controllers\AuthController;
use App\Controllers\OtpController;
use App\Controllers\UserController;
use App\Controllers\AdminController;
use App\Controllers\ProductController;
use App\Controllers\CartController;
use App\Controllers\OrderController;

/** @var App\Core\Router $router */

$router->get('/api/health', function (Request $request): void {
    Response::success('API_OK', [
        'service' => 'ecommerce_security_platform',
        'environment' => App\Core\Config::get('APP_ENV', 'local'),
        'time' => date('c')
    ]);
});

$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/otp/request', [OtpController::class, 'requestOtp']);
$router->post('/api/otp/verify', [OtpController::class, 'verifyOtp']);
$router->post('/api/auth/complete-login', [AuthController::class, 'completeLogin']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);

$router->get('/api/users/me', [UserController::class, 'me']);
$router->get('/api/users/{id}', [UserController::class, 'show']);

$router->get('/api/admin/dashboard', [AdminController::class, 'dashboard']);

$router->get('/api/products', [ProductController::class, 'index']);
$router->get('/api/products/{id}', [ProductController::class, 'show']);

$router->get('/api/cart', [CartController::class, 'index']);
$router->post('/api/cart/add', [CartController::class, 'add']);
$router->post('/api/cart/update', [CartController::class, 'update']);
$router->post('/api/cart/remove', [CartController::class, 'remove']);
$router->post('/api/cart/clear', [CartController::class, 'clear']);

$router->get('/api/orders', [OrderController::class, 'index']);
$router->post('/api/orders', [OrderController::class, 'store']);
$router->get('/api/orders/{id}', [OrderController::class, 'show']);