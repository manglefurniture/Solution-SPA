<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(['error' => 'Método no permitido'], 405);
}

enforceRateLimit('public-valuation', 8, 3600);
$data = jsonInput();
$name = trim((string)($data['name'] ?? ''));
$phone = trim((string)($data['phone'] ?? ''));
$interest = trim((string)($data['interest'] ?? ''));
$website = trim((string)($data['website'] ?? ''));

if ($website !== '') respond(['ok'=>true],201);
if ($name === '' || $phone === '' || $interest === '') respond(['error'=>'Completa nombre, WhatsApp e interés'],422);
if (mb_strlen($name)>120) respond(['error'=>'Nombre demasiado largo'],422);
if (mb_strlen($phone)>30) respond(['error'=>'WhatsApp inválido'],422);
if (mb_strlen($interest)>160) respond(['error'=>'Interés demasiado largo'],422);
if (!preg_match('/^[0-9+() .-]{7,30}$/',$phone)) respond(['error'=>'Revisa el número de WhatsApp'],422);

try {
    $pdo=db();$stmt=$pdo->prepare("INSERT INTO web_requests (name,phone,interest,status,source) VALUES (:name,:phone,:interest,'new','website')");
    $stmt->execute(['name'=>$name,'phone'=>$phone,'interest'=>$interest]);
    respond(['ok'=>true,'id'=>(int)$pdo->lastInsertId()],201);
} catch (Throwable $e) {
    respond(['error'=>'No pudimos registrar tu solicitud ahora mismo'],500);
}
