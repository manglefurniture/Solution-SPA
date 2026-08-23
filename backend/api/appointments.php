<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $date = trim((string)($_GET['date'] ?? ''));
    $month = trim((string)($_GET['month'] ?? ''));

    $sql = "SELECT a.id, a.starts_at, a.status, a.notes,
                   c.id AS client_id, c.name AS client_name, c.phone,
                   s.id AS service_id, s.name AS service_name, s.duration_minutes
            FROM appointments a
            JOIN clients c ON c.id = a.client_id
            JOIN services s ON s.id = a.service_id";
    $params = [];

    if ($date !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            respond(['error' => 'Invalid date'], 422);
        }
        $start = new DateTimeImmutable($date . ' 00:00:00');
        $end = $start->modify('+1 day');
        $sql .= ' WHERE a.starts_at >= :start AND a.starts_at < :end';
        $params['start'] = $start->format('Y-m-d H:i:s');
        $params['end'] = $end->format('Y-m-d H:i:s');
    } elseif ($month !== '') {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            respond(['error' => 'Invalid month'], 422);
        }
        $start = new DateTimeImmutable($month . '-01 00:00:00');
        $end = $start->modify('first day of next month');
        $sql .= ' WHERE a.starts_at >= :start AND a.starts_at < :end';
        $params['start'] = $start->format('Y-m-d H:i:s');
        $params['end'] = $end->format('Y-m-d H:i:s');
    }

    $sql .= ' ORDER BY a.starts_at ASC LIMIT 500';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    respond(['data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = jsonInput();
    requireFields($data, ['client_id', 'service_id', 'starts_at']);
    $status = (string)($data['status'] ?? 'pending');
    $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($status, $allowed, true)) {
        respond(['error' => 'Invalid status'], 422);
    }
    $stmt = $pdo->prepare('INSERT INTO appointments (client_id, service_id, starts_at, status, notes) VALUES (:client_id, :service_id, :starts_at, :status, :notes)');
    $stmt->execute([
        'client_id' => (int)$data['client_id'],
        'service_id' => (int)$data['service_id'],
        'starts_at' => (string)$data['starts_at'],
        'status' => $status,
        'notes' => $data['notes'] ?? null,
    ]);
    respond(['id' => (int)$pdo->lastInsertId()], 201);
}

respond(['error' => 'Method not allowed'], 405);
