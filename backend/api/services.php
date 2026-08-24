<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function serviceId(mixed $value): int|false
{
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

function normalizeService(array $data, array $fallback = []): array
{
    $name = trim((string)($data['name'] ?? $fallback['name'] ?? ''));
    $description = trim((string)($data['description'] ?? $fallback['description'] ?? ''));
    $duration = filter_var($data['duration_minutes'] ?? $fallback['duration_minutes'] ?? 60, FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 1440]]);
    $priceRaw = $data['price'] ?? $fallback['price'] ?? null;
    $active = array_key_exists('active', $data) ? ((bool)$data['active'] ? 1 : 0) : (int)($fallback['active'] ?? 1);

    if ($name === '') respond(['error' => 'El nombre del servicio es obligatorio'], 422);
    if (strlen($name) > 140) respond(['error' => 'El nombre del servicio es demasiado largo'], 422);
    if ($duration === false) respond(['error' => 'Duración inválida'], 422);

    $price = null;
    if ($priceRaw !== null && $priceRaw !== '') {
        if (!is_numeric($priceRaw)) respond(['error' => 'Precio inválido'], 422);
        $price = (float)$priceRaw;
        if ($price < 0 || $price > 99999999.99) respond(['error' => 'Precio fuera de rango'], 422);
    }

    return ['name'=>$name,'description'=>$description !== '' ? $description : null,'duration'=>$duration,'price'=>$price,'active'=>$active];
}

if ($method === 'GET') {
    $user = requirePermission('services.view');
    $includeArchived = $user['role'] !== 'client' && (!isset($_GET['include_archived']) || $_GET['include_archived'] !== '0');
    $sql = 'SELECT id, name, description, duration_minutes, price, active FROM services';
    if (!$includeArchived) $sql .= ' WHERE active=1';
    $sql .= ' ORDER BY active DESC, name';
    $stmt = $pdo->query($sql);
    respond(['data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    requirePermission('services.create');
    $data = jsonInput();requireFields($data, ['name']);$service = normalizeService($data);
    $stmt = $pdo->prepare('INSERT INTO services (name, description, duration_minutes, price, active) VALUES (:name, :description, :duration, :price, :active)');$stmt->execute($service);
    respond(['id' => (int)$pdo->lastInsertId()], 201);
}

if ($method === 'PATCH') {
    requirePermission('services.update');
    $data = jsonInput();$id = serviceId($data['id'] ?? null);if ($id === false) respond(['error' => 'Servicio inválido'], 422);
    $stmt = $pdo->prepare('SELECT name, description, duration_minutes, price, active FROM services WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$id]);$current=$stmt->fetch();if(!$current)respond(['error'=>'El servicio no existe'],404);
    $service=normalizeService($data,$current);$service['id']=$id;
    $stmt=$pdo->prepare('UPDATE services SET name=:name, description=:description, duration_minutes=:duration, price=:price, active=:active WHERE id=:id');$stmt->execute($service);
    respond(['ok'=>true]);
}

if ($method === 'DELETE') {
    requirePermission('services.delete');
    $data=jsonInput();$id=serviceId($data['id']??null);if($id===false)respond(['error'=>'Servicio inválido'],422);
    $stmt=$pdo->prepare('UPDATE services SET active=0 WHERE id=:id');$stmt->execute(['id'=>$id]);
    if($stmt->rowCount()===0){$exists=$pdo->prepare('SELECT 1 FROM services WHERE id=:id LIMIT 1');$exists->execute(['id'=>$id]);if(!$exists->fetchColumn())respond(['error'=>'El servicio no existe'],404);}
    respond(['ok'=>true,'archived'=>true]);
}

respond(['error'=>'Method not allowed'],405);
