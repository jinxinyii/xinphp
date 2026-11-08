<?php
declare(strict_types=1);

function database(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $environment = getenv('APP_ENV') ?: 'local';
    $isProduction = $environment === 'production';

    $host = getenv('DB_HOST') ?: ($isProduction ? null : '127.0.0.1');
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: ($isProduction ? null : 'xinphp_db');
    $user = getenv('DB_USER') ?: ($isProduction ? null : 'root');
    $password = getenv('DB_PASSWORD') ?: '';

    if (!$host || !$name || !$user) {
        throw new RuntimeException('Database configuration is incomplete.');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    } catch (PDOException $exception) {
        error_log('Database connection failed: ' . $exception->getMessage());

        throw new RuntimeException('Unable to connect to the database.');
    }
}