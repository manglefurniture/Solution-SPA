<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require_once dirname(__DIR__) . '/backend/db.php';
require_once dirname(__DIR__) . '/backend/audit.php';

try {
    $deleted = auditPrune(db());
    echo 'AUDIT_PRUNE_OK deleted=' . $deleted . ' retention_days=' . auditRetentionDays() . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'AUDIT_PRUNE_FAIL ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
