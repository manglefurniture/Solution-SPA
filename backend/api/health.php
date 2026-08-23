<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    db()->query('SELECT 1');
    respond(['ok' => true, 'database' => 'connected']);
} catch (Throwable $e) {
    respond(['ok' => false, 'database' => 'unavailable'], 503);
}
