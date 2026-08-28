<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/db.php';

$pdo = db();
$pdo->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(4));

    $pdo->prepare('INSERT INTO clients(name,phone,email,active) VALUES(:name,:phone,:email,1)')->execute([
        'name' => 'CI Client ' . $suffix,
        'phone' => '+529981234567',
        'email' => 'ci-' . $suffix . '@example.test',
    ]);
    $clientId = (int)$pdo->lastInsertId();

    $pdo->prepare('INSERT INTO services(name,duration_minutes,price,active) VALUES(:name,60,1000,1)')->execute([
        'name' => 'CI Service ' . $suffix,
    ]);
    $serviceId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO appointments(client_id,service_id,starts_at,status) VALUES(:client,:service,'2026-08-27 10:00:00','completed')")->execute([
        'client' => $clientId,
        'service' => $serviceId,
    ]);
    $appointmentId = (int)$pdo->lastInsertId();

    $insert = $pdo->prepare("INSERT INTO payments(client_id,appointment_id,amount,method,status,paid_at) VALUES(:client,:appointment,1000,'cash',:status,NOW())");
    $insert->execute(['client' => $clientId, 'appointment' => $appointmentId, 'status' => 'paid']);

    $duplicateInsertRejected = false;
    try {
        $insert->execute(['client' => $clientId, 'appointment' => $appointmentId, 'status' => 'paid']);
    } catch (PDOException $e) {
        $duplicateInsertRejected = (string)$e->getCode() === '23000';
    }

    $insert->execute(['client' => $clientId, 'appointment' => $appointmentId, 'status' => 'pending']);
    $pendingId = (int)$pdo->lastInsertId();

    $duplicatePromotionRejected = false;
    try {
        $pdo->prepare("UPDATE payments SET status='paid', paid_at=NOW() WHERE id=:id")->execute(['id' => $pendingId]);
    } catch (PDOException $e) {
        $duplicatePromotionRejected = (string)$e->getCode() === '23000';
    }

    if (!$duplicateInsertRejected || !$duplicatePromotionRejected) {
        throw new RuntimeException('Paid appointment uniqueness constraint did not reject all duplicate paths.');
    }

    echo "PaymentIntegrityDbTest OK\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
