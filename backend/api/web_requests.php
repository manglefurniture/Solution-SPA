<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    requirePermission('web_requests.view');
    $stmt = $pdo->query("SELECT wr.id,wr.name,wr.phone,wr.interest,wr.status,wr.source,wr.client_id,wr.created_at,wr.updated_at,c.name AS client_name FROM web_requests wr LEFT JOIN clients c ON c.id=wr.client_id ORDER BY FIELD(wr.status,'new','contacted','converted','dismissed'),wr.created_at DESC LIMIT 100");
    respond(['data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $user = requirePermission('web_requests.update');
    $data = jsonInput();
    $action = (string)($data['action'] ?? '');
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) respond(['error' => 'Solicitud inválida'], 422);
    if ($action !== 'convert') respond(['error' => 'Acción inválida'], 422);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id,name,phone,interest,status,client_id FROM web_requests WHERE id=:id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            $pdo->rollBack();
            respond(['error' => 'La solicitud no existe'], 404);
        }
        if (!empty($row['client_id'])) {
            $clientId = (int)$row['client_id'];
            $pdo->commit();
            respond(['ok' => true, 'client_id' => $clientId, 'already_converted' => true]);
        }

        try {
            $phone = normalizePhoneE164((string)$row['phone']);
        } catch (InvalidArgumentException) {
            $pdo->rollBack();
            respond(['error' => 'La solicitud tiene un teléfono inválido; corrígelo antes de convertirla'], 422);
        }

        $stmt = $pdo->prepare('INSERT INTO clients (name,phone,notes,active) VALUES (:name,:phone,:notes,1)');
        $stmt->execute([
            'name' => trim((string)$row['name']),
            'phone' => $phone,
            'notes' => 'Solicitud web: ' . trim((string)$row['interest']),
        ]);
        $clientId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("UPDATE web_requests SET status='converted',client_id=:client_id,phone=:phone WHERE id=:id");
        $stmt->execute(['client_id' => $clientId, 'phone' => $phone, 'id' => $id]);

        auditMutation(
            $pdo,
            $user,
            'web_request.converted',
            'web_request',
            $id,
            $row,
            ['status' => 'converted', 'client_id' => $clientId, 'phone' => $phone],
            ['created_client_id' => $clientId]
        );
        auditMutation(
            $pdo,
            $user,
            'client.created_from_web_request',
            'client',
            $clientId,
            null,
            ['name' => trim((string)$row['name']), 'phone' => $phone],
            ['web_request_id' => $id]
        );

        $pdo->commit();
        respond(['ok' => true, 'client_id' => $clientId], 201);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['error' => 'No se pudo convertir la solicitud en cliente'], 500);
    }
}

if ($method === 'PATCH') {
    $user = requirePermission('web_requests.update');
    $data = jsonInput();
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $status = (string)($data['status'] ?? '');
    if ($id === false) respond(['error' => 'Solicitud inválida'], 422);
    if (!in_array($status, ['new', 'contacted', 'dismissed'], true)) respond(['error' => 'Estado inválido'], 422);

    $currentStmt = $pdo->prepare('SELECT id,name,phone,interest,status,source,client_id FROM web_requests WHERE id=:id LIMIT 1');
    $currentStmt->execute(['id' => $id]);
    $current = $currentStmt->fetch();
    if (!$current) respond(['error' => 'La solicitud no existe'], 404);
    if (!empty($current['client_id'])) respond(['error' => 'Una solicitud convertida ya no puede cambiar de estado'], 409);

    $stmt = $pdo->prepare('UPDATE web_requests SET status=:status WHERE id=:id AND client_id IS NULL');
    $stmt->execute(['status' => $status, 'id' => $id]);
    auditMutation($pdo, $user, 'web_request.status_changed', 'web_request', $id, $current, ['status' => $status]);
    respond(['ok' => true]);
}

respond(['error' => 'Method not allowed'], 405);
