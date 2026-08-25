<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/audit.php';

$input = [
    'name' => 'Cliente Demo',
    'password' => 'secret',
    'nested' => [
        'token' => 'abc123',
        'status' => 'active',
    ],
];

$result = auditSanitize($input);

$checks = [
    $result['name'] === 'Cliente Demo',
    $result['password'] === '[REDACTED]',
    $result['nested']['token'] === '[REDACTED]',
    $result['nested']['status'] === 'active',
];

if (in_array(false, $checks, true)) {
    fwrite(STDERR, "Audit sanitizer regression failed\n");
    exit(1);
}

echo "AuditSanitizerTest OK\n";
