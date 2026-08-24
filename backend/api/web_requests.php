<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    requirePermission('web_requests.view');
    $stmt = $pdo->query("SELECT id,name,phone,interest,status,source,created_at,updated_at FROM web_requests ORDER BY FIELD(status,'new','contacted','converted','dismissed'), created_at DESC LIMIT 100");
    respond(['data' => $stmt->fetchAll()]);
}

if ($method === 'PATCH') {
    requirePermission('web_requests.update');
    $data = jsonInput();
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $status = (string)($data['status'] ?? '');
    if ($id === false) respond(['error' => 'Solicitud inválida'], 422);
    if (!in_array($status, ['new','contacted','converted','dismissed'], true)) respond(['error' => 'Estado inválido'], 422);
    $stmt = $pdo->prepare('UPDATE web_requests SET status=:status WHERE id=:id');
    $stmt->execute(['status' => $status, 'id' => $id]);
    if ($stmt->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT 1 FROM web_requests WHERE id=:id LIMIT 1');
        $exists->execute(['id' => $id]);
        if (!$exists->fetchColumn()) respond(['error' => 'La solicitud no existe'], 404);
    }
    respond(['ok' => true]);
}

respond(['error' => 'Method not allowed'], 405);
