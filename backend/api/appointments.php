<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
requireAuth();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function strictDate(string $value): ?DateTimeImmutable
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    if (!checkdate($month, $day, $year)) return null;
    return new DateTimeImmutable($value . ' 00:00:00');
}

function strictDateTime(string $value): ?DateTimeImmutable
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) return null;
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
    if (!$date || $date->format('Y-m-d H:i:s') !== $value) return null;
    return $date;
}

$select = "SELECT a.id, a.starts_at, a.status, a.notes,
                  c.id AS client_id, c.name AS client_name, c.phone,
                  s.id AS service_id, s.name AS service_name, s.duration_minutes
           FROM appointments a
           JOIN clients c ON c.id = a.client_id
           JOIN services s ON s.id = a.service_id";

if ($method === 'GET') {
    $date = trim((string)($_GET['date'] ?? ''));
    $month = trim((string)($_GET['month'] ?? ''));
    $nextFrom = trim((string)($_GET['next_from'] ?? ''));
    $sql = $select;
    $params = [];
    $limit = 500;

    if ($nextFrom !== '') {
        $from = strictDateTime($nextFrom);
        if (!$from) respond(['error' => 'Invalid next_from'], 422);
        $sql .= " WHERE a.starts_at >= :from AND a.status IN ('pending','confirmed')";
        $params['from'] = $from->format('Y-m-d H:i:s');
        $limit = 1;
    } elseif ($date !== '') {
        $start = strictDate($date);
        if (!$start) respond(['error' => 'Invalid date'], 422);
        $end = $start->modify('+1 day');
        $sql .= ' WHERE a.starts_at >= :start AND a.starts_at < :end';
        $params['start'] = $start->format('Y-m-d H:i:s');
        $params['end'] = $end->format('Y-m-d H:i:s');
    } elseif ($month !== '') {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $month, $parts)) respond(['error' => 'Invalid month'], 422);
        $year = (int)$parts[1];
        $monthNumber = (int)$parts[2];
        if ($monthNumber < 1 || $monthNumber > 12) respond(['error' => 'Invalid month'], 422);
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $monthNumber));
        $end = $start->modify('first day of next month');
        $sql .= ' WHERE a.starts_at >= :start AND a.starts_at < :end';
        $params['start'] = $start->format('Y-m-d H:i:s');
        $params['end'] = $end->format('Y-m-d H:i:s');
    }

    $sql .= ' ORDER BY a.starts_at ASC LIMIT ' . $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    respond(['data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = jsonInput();
    requireFields($data, ['client_id', 'service_id', 'starts_at']);
    $clientId = filter_var($data['client_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $serviceId = filter_var($data['service_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($clientId === false || $serviceId === false) respond(['error' => 'Cliente o servicio inválido'], 422);
    $startsAt = strictDateTime(trim((string)$data['starts_at']));
    if (!$startsAt) respond(['error' => 'Fecha u hora inválida'], 422);
    $status = (string)($data['status'] ?? 'pending');
    $allowed = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($status, $allowed, true)) respond(['error' => 'Estado inválido'], 422);

    $clientCheck = $pdo->prepare('SELECT 1 FROM clients WHERE id = :id LIMIT 1');
    $clientCheck->execute(['id' => $clientId]);
    if (!$clientCheck->fetchColumn()) respond(['error' => 'El cliente no existe'], 422);

    $serviceCheck = $pdo->prepare('SELECT active FROM services WHERE id = :id LIMIT 1');
    $serviceCheck->execute(['id' => $serviceId]);
    $serviceActive = $serviceCheck->fetchColumn();
    if ($serviceActive === false) respond(['error' => 'El servicio no existe'], 422);
    if ((int)$serviceActive !== 1) respond(['error' => 'El servicio está inactivo'], 422);

    $notes = isset($data['notes']) ? trim((string)$data['notes']) : null;
    $stmt = $pdo->prepare('INSERT INTO appointments (client_id, service_id, starts_at, status, notes) VALUES (:client_id, :service_id, :starts_at, :status, :notes)');
    $stmt->execute([
        'client_id' => $clientId,
        'service_id' => $serviceId,
        'starts_at' => $startsAt->format('Y-m-d H:i:s'),
        'status' => $status,
        'notes' => $notes !== '' ? $notes : null,
    ]);
    respond(['id' => (int)$pdo->lastInsertId()], 201);
}

respond(['error' => 'Method not allowed'], 405);
