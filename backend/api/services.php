<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $stmt = $pdo->query('SELECT id, name, description, duration_minutes, price, active FROM services ORDER BY active DESC, name');
    respond(['data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = jsonInput();
    requireFields($data, ['name']);
    $duration = max(5, (int)($data['duration_minutes'] ?? 60));
    $price = isset($data['price']) && $data['price'] !== '' ? (float)$data['price'] : null;
    $stmt = $pdo->prepare('INSERT INTO services (name, description, duration_minutes, price, active) VALUES (:name, :description, :duration, :price, :active)');
    $stmt->execute([
        'name' => trim((string)$data['name']),
        'description' => $data['description'] ?? null,
        'duration' => $duration,
        'price' => $price,
        'active' => !isset($data['active']) || (bool)$data['active'] ? 1 : 0,
    ]);
    respond(['id' => (int)$pdo->lastInsertId()], 201);
}

respond(['error' => 'Method not allowed'], 405);
