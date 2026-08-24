<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = trim((string)($_GET['action'] ?? 'me'));

if ($method === 'GET' && $action === 'me') {
    $user = currentUser();
    if (!$user) respond(['error' => 'Sesión requerida'], 401);
    respond(['user' => $user]);
}

if ($method === 'POST' && $action === 'login') {
    $data = jsonInput();
    requireFields($data, ['email', 'password']);
    $email = strtolower(trim((string)$data['email']));
    $password = (string)$data['password'];
    $remember = !empty($data['remember']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(['error' => 'Correo inválido'], 422);

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role, active, client_id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    if (!$user || (int)$user['active'] !== 1 || !password_verify($password, (string)$user['password_hash'])) {
        usleep(250000);
        respond(['error' => 'Correo o contraseña incorrectos'], 401);
    }

    establishUserSession($user);
    $pdo->prepare('DELETE FROM remember_tokens WHERE expires_at < NOW()')->execute();

    if ($remember) {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + 60 * 60 * 24 * 30;
        $stmt = $pdo->prepare('INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (:user_id, :selector, :hash, :expires)');
        $stmt->execute([
            'user_id' => (int)$user['id'],
            'selector' => $selector,
            'hash' => hash('sha256', $validator),
            'expires' => date('Y-m-d H:i:s', $expires),
        ]);
        setRememberCookie($selector . ':' . $validator, $expires);
    }

    respond(['user' => currentUser()]);
}

if ($method === 'POST' && $action === 'logout') {
    $cookie = (string)($_COOKIE['solution_spa_remember'] ?? '');
    if ($cookie !== '' && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        if (preg_match('/^[a-f0-9]{24}$/', $selector)) {
            $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :selector')->execute(['selector' => $selector]);
        }
    }
    clearRememberCookie();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
    respond(['ok' => true]);
}

respond(['error' => 'Ruta no permitida'], 405);
