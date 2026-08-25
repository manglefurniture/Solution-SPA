<?php

declare(strict_types=1);

function phoneDefaultCountryCode(): string
{
    $configured = preg_replace('/\D+/', '', (string)(getenv('PHONE_DEFAULT_COUNTRY') ?: '52')) ?? '';
    return $configured !== '' ? $configured : '52';
}

/**
 * Normalize a phone number to E.164. Local numbers use the configured default
 * country code. Historical Mexican +521 mobile numbers are converted to +52.
 */
function normalizePhoneE164(string $raw, ?string $defaultCountryCode = null): string
{
    $raw = trim($raw);
    if ($raw === '') {
        throw new InvalidArgumentException('Phone number is required.');
    }

    $country = preg_replace('/\D+/', '', $defaultCountryCode ?? phoneDefaultCountryCode()) ?? '';
    if ($country === '') {
        throw new InvalidArgumentException('Default country code is required.');
    }

    $digits = preg_replace('/\D+/', '', $raw) ?? '';
    $explicitInternational = str_starts_with($raw, '+') || str_starts_with($raw, '00');

    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
        $explicitInternational = true;
    }

    // Historical Mexican WhatsApp/mobile form: +521 followed by 10 digits.
    if (str_starts_with($digits, '521') && strlen($digits) === 13) {
        $digits = '52' . substr($digits, 3);
    }

    if (!$explicitInternational && !str_starts_with($digits, $country)) {
        $digits = $country . $digits;
    }

    if (!preg_match('/^[1-9][0-9]{7,14}$/', $digits)) {
        throw new InvalidArgumentException('Phone number is not valid for E.164.');
    }

    return '+' . $digits;
}
