<?php

declare(strict_types=1);

return [
    // Local fallback configuration. Production/staging may override database
    // values with DB_* environment variables without changing this file.
    'app_env' => 'development',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'solution_spa',
        'user' => 'solution_spa',
        'pass' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
];
