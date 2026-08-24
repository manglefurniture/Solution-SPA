<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function reply(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    reply(['error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) reply(['error' => 'JSON inválido'], 400);

$name = trim((string)($data['name'] ?? ''));
$phone = trim((string)($data['phone'] ?? ''));
$interest = trim((string)($data['interest'] ?? ''));
$website = trim((string)($data['website'] ?? '')); // honeypot

if ($website !== '') reply(['ok' => true], 201);
if ($name === '' || $phone === '' || $interest === '') reply(['error' => 'Completa nombre, WhatsApp e interés'], 422);
if (mb_strlen($name) > 120) reply(['error' => 'Nombre demasiado largo'], 422);
if (mb_strlen($phone) > 30) reply(['error' => 'WhatsApp inválido'], 422);
if (mb_strlen($interest) > 160) reply(['error' => 'Interés demasiado largo'], 422);
if (!preg_match('/^[0-9+() .-]{7,30}$/', $phone)) reply(['error' => 'Revisa el número de WhatsApp'], 422);

try {
    $pdo = db();
    $stmt = $pdo->prepare('INSERT INTO web_requests (name, phone, interest, status, source) VALUES (:name, :phone, :interest, \'new\', \'website\')');
    $stmt->execute(['name' => $name, 'phone' => $phone, 'interest' => $interest]);
    reply(['ok' => true, 'id' => (int)$pdo->lastInsertId()], 201);
} catch (Throwable $e) {
    reply(['error' => 'No pudimos registrar tu solicitud ahora mismo'], 500);
}
