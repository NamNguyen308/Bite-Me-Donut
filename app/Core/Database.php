<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::get('DB_PORT', '3306');
        $database = Config::get('DB_NAME', 'ecommerce_security_platform');
        $username = Config::get('DB_USER', 'root');
        $password = Config::get('DB_PASS', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        try {
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return self::$connection;
        } catch (PDOException $e) {
            http_response_code(500);

            echo json_encode([
                'success' => false,
                'error_code' => 'DATABASE_CONNECTION_ERROR',
                'message' => 'Cannot connect to database',
                'detail' => $e->getMessage()
            ]);

            exit;
        }
    }
}