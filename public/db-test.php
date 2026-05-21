<?php

require_once __DIR__ . '/../app/Core/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';

use App\Core\Config;
use App\Core\Database;

header('Content-Type: application/json; charset=utf-8');

Config::load(__DIR__ . '/../.env');
date_default_timezone_set(Config::get('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'));

$pdo = Database::getConnection();

$stmt = $pdo->query("
    SELECT id, name, email, phone, role, is_active
    FROM users
    ORDER BY id ASC
");

$users = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'message' => 'DATABASE_CONNECTED',
    'data' => [
        'users' => $users
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);