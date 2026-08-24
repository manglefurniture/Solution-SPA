<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';

date_default_timezone_set('America/Cancun');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function requestIsSecure(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('solution_spa_session');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>requestIsSecure(),'httponly'=>true,'samesite'=>'Lax']);
    session_start();
}

function jsonInput(): array { $raw=file_get_contents('php://input'); if($raw===false||trim($raw)==='')return []; $data=json_decode($raw,true); if(!is_array($data))respond(['error'=>'JSON inválido'],400); return $data; }
function respond(array $payload,int $status=200):never { http_response_code($status); echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function requireFields(array $data,array $fields):void { foreach($fields as $field)if(!isset($data[$field])||trim((string)$data[$field])==='')respond(['error'=>"Falta el campo: {$field}"],422); }
function canonicalRole(string $role):string{return $role==='staff'?'operator':$role;}
function roleLabel(string $role):string{return match(canonicalRole($role)){'admin'=>'Administrador','operator'=>'Gestor','client'=>'Cliente',default=>'Usuario'};}
function rolePermissions(string $role):array{$role=canonicalRole($role);$map=['admin'=>['*'],'operator'=>['clients.view','clients.create','clients.update','appointments.view','appointments.create','appointments.update','services.view','web_requests.view','web_requests.update'],'client'=>['profile.view','appointments.own.view','appointments.own.create','services.view']];return $map[$role]??[];}
function userCan(array $user,string $permission):bool{$p=rolePermissions((string)($user['role']??''));return in_array('*',$p,true)||in_array($permission,$p,true);}
function requirePermission(string $permission):array{$user=requireAuth();if(!userCan($user,$permission))respond(['error'=>'No tienes permiso para realizar esta acción'],403);return $user;}

function requestIp(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR']??'unknown'),0,64);
}
function enforceRateLimit(string $bucket,int $limit,int $windowSeconds):void
{
    $now=time();$dir=sys_get_temp_dir().'/solution-spa-rate-limits';
    if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))return;
    $key=hash('sha256',$bucket.'|'.requestIp());$path=$dir.'/'.$key.'.json';$fp=@fopen($path,'c+');if(!$fp)return;
    try{
        if(!flock($fp,LOCK_EX))return;
        $raw=stream_get_contents($fp);$state=json_decode($raw?:'{}',true);if(!is_array($state))$state=[];
        $start=(int)($state['start']??$now);$count=(int)($state['count']??0);
        if($start>$now||$now-$start>=$windowSeconds){$start=$now;$count=0;}
        if($count>=$limit){$retry=max(1,$windowSeconds-($now-$start));header('Retry-After: '.$retry);respond(['error'=>'Demasiados intentos. Espera un momento antes de volver a intentar.'],429);}
        $count++;rewind($fp);ftruncate($fp,0);fwrite($fp,json_encode(['start'=>$start,'count'=>$count]));fflush($fp);
    }finally{flock($fp,LOCK_UN);fclose($fp);}
}

function csrfToken(): string
{
    if(empty($_SESSION['csrf_token'])||!is_string($_SESSION['csrf_token']))$_SESSION['csrf_token']=bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function sameOriginRequest(): bool
{
    $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
    if($origin==='')return false;
    $originHost=(string)parse_url($origin,PHP_URL_HOST);
    $host=preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??''));
    return $originHost!==''&&$host!==''&&strcasecmp($originHost,$host)===0;
}
function requireCsrf(): void
{
    $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
    if(in_array($method,['GET','HEAD','OPTIONS'],true))return;

    $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
    if($origin!==''&&!sameOriginRequest())respond(['error'=>'Origen de solicitud no permitido'],403);

    $sent=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
    $expected=(string)($_SESSION['csrf_token']??'');
    if($expected!==''&&$sent!==''&&hash_equals($expected,$sent))return;

    // Los navegadores modernos envían Origin en fetch POST/PATCH/DELETE.
    // Una petición same-origin ya queda protegida frente a CSRF externo;
    // el token sigue siendo la vía principal y cubre clientes sin Origin.
    if(sameOriginRequest())return;

    respond(['error'=>'La sesión de seguridad expiró. Recarga la página e inténtalo de nuevo.'],419);
}
function enforceAuthenticatedMutationCsrf():void
{
    $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
    if(!in_array($method,['POST','PATCH','PUT','DELETE'],true)||empty($_SESSION['user_id']))return;

    $script=basename((string)($_SERVER['SCRIPT_NAME']??''));
    $action=(string)($_GET['action']??'');
    // Login/logout deben poder completar siempre su ciclo de sesión.
    // En particular logout tiene que poder borrar el token "recordarme".
    if($script==='auth.php'&&in_array($action,['login','logout'],true))return;

    requireCsrf();
}

