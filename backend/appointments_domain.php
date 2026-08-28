<?php

declare(strict_types=1);

const CLIENT_OPEN_TIME = '08:00:00';
const CLIENT_CLOSE_TIME = '16:00:00';

function strictDate(string $value): ?DateTimeImmutable
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    if (!checkdate($month, $day, $year)) return null;
    return new DateTimeImmutable($value . ' 00:00:00', new DateTimeZone('America/Cancun'));
}

function strictDateTime(string $value): ?DateTimeImmutable
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('America/Cancun'));
    return $date && $date->format('Y-m-d H:i:s') === $value ? $date : null;
}

function entityId(mixed $value): int|false
{
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

function appointmentWithinBusinessHours(DateTimeImmutable $start, int $duration): bool
{
    if ($duration < 1) return false;
    $timezone = new DateTimeZone('America/Cancun');
    $open = new DateTimeImmutable($start->format('Y-m-d') . ' ' . CLIENT_OPEN_TIME, $timezone);
    $close = new DateTimeImmutable($start->format('Y-m-d') . ' ' . CLIENT_CLOSE_TIME, $timezone);
    return $start >= $open && $start->modify('+' . $duration . ' minutes') <= $close;
}

function ensureBusinessHours(DateTimeImmutable $start, int $duration): void
{
    if (!appointmentWithinBusinessHours($start, $duration)) {
        respond(['error'=>'Las reservas en línea están disponibles de 8:00 a. m. a 4:00 p. m. y el tratamiento debe terminar antes del cierre.'], 422);
    }
}

function ensureClient(PDO $pdo, int $id, bool $allowArchived = false): void
{
    $stmt = $pdo->prepare('SELECT active FROM clients WHERE id=:id');
    $stmt->execute(['id'=>$id]);
    $active = $stmt->fetchColumn();
    if ($active === false) respond(['error'=>'El cliente no existe'], 422);
    if (!$allowArchived && (int)$active !== 1) respond(['error'=>'El cliente está archivado'], 422);
}

function serviceDuration(PDO $pdo, int $id, bool $allowInactive = false): int
{
    $stmt = $pdo->prepare('SELECT active,duration_minutes FROM services WHERE id=:id');
    $stmt->execute(['id'=>$id]);
    $row = $stmt->fetch();
    if (!$row) respond(['error'=>'El servicio no existe'], 422);
    if (!$allowInactive && (int)$row['active'] !== 1) respond(['error'=>'El servicio está inactivo'], 422);
    return max(5, (int)$row['duration_minutes']);
}

function ensureManager(PDO $pdo, ?int $id): void
{
    if ($id === null) return;
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id=:id AND role='operator' AND active=1");
    $stmt->execute(['id'=>$id]);
    if (!$stmt->fetchColumn()) respond(['error'=>'Gestor inválido o inactivo'], 422);
}

function hasConflict(PDO $pdo, DateTimeImmutable $start, int $duration, ?int $managerId, ?int $excludeId = null): bool
{
    $end = $start->modify('+' . $duration . ' minutes');
    $sql = "SELECT a.id FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.status IN ('pending','confirmed') AND a.starts_at<:end AND DATE_ADD(a.starts_at,INTERVAL s.duration_minutes MINUTE)>:start";
    $params = ['end'=>$end->format('Y-m-d H:i:s'),'start'=>$start->format('Y-m-d H:i:s')];
    if ($managerId !== null) {
        $sql .= ' AND (a.manager_user_id=:manager OR a.manager_user_id IS NULL)';
        $params['manager'] = $managerId;
    } else {
        $sql .= ' AND a.manager_user_id IS NULL';
    }
    if ($excludeId !== null) {
        $sql .= ' AND a.id<>:exclude';
        $params['exclude'] = $excludeId;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function chooseManager(PDO $pdo, DateTimeImmutable $start, int $duration, ?int $excludeId = null): ?int
{
    $rows = $pdo->query("SELECT id FROM users WHERE role='operator' AND active=1 ORDER BY name")->fetchAll();
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        if (!hasConflict($pdo, $start, $duration, $id, $excludeId)) return $id;
    }
    if (!$rows && !hasConflict($pdo, $start, $duration, null, $excludeId)) return null;
    return -1;
}
