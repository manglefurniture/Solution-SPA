<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db.php';

if ($argc < 4) {
    fwrite(STDERR, "Uso: php backend/create_admin.php \"Nombre\" correo@dominio.com \"Contraseña segura\"\n");
    exit(1);
}

$name = trim((string)$argv[1]);
$email = strtolower(trim((string)$argv[2]));
$password = (string)$argv[3];

if ($name === '' || strlen($name) > 120) {
    fwrite(STDERR, "Nombre inválido\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
    fwrite(STDERR, "Correo inválido\n");
    exit(1);
}
if (strlen($password) < 10) {
    fwrite(STDERR, "La contraseña debe tener al menos 10 caracteres\n");
    exit(1);
}

$pdo = db();
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, active) VALUES (:name, :email, :hash, 'admin', 1) ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = 'admin', active = 1");
$stmt->execute(['name' => $name, 'email' => $email, 'hash' => $hash]);

echo "Administrador listo: {$email}\n";
