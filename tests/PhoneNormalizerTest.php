<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/phone.php';

function expectPhone(string $input, string $expected, string $country = '52'): void
{
    $actual = normalizePhoneE164($input, $country);
    if ($actual !== $expected) {
        fwrite(STDERR, "Expected {$expected}, got {$actual} for {$input}\n");
        exit(1);
    }
}

expectPhone('998 123 4567', '+529981234567');
expectPhone('+52 998 123 4567', '+529981234567');
expectPhone('+5219981234567', '+529981234567');
expectPhone('0052 998 123 4567', '+529981234567');
expectPhone('+1 305 555 0123', '+13055550123');
expectPhone('3055550123', '+13055550123', '1');

$failed = false;
try {
    normalizePhoneE164('123', '52');
} catch (InvalidArgumentException) {
    $failed = true;
}
if (!$failed) {
    fwrite(STDERR, "Invalid phone was accepted\n");
    exit(1);
}

echo "PhoneNormalizerTest OK\n";
