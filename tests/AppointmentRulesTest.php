<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/appointments_domain.php';

function appointmentAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

appointmentAssert(strictDate('2026-02-28') instanceof DateTimeImmutable, 'valid date must parse');
appointmentAssert(strictDate('2026-02-30') === null, 'invalid calendar date must be rejected');
appointmentAssert(strictDate('26-02-28') === null, 'non-canonical date must be rejected');

$valid = strictDateTime('2026-08-28 09:30:00');
appointmentAssert($valid instanceof DateTimeImmutable, 'valid datetime must parse');
appointmentAssert(strictDateTime('2026-08-28 25:00:00') === null, 'invalid hour must be rejected');
appointmentAssert(strictDateTime('2026-08-28T09:30:00') === null, 'non-canonical datetime must be rejected');

$tz = new DateTimeZone('America/Cancun');
appointmentAssert(appointmentWithinBusinessHours(new DateTimeImmutable('2026-08-28 08:00:00', $tz), 60), 'opening time must be allowed');
appointmentAssert(appointmentWithinBusinessHours(new DateTimeImmutable('2026-08-28 15:00:00', $tz), 60), 'appointment ending at close must be allowed');
appointmentAssert(!appointmentWithinBusinessHours(new DateTimeImmutable('2026-08-28 07:59:59', $tz), 60), 'before opening must be rejected');
appointmentAssert(!appointmentWithinBusinessHours(new DateTimeImmutable('2026-08-28 15:30:00', $tz), 60), 'appointment extending past close must be rejected');
appointmentAssert(!appointmentWithinBusinessHours(new DateTimeImmutable('2026-08-28 10:00:00', $tz), 0), 'zero duration must be rejected');

appointmentAssert(entityId('1') === 1, 'positive integer id must parse');
appointmentAssert(entityId('0') === false, 'zero id must be rejected');
appointmentAssert(entityId('-3') === false, 'negative id must be rejected');
appointmentAssert(entityId('abc') === false, 'non-numeric id must be rejected');

echo "APPOINTMENT_RULES_OK\n";
