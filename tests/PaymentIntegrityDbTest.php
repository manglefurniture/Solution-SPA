<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/backend/db.php';
require_once dirname(__DIR__) . '/backend/audit.php';

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
    $paymentId = (int)$pdo->lastInsertId();

    $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
    auditMutationRequired(
        $pdo,
        ['id'=>999,'role'=>'admin'],
        'payment.created',
        'payment',
        $paymentId,
        null,
        [
            'client_id'=>$clientId,
            'appointment_id'=>$appointmentId,
            'amount'=>1000,
            'status'=>'paid',
            'reference'=>'TEST-REFERENCE',
            'notes'=>'PII should not remain here',
        ],
        ['financial'=>true]
    );

    $audit = $pdo->prepare("SELECT ip_address,after_data FROM audit_events WHERE entity_type='payment' AND entity_id=:id ORDER BY id DESC LIMIT 1");
    $audit->execute(['id'=>(string)$paymentId]);
    $event = $audit->fetch();
    if (!$event) {
        throw new RuntimeException('Required financial audit event was not persisted.');
    }
    $after = json_decode((string)$event['after_data'], true);
    if (!is_array($after)
        || (float)($after['amount'] ?? 0) !== 1000.0
        || ($after['status'] ?? null) !== 'paid'
        || ($after['reference'] ?? null) !== '[MINIMIZED]'
        || ($after['notes'] ?? null) !== '[MINIMIZED]'
        || ($event['ip_address'] ?? null) !== '203.0.113.0') {
        throw new RuntimeException('Financial audit minimization regression failed.');
    }

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

    echo "PAYMENT_INTEGRITY_AUDIT_OK\n";
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
