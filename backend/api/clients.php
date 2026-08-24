<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user = requireAuth();

function clientId(mixed $value): int|false
{
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

function normalizeClient(array $data, array $fallback = []): array
{
    $name = trim((string)($data['name'] ?? $fallback['name'] ?? ''));
    $phone = trim((string)($data['phone'] ?? $fallback['phone'] ?? ''));
    $email = trim((string)($data['email'] ?? $fallback['email'] ?? ''));
    $birthDate = trim((string)($data['birth_date'] ?? $fallback['birth_date'] ?? ''));
    $notes = trim((string)($data['notes'] ?? $fallback['notes'] ?? ''));
    $active = array_key_exists('active', $data) ? ((bool)$data['active'] ? 1 : 0) : (int)($fallback['active'] ?? 1);

    if ($name === '' || $phone === '') respond(['error' => 'Nombre y teléfono son obligatorios'], 422);
    if (strlen($name) > 120) respond(['error' => 'El nombre es demasiado largo'], 422);
    if (strlen($phone) > 30) respond(['error' => 'El teléfono es demasiado largo'], 422);
    if ($email !== '' && (strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL))) respond(['error' => 'Correo inválido'], 422);
    if ($birthDate !== '') {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $birthDate, $parts) || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) respond(['error' => 'Fecha de nacimiento inválida'], 422);
    }

    return [
        'name' => $name,
        'phone' => $phone,
        'email' => $email !== '' ? $email : null,
        'birth_date' => $birthDate !== '' ? $birthDate : null,
        'notes' => $notes !== '' ? $notes : null,
        'active' => $active,
    ];
}

if ($method === 'GET') {
    if ($user['role'] === 'client') {
        $ownId = (int)($user['client_id'] ?? 0);
        if ($ownId < 1) respond(['error' => 'Tu cuenta todavía no está vinculada a una ficha de cliente'], 403);
        $stmt = $pdo->prepare('SELECT id, name, phone, email, birth_date, active, created_at FROM clients WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $ownId]);
        $client = $stmt->fetch();
        if (!$client) respond(['error' => 'La ficha de cliente no existe'], 404);
        respond(['data' => [$client], 'meta' => ['total' => 1, 'page' => 1, 'per_page' => 1, 'pages' => 1]]);
    }

    requirePermission('clients.view');
    $q = trim((string)($_GET['q'] ?? ''));
    $includeArchived = isset($_GET['include_archived']) && $_GET['include_archived'] === '1';
    $countOnly = isset($_GET['count_only']) && $_GET['count_only'] === '1';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = max(1, min(200, (int)($_GET['per_page'] ?? 100)));
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];
    if (!$includeArchived) $where[] = 'active = 1';
    if ($q !== '') {
        $where[] = '(name LIKE :q OR phone LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM clients' . $whereSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    if ($countOnly) respond(['total' => $total]);

    $sql = 'SELECT id, name, phone, email, birth_date, notes, active, created_at FROM clients' . $whereSql . ' ORDER BY active DESC, name LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    respond(['data' => $stmt->fetchAll(), 'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'pages' => max(1, (int)ceil($total / $perPage))]]);
}

if ($method === 'POST') {
    requirePermission('clients.create');
    $data = jsonInput();
    requireFields($data, ['name', 'phone']);
    $client = normalizeClient($data);
    $stmt = $pdo->prepare('INSERT INTO clients (name, phone, email, birth_date, notes, active) VALUES (:name, :phone, :email, :birth_date, :notes, :active)');
    $stmt->execute($client);
    respond(['id' => (int)$pdo->lastInsertId()], 201);
}

if ($method === 'PATCH') {
    requirePermission('clients.update');
    $data = jsonInput();
    $id = clientId($data['id'] ?? null);
    if ($id === false) respond(['error' => 'Cliente inválido'], 422);

    $stmt = $pdo->prepare('SELECT name, phone, email, birth_date, notes, active FROM clients WHERE id=:id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $current = $stmt->fetch();
    if (!$current) respond(['error' => 'El cliente no existe'], 404);

    if ($user['role'] === 'operator' && array_key_exists('active', $data) && (int)(bool)$data['active'] !== (int)$current['active']) {
        respond(['error' => 'Los operarios no pueden archivar ni reactivar clientes'], 403);
    }

    $client = normalizeClient($data, $current);
    $client['id'] = $id;
    $stmt = $pdo->prepare('UPDATE clients SET name=:name, phone=:phone, email=:email, birth_date=:birth_date, notes=:notes, active=:active WHERE id=:id');
    $stmt->execute($client);
    respond(['ok' => true]);
}

if ($method === 'DELETE') {
    requirePermission('clients.delete');
    $data = jsonInput();
    $id = clientId($data['id'] ?? null);
    if ($id === false) respond(['error' => 'Cliente inválido'], 422);

    $exists = $pdo->prepare('SELECT 1 FROM clients WHERE id=:id LIMIT 1');
    $exists->execute(['id' => $id]);
    if (!$exists->fetchColumn()) respond(['error' => 'El cliente no existe'], 404);

    $nowCancun = (new DateTimeImmutable('now', new DateTimeZone('America/Cancun')))->format('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE clients SET active=0 WHERE id=:id');
        $stmt->execute(['id' => $id]);
        $stmt = $pdo->prepare("UPDATE appointments SET status='cancelled' WHERE client_id=:id AND starts_at >= :now AND status IN ('pending','confirmed')");
        $stmt->execute(['id' => $id, 'now' => $nowCancun]);
        $cancelledFuture = $stmt->rowCount();
        $pdo->commit();
        respond(['ok' => true, 'archived' => true, 'cancelled_future_appointments' => $cancelledFuture]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['error' => 'No se pudo archivar el cliente'], 500);
    }
}

respond(['error' => 'Method not allowed'], 405);
