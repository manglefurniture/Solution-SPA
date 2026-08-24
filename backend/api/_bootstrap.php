<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function requestIsSecure(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('solution_spa_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => requestIsSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function jsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'JSON inválido']);
        exit;
    }
    return $data;
}

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function requireFields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            respond(['error' => "Falta el campo: {$field}"], 422);
        }
    }
}

function canonicalRole(string $role): string
{
    return $role === 'staff' ? 'operator' : $role;
}

function rolePermissions(string $role): array
{
    $role = canonicalRole($role);
    $map = [
        'admin' => ['*'],
        'operator' => [
            'clients.view', 'clients.create', 'clients.update',
            'appointments.view', 'appointments.create', 'appointments.update',
            'services.view',
            'web_requests.view', 'web_requests.update',
        ],
        'client' => [
            'profile.view', 'appointments.own.view', 'services.view',
        ],
    ];
    return $map[$role] ?? [];
}

function userCan(array $user, string $permission): bool
{
    $permissions = rolePermissions((string)($user['role'] ?? ''));
    return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
}

function requirePermission(string $permission): array
{
    $user = requireAuth();
    if (!userCan($user, $permission)) respond(['error' => 'No tienes permiso para realizar esta acción'], 403);
    return $user;
}

function setRememberCookie(string $value, int $expires): void
{
    setcookie('solution_spa_remember', $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => requestIsSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearRememberCookie(): void
{
    setRememberCookie('', time() - 3600);
}

function establishUserSession(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = (string)$user['name'];
    $_SESSION['user_email'] = (string)$user['email'];
    $_SESSION['user_role'] = canonicalRole((string)$user['role']);
    $_SESSION['client_id'] = isset($user['client_id']) && $user['client_id'] !== null ? (int)$user['client_id'] : null;
}

function restoreRememberedUser(): ?array
{
    $cookie = (string)($_COOKIE['solution_spa_remember'] ?? '');
    if ($cookie === '' || !str_contains($cookie, ':')) return null;
    [$selector, $validator] = explode(':', $cookie, 2);
    if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
        clearRememberCookie();
        return null;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT rt.id AS token_id, rt.validator_hash, rt.expires_at, u.id, u.name, u.email, u.role, u.active, u.client_id
                               FROM remember_tokens rt JOIN users u ON u.id = rt.user_id
                               WHERE rt.selector = :selector LIMIT 1");
        $stmt->execute(['selector' => $selector]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['active'] !== 1 || strtotime((string)$row['expires_at']) < time() || !hash_equals((string)$row['validator_hash'], hash('sha256', $validator))) {
            if ($row) $pdo->prepare('DELETE FROM remember_tokens WHERE id = :id')->execute(['id' => $row['token_id']]);
            clearRememberCookie();
            return null;
        }

        establishUserSession($row);
        $newValidator = bin2hex(random_bytes(32));
        $newHash = hash('sha256', $newValidator);
        $expires = time() + 60 * 60 * 24 * 30;
        $pdo->prepare('UPDATE remember_tokens SET validator_hash = :hash, expires_at = :expires WHERE id = :id')->execute([
            'hash' => $newHash,
            'expires' => date('Y-m-d H:i:s', $expires),
            'id' => $row['token_id'],
        ]);
        setRememberCookie($selector . ':' . $newValidator, $expires);
        return $row;
    } catch (Throwable $e) {
        return null;
    }
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) restoreRememberedUser();
    if (empty($_SESSION['user_id'])) return null;
    $role = canonicalRole((string)($_SESSION['user_role'] ?? 'operator'));
    return [
        'id' => (int)$_SESSION['user_id'],
        'name' => (string)($_SESSION['user_name'] ?? ''),
        'email' => (string)($_SESSION['user_email'] ?? ''),
        'role' => $role,
        'client_id' => isset($_SESSION['client_id']) && $_SESSION['client_id'] !== null ? (int)$_SESSION['client_id'] : null,
        'permissions' => rolePermissions($role),
    ];
}

function requireAuth(): array
{
    $user = currentUser();
    if (!$user) respond(['error' => 'Sesión requerida'], 401);
    return $user;
}
