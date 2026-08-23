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

    $name = trim((string)$data['name']);
    $description = isset($data['description']) ? trim((string)$data['description']) : '';
    $duration = filter_var($data['duration_minutes'] ?? 60, FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 1440]]);
    $priceRaw = $data['price'] ?? null;

    if (strlen($name) > 280) {
        respond(['error' => 'El nombre del servicio es demasiado largo'], 422);
    }
    if ($duration === false) {
        respond(['error' => 'Duración inválida'], 422);
    }

    $price = null;
    if ($priceRaw !== null && $priceRaw !== '') {
        if (!is_numeric($priceRaw)) {
            respond(['error' => 'Precio inválido'], 422);
        }
        $price = (float)$priceRaw;
        if ($price < 0 || $price > 99999999.99) {
            respond(['error' => 'Precio fuera de rango'], 422);
        }
    }

    $stmt = $pdo->prepare('INSERT INTO services (name, description, duration_minutes, price, active) VALUES (:name, :description, :duration, :price, :active)');
    $stmt->execute([
        'name' => $name,
        'description' => $description !== '' ? $description : null,
        'duration' => $duration,
        'price' => $price,
        'active' => !isset($data['active']) || (bool)$data['active'] ? 1 : 0,
    ]);
    respond(['id' => (int)$pdo->lastInsertId()], 201);
}

respond(['error' => 'Method not allowed'], 405);
