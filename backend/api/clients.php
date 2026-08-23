<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

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
    $stmt = $pdo->prepare('INSERT INTO clients (name, phone, email, birth_date, notes) VALUES (:name, :phone, :email, :birth_date, :notes)');
    $stmt->execute([
        'name' => trim((string)$data['name']),
        'phone' => trim((string)$data['phone']),
        'email' => isset($data['email']) && trim((string)$data['email']) !== '' ? trim((string)$data['email']) : null,
        'birth_date' => $data['birth_date'] ?? null,
        'notes' => $data['notes'] ?? null,
    ]);
    respond(['id' => (int)$pdo->lastInsertId()], 201);
}

respond(['error' => 'Method not allowed'], 405);
