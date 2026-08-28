<?php

declare(strict_types=1);

final class RateLimitStorageException extends RuntimeException
{
}

function canonicalRole(string $role): string
{
    return $role === 'staff' ? 'operator' : $role;
}

function roleLabel(string $role): string
{
    return match (canonicalRole($role)) {
        'admin' => 'Administrador',
        'operator' => 'Gestor',
        'client' => 'Cliente',
        default => 'Usuario',
    };
}

function rolePermissions(string $role): array
{
    $role = canonicalRole($role);
    $map = [
        'admin' => ['*'],
        'operator' => [
            'clients.view','clients.create','clients.update',
            'appointments.view','appointments.create','appointments.update',
            'services.view','web_requests.view','web_requests.update',
            'payments.view','payments.update',
        ],
        'client' => [
            'profile.view','appointments.own.view','appointments.own.create',
            'appointments.own.update','services.view','payments.own.view',
        ],
    ];
    return $map[$role] ?? [];
}

function userCan(array $user, string $permission): bool
{
    $permissions = rolePermissions((string)($user['role'] ?? ''));
    return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
}

/**
 * Atomically consumes one request from a file-backed rate-limit bucket.
 * Storage failures throw instead of silently disabling protection.
 *
 * @return array{allowed:bool,retry_after:int}
 */
function rateLimitConsume(
    string $directory,
    string $key,
    int $limit,
    int $windowSeconds,
    ?int $now = null
): array {
    if ($limit < 1 || $windowSeconds < 1) {
        throw new InvalidArgumentException('Rate-limit configuration must be positive.');
    }

    $now ??= time();
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RateLimitStorageException('Cannot create rate-limit directory.');
    }

    $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    $fp = fopen($path, 'c+');
    if ($fp === false) {
        throw new RateLimitStorageException('Cannot open rate-limit state.');
    }

    $locked = false;
    try {
        if (!flock($fp, LOCK_EX)) {
            throw new RateLimitStorageException('Cannot lock rate-limit state.');
        }
        $locked = true;

        rewind($fp);
        $raw = stream_get_contents($fp);
        if ($raw === false) {
            throw new RateLimitStorageException('Cannot read rate-limit state.');
        }

        $state = json_decode($raw !== '' ? $raw : '{}', true);
        if (!is_array($state)) {
            $state = [];
        }

        $start = (int)($state['start'] ?? $now);
        $count = (int)($state['count'] ?? 0);
        if ($start > $now || $now - $start >= $windowSeconds) {
            $start = $now;
            $count = 0;
        }

        if ($count >= $limit) {
            return [
                'allowed' => false,
                'retry_after' => max(1, $windowSeconds - ($now - $start)),
            ];
        }

        $count++;
        $payload = json_encode(['start' => $start, 'count' => $count], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RateLimitStorageException('Cannot encode rate-limit state.');
        }

        rewind($fp);
        if (!ftruncate($fp, 0)) {
            throw new RateLimitStorageException('Cannot truncate rate-limit state.');
        }
        $written = fwrite($fp, $payload);
        if ($written === false || $written !== strlen($payload) || !fflush($fp)) {
            throw new RateLimitStorageException('Cannot persist rate-limit state.');
        }

        return ['allowed' => true, 'retry_after' => 0];
    } finally {
        if ($locked) {
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}
