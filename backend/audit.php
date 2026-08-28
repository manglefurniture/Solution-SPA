<?php

declare(strict_types=1);

/**
 * Generic mutation audit helpers adapted from Hache-Base.
 * Keep this module business-agnostic: endpoints decide what is worth auditing.
 */

function auditSanitize(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    $secrets = [
        'password', 'password_confirm', 'password_hash', 'token', 'access_token',
        'refresh_token', 'authorization', 'cookie', 'secret', 'api_key',
        'card_number', 'cvv',
    ];
    $personal = [
        'name', 'full_name', 'client_name', 'email', 'phone', 'phone_e164',
        'birth_date', 'address', 'notes', 'reference',
    ];

    $clean = [];
    foreach ($value as $key => $item) {
        $normalized = strtolower((string)$key);
        if (in_array($normalized, $secrets, true)) {
            $clean[$key] = '[REDACTED]';
            continue;
        }
        if (in_array($normalized, $personal, true)) {
            $clean[$key] = '[MINIMIZED]';
            continue;
        }
        $clean[$key] = is_array($item) ? auditSanitize($item) : $item;
    }

    return $clean;
}

function auditJson(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    $json = json_encode(auditSanitize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Audit payload could not be encoded.');
    }
    return $json;
}

function auditRequestId(): string
{
    static $requestId = null;
    if (is_string($requestId)) {
        return $requestId;
    }

    $provided = trim((string)($_SERVER['HTTP_X_REQUEST_ID'] ?? ''));
    if ($provided !== '' && strlen($provided) <= 128) {
        $requestId = $provided;
        return $requestId;
    }

    try {
        $requestId = bin2hex(random_bytes(16));
    } catch (Throwable) {
        $requestId = uniqid('req_', true);
    }

    return $requestId;
}

function auditMinimizeIp(string $ip): ?string
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $parts[3] = '0';
        return implode('.', $parts);
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $packed = inet_pton($ip);
        if ($packed !== false) {
            $masked = substr($packed, 0, 8) . str_repeat("\0", 8);
            $normalized = inet_ntop($masked);
            return $normalized === false ? null : $normalized;
        }
    }

    return null;
}

function auditRetentionDays(): int
{
    $raw = getenv('AUDIT_RETENTION_DAYS');
    $days = $raw === false || $raw === '' ? 180 : (int)$raw;
    return max(30, min(3650, $days));
}

function auditPrune(PDO $pdo, ?int $days = null): int
{
    $days = max(30, min(3650, $days ?? auditRetentionDays()));
    return (int)$pdo->exec("DELETE FROM audit_events WHERE created_at < (CURRENT_TIMESTAMP - INTERVAL {$days} DAY)");
}

function auditMutation(
    PDO $pdo,
    ?array $actor,
    string $action,
    string $entityType,
    int|string|null $entityId = null,
    ?array $before = null,
    ?array $after = null,
    array $metadata = [],
    string $source = 'api'
): bool {
    $action = trim($action);
    $entityType = trim($entityType);
    if ($action === '' || $entityType === '') {
        return false;
    }

    $actorType = $actor === null ? 'public' : 'user';
    $actorId = $actor['id'] ?? null;
    $actorRole = $actor['role'] ?? null;
    $ip = auditMinimizeIp((string)($_SERVER['REMOTE_ADDR'] ?? ''));

    try {
        // Enforce retention as part of normal write traffic. The indexed
        // created_at column keeps this bounded and avoids indefinite PII growth.
        auditPrune($pdo);

        $stmt = $pdo->prepare(
            'INSERT INTO audit_events '
            . '(actor_type,actor_id,actor_role,action,entity_type,entity_id,source,request_id,ip_address,before_data,after_data,metadata) '
            . 'VALUES (:actor_type,:actor_id,:actor_role,:action,:entity_type,:entity_id,:source,:request_id,:ip_address,:before_data,:after_data,:metadata)'
        );

        return $stmt->execute([
            'actor_type' => $actorType,
            'actor_id' => $actorId === null ? null : (string)$actorId,
            'actor_role' => $actorRole === null ? null : (string)$actorRole,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId === null ? null : (string)$entityId,
            'source' => substr($source, 0, 100),
            'request_id' => auditRequestId(),
            'ip_address' => $ip,
            'before_data' => auditJson($before),
            'after_data' => auditJson($after),
            'metadata' => auditJson($metadata),
        ]);
    } catch (Throwable $e) {
        error_log('Solution SPA audit failure: ' . $e->getMessage());
        return false;
    }
}

function auditMutationRequired(
    PDO $pdo,
    ?array $actor,
    string $action,
    string $entityType,
    int|string|null $entityId = null,
    ?array $before = null,
    ?array $after = null,
    array $metadata = [],
    string $source = 'api'
): void {
    if (!auditMutation($pdo, $actor, $action, $entityType, $entityId, $before, $after, $metadata, $source)) {
        throw new RuntimeException('Required audit event could not be persisted.');
    }
}
