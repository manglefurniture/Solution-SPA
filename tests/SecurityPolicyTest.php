<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/security.php';

function assertTrue(bool $value, string $message): void
{
    if (!$value) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

assertTrue(canonicalRole('staff') === 'operator', 'legacy staff role must canonicalize to operator');
assertTrue(userCan(['role' => 'admin'], 'users.manage'), 'admin wildcard must allow administrative permission');
assertTrue(userCan(['role' => 'operator'], 'payments.update'), 'operator must retain payment update permission');
assertTrue(!userCan(['role' => 'operator'], 'users.manage'), 'operator must not manage users');
assertTrue(userCan(['role' => 'client'], 'appointments.own.create'), 'client must be able to create own appointment');
assertTrue(!userCan(['role' => 'client'], 'payments.update'), 'client must not update payments');

$tmp = sys_get_temp_dir() . '/solution-spa-security-test-' . bin2hex(random_bytes(6));
try {
    $first = rateLimitConsume($tmp, 'login|127.0.0.1', 2, 60, 1000);
    $second = rateLimitConsume($tmp, 'login|127.0.0.1', 2, 60, 1001);
    $third = rateLimitConsume($tmp, 'login|127.0.0.1', 2, 60, 1002);
    $reset = rateLimitConsume($tmp, 'login|127.0.0.1', 2, 60, 1060);

    assertTrue($first['allowed'] && $second['allowed'], 'first two requests must be allowed');
    assertTrue(!$third['allowed'] && $third['retry_after'] === 58, 'third request must be limited with retry window');
    assertTrue($reset['allowed'], 'bucket must reset after the window');

    $blocker = $tmp . '-file';
    file_put_contents($blocker, 'not a directory');
    $failedClosed = false;
    try {
        rateLimitConsume($blocker . '/child', 'public|127.0.0.1', 1, 60, 1000);
    } catch (RateLimitStorageException) {
        $failedClosed = true;
    }
    assertTrue($failedClosed, 'storage failure must throw instead of disabling rate limiting');
    @unlink($blocker);
} finally {
    if (is_dir($tmp)) {
        foreach (glob($tmp . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($tmp);
    }
}

echo "SECURITY_POLICY_OK\n";
