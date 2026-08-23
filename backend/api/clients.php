<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
requireAuth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

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

    $name = trim((string)$data['name']);
    $phone = trim((string)$data['phone']);
    $email = isset($data['email']) ? trim((string)$data['email']) : '';
    $birthDate = isset($data['birth_date']) ? trim((string)$data['birth_date']) : '';
    $notes = isset($data['notes']) ? trim((string)$data['notes']) : '';

    if (strlen($name) > 120) respond(['error' => 'El nombre es demasiado largo'], 422);
    if (strlen($phone) > 30) respond(['error' => 'El teléfono es demasiado largo'], 422);
    if ($email !== '' && (strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL))) respond(['error' => 'Correo inválido'], 422);
    if ($birthDate !== '') {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $birthDate, $parts) || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) respond(['error' => 'Fecha de nacimiento inválida'], 422);
    }

    $stmt = $pdo->prepare('INSERT INTO clients (name, phone, email, birth_date, notes) VALUES (:name, :phone, :email, :birth_date, :notes)');
    $stmt->execute([
        'name' => $name,
        'phone' => $phone,
        'email' => $email !== '' ? $email : null,
        'birth_date' => $birthDate !== '' ? $birthDate : null,
        'notes' => $notes !== '' ? $notes : null,
    ]);
    respond(['id' => (int)$pdo->lastInsertId()], 201);
}

respond(['error' => 'Method not allowed'], 405);
