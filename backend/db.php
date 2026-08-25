<?php

declare(strict_types=1);

function envValue(string $name): ?string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return null;
    }
    return (string)$value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configFile = __DIR__ . '/config.php';
    $config = is_file($configFile) ? require $configFile : [];
    if (!is_array($config)) {
        throw new RuntimeException('backend/config.php must return an array.');
    }

    $db = is_array($config['db'] ?? null) ? $config['db'] : [];

    $host = envValue('DB_HOST') ?? (string)($db['host'] ?? '127.0.0.1');
    $port = (int)(envValue('DB_PORT') ?? (string)($db['port'] ?? 3306));
    $name = envValue('DB_NAME') ?? (string)($db['name'] ?? '');
    $user = envValue('DB_USER') ?? (string)($db['user'] ?? '');
    $pass = envValue('DB_PASSWORD') ?? (string)($db['pass'] ?? '');
    $charset = envValue('DB_CHARSET') ?? (string)($db['charset'] ?? 'utf8mb4');

    if ($name === '' || $user === '') {
        throw new RuntimeException(
            'Database configuration missing. Provide backend/config.php or DB_NAME/DB_USER environment variables.'
        );
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $host,
        $port,
        $name,
        $charset
    );

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
