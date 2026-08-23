<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
requireAuth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

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
    ];
}

if ($method === 'GET') {
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q === '') {
        $stmt = $pdo->query('SELECT id, name, phone, email, birth_date, notes, created_at FROM clients ORDER BY name LIMIT 200');
    } else {
        $stmt = $pdo->prepare('SELECT id, name, phone, email, birth_date, notes, created_at FROM clients WHERE name LIKE :q OR phone LIKE :q ORDER BY name LIMIT 100');
        $stmt->execute(['q' => '%' . $q . '%']);
    }
    respond(['data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = jsonInput();
    requireFields($data, ['name', 'phone']);
    $client = normalizeClient($data);
    $stmt = $pdo->prepare('INSERT INTO clients (name, phone, email, birth_date, notes) VALUES (:name, :phone, :email, :birth_date, :notes)');
    $stmt->execute($client);
    respond(['id' => (int)$pdo->lastInsertId()], 201);
}

if ($method === 'PATCH') {
    $data = jsonInput();
    $id = clientId($data['id'] ?? null);
    if ($id === false) respond(['error' => 'Cliente inválido'], 422);

    $stmt = $pdo->prepare('SELECT name, phone, email, birth_date, notes FROM clients WHERE id=:id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $current = $stmt->fetch();
    if (!$current) respond(['error' => 'El cliente no existe'], 404);

    $client = normalizeClient($data, $current);
    $client['id'] = $id;
    $stmt = $pdo->prepare('UPDATE clients SET name=:name, phone=:phone, email=:email, birth_date=:birth_date, notes=:notes WHERE id=:id');
    $stmt->execute($client);
    respond(['ok' => true]);
}

if ($method === 'DELETE') {
    $data = jsonInput();
    $id = clientId($data['id'] ?? null);
    if ($id === false) respond(['error' => 'Cliente inválido'], 422);

    $stmt = $pdo->prepare('SELECT name FROM clients WHERE id=:id LIMIT 1');
    $stmt->execute(['id' => $id]);
    if (!$stmt->fetchColumn()) respond(['error' => 'El cliente no existe'], 404);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('DELETE FROM treatments WHERE client_id=:id');
        $stmt->execute(['id' => $id]);
        $stmt = $pdo->prepare('DELETE FROM appointments WHERE client_id=:id');
        $stmt->execute(['id' => $id]);
        $stmt = $pdo->prepare('DELETE FROM clients WHERE id=:id');
        $stmt->execute(['id' => $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['error' => 'No se pudo eliminar el cliente'], 500);
    }
    respond(['ok' => true]);
}

respond(['error' => 'Method not allowed'], 405);
