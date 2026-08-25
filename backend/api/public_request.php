<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(['error' => 'Método no permitido'], 405);
}

enforceRateLimit('public-valuation', 8, 3600);
$data = jsonInput();
$name = trim((string)($data['name'] ?? ''));
$phoneRaw = trim((string)($data['phone'] ?? ''));
$interest = trim((string)($data['interest'] ?? ''));
$website = trim((string)($data['website'] ?? ''));

if ($website !== '') respond(['ok' => true], 201);
if ($name === '' || $phoneRaw === '' || $interest === '') respond(['error' => 'Completa nombre, WhatsApp e interés'], 422);
if (mb_strlen($name) > 120) respond(['error' => 'Nombre demasiado largo'], 422);
if (mb_strlen($interest) > 160) respond(['error' => 'Interés demasiado largo'], 422);

try {
    $phone = normalizePhoneE164($phoneRaw);
} catch (InvalidArgumentException) {
    respond(['error' => 'Revisa el número de WhatsApp'], 422);
}

try {
    $pdo = db();
    $stmt = $pdo->prepare("INSERT INTO web_requests (name,phone,interest,status,source) VALUES (:name,:phone,:interest,'new','website')");
    $stmt->execute(['name' => $name, 'phone' => $phone, 'interest' => $interest]);
    $id = (int)$pdo->lastInsertId();
    auditMutation(
        $pdo,
        null,
        'web_request.created',
        'web_request',
        $id,
        null,
        ['name' => $name, 'phone' => $phone, 'interest' => $interest, 'status' => 'new'],
        [],
        'website'
    );
    respond(['ok' => true, 'id' => $id], 201);
} catch (Throwable $e) {
    respond(['error' => 'No pudimos registrar tu solicitud ahora mismo'], 500);
}
