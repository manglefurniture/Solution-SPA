<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$admin = requirePermission('users.manage');
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function userId(mixed $value): int|false
{
    return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

function validateRole(string $role): string
{
    if (!in_array($role, ['admin','operator','client'], true)) respond(['error' => 'Rol inválido'], 422);
    return $role;
}

function validateClientLink(PDO $pdo, string $role, mixed $clientId): ?int
{
    if ($role !== 'client') return null;
    $id = userId($clientId);
    if ($id === false) respond(['error' => 'El usuario cliente debe vincularse a una ficha de cliente'], 422);
    $stmt = $pdo->prepare('SELECT 1 FROM clients WHERE id=:id LIMIT 1');
    $stmt->execute(['id' => $id]);
    if (!$stmt->fetchColumn()) respond(['error' => 'La ficha de cliente no existe'], 422);
    return $id;
}

function userTxFail(PDO $pdo, array $payload, int $status): never
{
    if ($pdo->inTransaction()) $pdo->rollBack();
    respond($payload, $status);
}

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT u.id,u.name,u.email,u.role,u.client_id,u.active,u.created_at,c.name AS client_name FROM users u LEFT JOIN clients c ON c.id=u.client_id ORDER BY u.active DESC, FIELD(u.role,'admin','operator','client'),u.name");
    respond(['data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = jsonInput();
    requireFields($data, ['name','email','password','role']);
    $name = trim((string)$data['name']);
    $email = strtolower(trim((string)$data['email']));
    $password = (string)$data['password'];
    $role = validateRole((string)$data['role']);
    if (strlen($name) > 120) respond(['error' => 'Nombre demasiado largo'], 422);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) respond(['error' => 'Correo inválido'], 422);
    if (strlen($password) < 8) respond(['error' => 'La contraseña debe tener al menos 8 caracteres'], 422);
    $clientId = validateClientLink($pdo, $role, $data['client_id'] ?? null);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO users (name,email,password_hash,role,client_id,active) VALUES (:name,:email,:password_hash,:role,:client_id,1)');
        $stmt->execute(['name'=>$name,'email'=>$email,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role,'client_id'=>$clientId]);
        $id = (int)$pdo->lastInsertId();
        auditMutationRequired($pdo, $admin, 'user.created', 'user', $id, null, ['name'=>$name,'email'=>$email,'role'=>$role,'client_id'=>$clientId,'active'=>1], ['permission_change'=>true]);
        $pdo->commit();
        respond(['id' => $id], 201);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((string)$e->getCode() === '23000') respond(['error' => 'Ya existe un usuario con ese correo'], 409);
        respond(['error' => 'No se pudo crear el usuario'], 500);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['error' => 'No se pudo crear el usuario'], 500);
    }
}

if ($method === 'PATCH') {
    $data = jsonInput();
    $id = userId($data['id'] ?? null);
    if ($id === false) respond(['error' => 'Usuario inválido'], 422);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id,name,email,role,client_id,active FROM users WHERE id=:id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id'=>$id]);
        $current = $stmt->fetch();
        if (!$current) userTxFail($pdo, ['error' => 'El usuario no existe'], 404);

        $name = trim((string)($data['name'] ?? $current['name']));
        $email = strtolower(trim((string)($data['email'] ?? $current['email'])));
        $roleRaw = (string)($data['role'] ?? $current['role']);
        if (!in_array($roleRaw, ['admin','operator','client'], true)) userTxFail($pdo, ['error'=>'Rol inválido'], 422);
        $role = $roleRaw;
        $active = array_key_exists('active',$data) ? ((bool)$data['active'] ? 1 : 0) : (int)$current['active'];

        if (strlen($name) > 120) userTxFail($pdo, ['error'=>'Nombre demasiado largo'], 422);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) userTxFail($pdo, ['error'=>'Correo inválido'], 422);
        if ($id === (int)$admin['id'] && ($active !== 1 || $role !== 'admin')) userTxFail($pdo, ['error'=>'No puedes quitarte tu propio acceso de administrador'], 409);

        $clientId = null;
        if ($role === 'client') {
            $candidate = userId($data['client_id'] ?? $current['client_id']);
            if ($candidate === false) userTxFail($pdo, ['error'=>'El usuario cliente debe vincularse a una ficha de cliente'], 422);
            $link = $pdo->prepare('SELECT 1 FROM clients WHERE id=:id LIMIT 1');
            $link->execute(['id'=>$candidate]);
            if (!$link->fetchColumn()) userTxFail($pdo, ['error'=>'La ficha de cliente no existe'], 422);
            $clientId = $candidate;
        }

        $params=['id'=>$id,'name'=>$name,'email'=>$email,'role'=>$role,'client_id'=>$clientId,'active'=>$active];
        $sql='UPDATE users SET name=:name,email=:email,role=:role,client_id=:client_id,active=:active';
        $passwordChanged = false;
        if (isset($data['password']) && (string)$data['password'] !== '') {
            $password=(string)$data['password'];
            if(strlen($password)<8) userTxFail($pdo, ['error'=>'La contraseña debe tener al menos 8 caracteres'], 422);
            $sql.=',password_hash=:password_hash';
            $params['password_hash']=password_hash($password,PASSWORD_DEFAULT);
            $passwordChanged = true;
        }
        $sql.=' WHERE id=:id';
        $pdo->prepare($sql)->execute($params);

        auditMutationRequired(
            $pdo,
            $admin,
            'user.updated',
            'user',
            $id,
            $current,
            ['id'=>$id,'name'=>$name,'email'=>$email,'role'=>$role,'client_id'=>$clientId,'active'=>$active],
            ['password_changed'=>$passwordChanged,'permission_change'=>true]
        );
        $pdo->commit();
        respond(['ok'=>true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((string)$e->getCode()==='23000') respond(['error'=>'Ya existe un usuario con ese correo'],409);
        respond(['error'=>'No se pudo actualizar el usuario'],500);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        respond(['error'=>'No se pudo actualizar el usuario'],500);
    }
}

respond(['error'=>'Method not allowed'],405);
