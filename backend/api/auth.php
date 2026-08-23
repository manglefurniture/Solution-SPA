<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = trim((string)($_GET['action'] ?? 'me'));

if ($method === 'GET' && $action === 'me') {
    $user = currentUser();
    if (!$user) {
        respond(['error' => 'Sesión requerida'], 401);
    }
    respond(['user' => $user]);
}

if ($method === 'POST' && $action === 'login') {
    $data = jsonInput();
    requireFields($data, ['email', 'password']);

    $email = strtolower(trim((string)$data['email']));
    $password = (string)$data['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(['error' => 'Correo inválido'], 422);
    }

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role, active FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['active'] !== 1 || !password_verify($password, (string)$user['password_hash'])) {
        usleep(250000);
        respond(['error' => 'Correo o contraseña incorrectos'], 401);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = (string)$user['name'];
    $_SESSION['user_email'] = (string)$user['email'];
    $_SESSION['user_role'] = (string)$user['role'];

    respond(['user' => currentUser()]);
}

if ($method === 'POST' && $action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    respond(['ok' => true]);
}

respond(['error' => 'Ruta no permitida'], 405);
