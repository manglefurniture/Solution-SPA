<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/audit.php';

function auditAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$input = [
    'name' => 'Cliente Demo',
    'email' => 'cliente@example.test',
    'phone' => '+529981234567',
    'password' => 'secret',
    'amount' => 1200,
    'status' => 'paid',
    'nested' => [
        'token' => 'abc123',
        'notes' => 'dato personal libre',
        'active' => true,
    ],
];

$result = auditSanitize($input);

auditAssert($result['name'] === '[MINIMIZED]', 'name must be minimized');
auditAssert($result['email'] === '[MINIMIZED]', 'email must be minimized');
auditAssert($result['phone'] === '[MINIMIZED]', 'phone must be minimized');
auditAssert($result['password'] === '[REDACTED]', 'password must be redacted');
auditAssert($result['amount'] === 1200, 'non-PII financial amount must remain auditable');
auditAssert($result['status'] === 'paid', 'status must remain auditable');
auditAssert($result['nested']['token'] === '[REDACTED]', 'nested token must be redacted');
auditAssert($result['nested']['notes'] === '[MINIMIZED]', 'free-form notes must be minimized');
auditAssert($result['nested']['active'] === true, 'non-sensitive nested value must remain');

auditAssert(auditMinimizeIp('192.168.10.42') === '192.168.10.0', 'IPv4 must be reduced to /24');
$ipv6 = auditMinimizeIp('2001:db8:abcd:1234:5678:90ab:cdef:1234');
auditAssert($ipv6 !== null && !str_contains($ipv6, '5678'), 'IPv6 host portion must be removed');
auditAssert(auditMinimizeIp('not-an-ip') === null, 'invalid IP must not be stored');

$oldRetention = getenv('AUDIT_RETENTION_DAYS');
putenv('AUDIT_RETENTION_DAYS=5');
auditAssert(auditRetentionDays() === 30, 'retention must not go below 30 days');
putenv('AUDIT_RETENTION_DAYS=180');
auditAssert(auditRetentionDays() === 180, 'configured retention must be honored');
if ($oldRetention === false) {
    putenv('AUDIT_RETENTION_DAYS');
} else {
    putenv('AUDIT_RETENTION_DAYS=' . $oldRetention);
}

$failurePdo = new class extends PDO {
    public function __construct() {}
    public function exec(string $statement): int|false
    {
        throw new RuntimeException('forced audit storage failure');
    }
};
$requiredFailed = false;
try {
    auditMutationRequired($failurePdo, ['id'=>1,'role'=>'admin'], 'payment.updated', 'payment', 1, null, ['status'=>'paid']);
} catch (RuntimeException) {
    $requiredFailed = true;
}
auditAssert($requiredFailed, 'required audit must fail closed when storage is unavailable');

echo "AUDIT_SANITIZER_OK\n";
