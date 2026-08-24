<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user = requireAuth();

function strictDate(string $value): ?DateTimeImmutable
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
    [$year,$month,$day]=array_map('intval',explode('-',$value));
    if(!checkdate($month,$day,$year))return null;
    return new DateTimeImmutable($value.' 00:00:00');
}
function strictDateTime(string $value): ?DateTimeImmutable
{
    if(!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',$value))return null;
    $date=DateTimeImmutable::createFromFormat('Y-m-d H:i:s',$value);
    if(!$date||$date->format('Y-m-d H:i:s')!==$value)return null;
    return $date;
}
function validEntityId(mixed $value): int|false
{
    return filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
}
function ensureClient(PDO $pdo,int $id,bool $allowArchived=false):void
{
    $stmt=$pdo->prepare('SELECT active FROM clients WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$id]);$active=$stmt->fetchColumn();
    if($active===false)respond(['error'=>'El cliente no existe'],422);
    if(!$allowArchived&&(int)$active!==1)respond(['error'=>'El cliente está archivado'],422);
}
function ensureService(PDO $pdo,int $id,bool $allowInactive=false):void
{
    $stmt=$pdo->prepare('SELECT active FROM services WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$id]);$active=$stmt->fetchColumn();
    if($active===false)respond(['error'=>'El servicio no existe'],422);
    if(!$allowInactive&&(int)$active!==1)respond(['error'=>'El servicio está inactivo'],422);
}
function ensureNoExactConflict(PDO $pdo,string $startsAt,?int $excludeId=null):void
{
    $sql="SELECT id FROM appointments WHERE starts_at=:starts_at AND status IN ('pending','confirmed')";$params=['starts_at'=>$startsAt];
    if($excludeId!==null){$sql.=' AND id<>:id';$params['id']=$excludeId;}$sql.=' LIMIT 1';
    $stmt=$pdo->prepare($sql);$stmt->execute($params);if($stmt->fetchColumn())respond(['error'=>'Ese horario ya tiene una cita. Elige otra hora.'],409);
}

$select="SELECT a.id,a.starts_at,a.status,a.notes,c.id AS client_id,c.name AS client_name,c.phone,c.active AS client_active,s.id AS service_id,s.name AS service_name,s.duration_minutes,s.active AS service_active FROM appointments a JOIN clients c ON c.id=a.client_id JOIN services s ON s.id=a.service_id";

if($method==='GET'){
    $isClient=$user['role']==='client';
    if(!$isClient) requirePermission('appointments.view');
    $date=trim((string)($_GET['date']??''));$month=trim((string)($_GET['month']??''));$nextFrom=trim((string)($_GET['next_from']??''));$clientIdRaw=trim((string)($_GET['client_id']??''));$idRaw=trim((string)($_GET['id']??''));$sql=$select;$params=[];$limit=500;$where=[];
    if($idRaw!==''){$id=validEntityId($idRaw);if($id===false)respond(['error'=>'Cita inválida'],422);$where[]='a.id=:id';$params['id']=$id;$limit=1;}
    elseif($nextFrom!==''){$from=strictDateTime($nextFrom);if(!$from)respond(['error'=>'Invalid next_from'],422);$where[]='a.starts_at>=:from';$where[]="a.status IN ('pending','confirmed')";$params['from']=$from->format('Y-m-d H:i:s');$limit=isset($_GET['limit'])?max(1,min(20,(int)$_GET['limit'])):1;}
    elseif($date!==''){$start=strictDate($date);if(!$start)respond(['error'=>'Invalid date'],422);$end=$start->modify('+1 day');$where[]='a.starts_at>=:start AND a.starts_at<:end';$params['start']=$start->format('Y-m-d H:i:s');$params['end']=$end->format('Y-m-d H:i:s');}
    elseif($month!==''){if(!preg_match('/^(\d{4})-(\d{2})$/',$month,$parts))respond(['error'=>'Invalid month'],422);$year=(int)$parts[1];$monthNumber=(int)$parts[2];if($monthNumber<1||$monthNumber>12)respond(['error'=>'Invalid month'],422);$start=new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00',$year,$monthNumber));$end=$start->modify('first day of next month');$where[]='a.starts_at>=:start AND a.starts_at<:end';$params['start']=$start->format('Y-m-d H:i:s');$params['end']=$end->format('Y-m-d H:i:s');}

    if($isClient){
        $ownClientId=(int)($user['client_id']??0);
        if($ownClientId<1)respond(['error'=>'Tu cuenta todavía no está vinculada a una ficha de cliente'],403);
        $where[]='a.client_id=:client_id';$params['client_id']=$ownClientId;$clientIdRaw=(string)$ownClientId;$limit=min($limit,100);
    } elseif($clientIdRaw!==''){
        $clientId=validEntityId($clientIdRaw);if($clientId===false)respond(['error'=>'Cliente inválido'],422);$where[]='a.client_id=:client_id';$params['client_id']=$clientId;$limit=min($limit,100);
    }

    if($where)$sql.=' WHERE '.implode(' AND ',$where);$sql.=' ORDER BY a.starts_at '.($clientIdRaw!==''?'DESC':'ASC').' LIMIT '.$limit;$stmt=$pdo->prepare($sql);$stmt->execute($params);$rows=$stmt->fetchAll();
    if($isClient){foreach($rows as &$row){unset($row['notes'],$row['phone']);}unset($row);}
    respond(['data'=>$rows]);
}

if($method==='POST'){
    $data=jsonInput();
    $isClient=$user['role']==='client';
    if($isClient){
        if(!userCan($user,'appointments.own.create'))respond(['error'=>'No tienes permiso para reservar'],403);
        requireFields($data,['service_id','starts_at']);
        $clientId=(int)($user['client_id']??0);
        if($clientId<1)respond(['error'=>'Tu cuenta todavía no está vinculada a una ficha de cliente'],403);
        $status='pending';
        $notes=null;
    }else{
        requirePermission('appointments.create');
        requireFields($data,['client_id','service_id','starts_at']);
        $clientId=validEntityId($data['client_id']);
        if($clientId===false)respond(['error'=>'Cliente inválido'],422);
        $status=(string)($data['status']??'pending');
        $allowed=['pending','confirmed','completed','cancelled'];
        if(!in_array($status,$allowed,true))respond(['error'=>'Estado inválido'],422);
        $notes=isset($data['notes'])?trim((string)$data['notes']):null;
    }
    $serviceId=validEntityId($data['service_id']??null);if($serviceId===false)respond(['error'=>'Servicio inválido'],422);
    $startsAt=strictDateTime(trim((string)($data['starts_at']??'')));if(!$startsAt)respond(['error'=>'Fecha u hora inválida'],422);
    if($isClient && $startsAt <= new DateTimeImmutable('now'))respond(['error'=>'La cita debe ser en una fecha futura'],422);
    ensureClient($pdo,$clientId,false);ensureService($pdo,$serviceId,false);
    if(in_array($status,['pending','confirmed'],true))ensureNoExactConflict($pdo,$startsAt->format('Y-m-d H:i:s'));
    $stmt=$pdo->prepare('INSERT INTO appointments (client_id,service_id,starts_at,status,notes) VALUES (:client_id,:service_id,:starts_at,:status,:notes)');
    $stmt->execute(['client_id'=>$clientId,'service_id'=>$serviceId,'starts_at'=>$startsAt->format('Y-m-d H:i:s'),'status'=>$status,'notes'=>$notes!==''?$notes:null]);
    respond(['id'=>(int)$pdo->lastInsertId(),'status'=>$status],201);
}

if($method==='PATCH'){
    requirePermission('appointments.update');
    $data=jsonInput();$id=validEntityId($data['id']??null);if($id===false)respond(['error'=>'Cita inválida'],422);$stmt=$pdo->prepare('SELECT client_id,service_id,starts_at,status,notes FROM appointments WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$id]);$current=$stmt->fetch();if(!$current)respond(['error'=>'La cita no existe'],404);
    $clientId=isset($data['client_id'])?validEntityId($data['client_id']):(int)$current['client_id'];$serviceId=isset($data['service_id'])?validEntityId($data['service_id']):(int)$current['service_id'];if($clientId===false||$serviceId===false)respond(['error'=>'Cliente o servicio inválido'],422);
    $startsAt=isset($data['starts_at'])?strictDateTime(trim((string)$data['starts_at'])):new DateTimeImmutable((string)$current['starts_at']);if(!$startsAt)respond(['error'=>'Fecha u hora inválida'],422);$status=isset($data['status'])?(string)$data['status']:(string)$current['status'];$allowed=['pending','confirmed','completed','cancelled'];if(!in_array($status,$allowed,true))respond(['error'=>'Estado inválido'],422);
    $sameClient=$clientId===(int)$current['client_id'];$sameService=$serviceId===(int)$current['service_id'];ensureClient($pdo,$clientId,$sameClient);ensureService($pdo,$serviceId,$sameService);
    if(in_array($status,['pending','confirmed'],true))ensureNoExactConflict($pdo,$startsAt->format('Y-m-d H:i:s'),$id);$notes=array_key_exists('notes',$data)?trim((string)$data['notes']):(string)($current['notes']??'');
    $stmt=$pdo->prepare('UPDATE appointments SET client_id=:client_id,service_id=:service_id,starts_at=:starts_at,status=:status,notes=:notes WHERE id=:id');$stmt->execute(['client_id'=>$clientId,'service_id'=>$serviceId,'starts_at'=>$startsAt->format('Y-m-d H:i:s'),'status'=>$status,'notes'=>$notes!==''?$notes:null,'id'=>$id]);respond(['ok'=>true]);
}

if($method==='DELETE'){
    requirePermission('appointments.delete');
    $data=jsonInput();$id=validEntityId($data['id']??null);if($id===false)respond(['error'=>'Cita inválida'],422);$stmt=$pdo->prepare('SELECT 1 FROM appointments WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$id]);if(!$stmt->fetchColumn())respond(['error'=>'La cita no existe'],404);
    try{$pdo->beginTransaction();$stmt=$pdo->prepare('DELETE FROM treatments WHERE appointment_id=:id');$stmt->execute(['id'=>$id]);$stmt=$pdo->prepare('DELETE FROM appointments WHERE id=:id');$stmt->execute(['id'=>$id]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();respond(['error'=>'No se pudo eliminar la cita'],500);}respond(['ok'=>true]);
}

respond(['error'=>'Method not allowed'],405);