function setRememberCookie(string $value,int $expires):void{setcookie('solution_spa_remember',$value,['expires'=>$expires,'path'=>'/','secure'=>requestIsSecure(),'httponly'=>true,'samesite'=>'Lax']);}
function clearRememberCookie():void{setRememberCookie('',time()-3600);}
function establishUserSession(array $user):void{session_regenerate_id(true);$_SESSION['user_id']=(int)$user['id'];$_SESSION['user_name']=(string)$user['name'];$_SESSION['user_email']=(string)$user['email'];$_SESSION['user_role']=canonicalRole((string)$user['role']);$_SESSION['client_id']=isset($user['client_id'])&&$user['client_id']!==null?(int)$user['client_id']:null;$_SESSION['csrf_token']=bin2hex(random_bytes(32));}
function restoreRememberedUser():?array{$cookie=(string)($_COOKIE['solution_spa_remember']??'');if($cookie===''||!str_contains($cookie,':'))return null;[$selector,$validator]=explode(':',$cookie,2);if(!preg_match('/^[a-f0-9]{24}$/',$selector)||!preg_match('/^[a-f0-9]{64}$/',$validator)){clearRememberCookie();return null;}try{$pdo=db();$stmt=$pdo->prepare("SELECT rt.id AS token_id,rt.validator_hash,rt.expires_at,u.id,u.name,u.email,u.role,u.active,u.client_id FROM remember_tokens rt JOIN users u ON u.id=rt.user_id WHERE rt.selector=:selector LIMIT 1");$stmt->execute(['selector'=>$selector]);$row=$stmt->fetch();if(!$row||(int)$row['active']!==1||strtotime((string)$row['expires_at'])<time()||!hash_equals((string)$row['validator_hash'],hash('sha256',$validator))){if($row)$pdo->prepare('DELETE FROM remember_tokens WHERE id=:id')->execute(['id'=>$row['token_id']]);clearRememberCookie();return null;}establishUserSession($row);$newValidator=bin2hex(random_bytes(32));$expires=time()+2592000;$pdo->prepare('UPDATE remember_tokens SET validator_hash=:hash,expires_at=:expires WHERE id=:id')->execute(['hash'=>hash('sha256',$newValidator),'expires'=>date('Y-m-d H:i:s',$expires),'id'=>$row['token_id']]);setRememberCookie($selector.':'.$newValidator,$expires);return $row;}catch(Throwable $e){return null;}}
function currentUser():?array{if(empty($_SESSION['user_id']))restoreRememberedUser();if(empty($_SESSION['user_id']))return null;$role=canonicalRole((string)($_SESSION['user_role']??'operator'));return ['id'=>(int)$_SESSION['user_id'],'name'=>(string)($_SESSION['user_name']??''),'email'=>(string)($_SESSION['user_email']??''),'role'=>$role,'role_label'=>roleLabel($role),'client_id'=>isset($_SESSION['client_id'])&&$_SESSION['client_id']!==null?(int)$_SESSION['client_id']:null,'permissions'=>rolePermissions($role),'csrf_token'=>csrfToken()];}
function requireAuth():array{$user=currentUser();if(!$user)respond(['error'=>'Sesión requerida'],401);return $user;}

enforceAuthenticatedMutationCsrf();
