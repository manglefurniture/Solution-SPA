<?php

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configFile = __DIR__ . '/config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('Missing backend/config.php. Copy config.example.php and set credentials.');
    }

    $config = require $configFile;
    $db = $config['db'] ?? [];

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'] ?? '127.0.0.1',
        $db['port'] ?? 3306,
        $db['name'] ?? 'solution_spa',
        $db['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, (string)($db['user'] ?? ''), (string)($db['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
