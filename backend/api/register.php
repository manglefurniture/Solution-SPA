<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(['error' => 'Método no permitido'], 405);
}

$data = jsonInput();
requireFields($data, ['name', 'phone', 'email', 'password', 'password_confirm']);

$name = trim((string)$data['name']);
$phone = trim((string)$data['phone']);
$email = strtolower(trim((string)$data['email']));
$password = (string)$data['password'];
$passwordConfirm = (string)$data['password_confirm'];
$website = trim((string)($data['website'] ?? ''));

// Honeypot básico contra bots. El campo no se muestra a personas reales.
if ($website !== '') {
    respond(['ok' => true], 201);
}

if ($name === '' || strlen($name) > 120) respond(['error' => 'Nombre inválido'], 422);
if ($phone === '' || strlen($phone) > 30) respond(['error' => 'Teléfono inválido'], 422);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) respond(['error' => 'Correo inválido'], 422);
if (strlen($password) < 8) respond(['error' => 'La contraseña debe tener al menos 8 caracteres'], 422);
if (!hash_equals($password, $passwordConfirm)) respond(['error' => 'Las contraseñas no coinciden'], 422);

$pdo = db();

$existingUser = $pdo->prepare('SELECT 1 FROM users WHERE email=:email LIMIT 1');
$existingUser->execute(['email' => $email]);
if ($existingUser->fetchColumn()) respond(['error' => 'Ya existe una cuenta con ese correo. Inicia sesión.'], 409);

// No vinculamos automáticamente una ficha ya existente solo por conocer su correo:
// evita que alguien pueda apropiarse de datos/citas de otra persona.
$existingClient = $pdo->prepare('SELECT id FROM clients WHERE email=:email LIMIT 1');
$existingClient->execute(['email' => $email]);
if ($existingClient->fetchColumn()) {
    respond(['error' => 'Ya existe una ficha con ese correo. Solicita al SPA que habilite tu acceso.'], 409);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('INSERT INTO clients (name, phone, email, active) VALUES (:name, :phone, :email, 1)');
    $stmt->execute(['name' => $name, 'phone' => $phone, 'email' => $email]);
    $clientId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, client_id, active) VALUES (:name, :email, :password_hash, 'client', :client_id, 1)");
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'client_id' => $clientId,
    ]);
    $userId = (int)$pdo->lastInsertId();

    $pdo->commit();

    establishUserSession([
        'id' => $userId,
        'name' => $name,
        'email' => $email,
        'role' => 'client',
        'client_id' => $clientId,
    ]);

    respond(['ok' => true, 'user' => currentUser()], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof PDOException && (string)$e->getCode() === '23000') {
        respond(['error' => 'No pudimos completar el registro porque esos datos ya están en uso.'], 409);
    }
    respond(['error' => 'No se pudo completar el registro ahora mismo'], 500);
}
