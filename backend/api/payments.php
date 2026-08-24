<?php

declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
$pdo=db();$method=$_SERVER['REQUEST_METHOD']??'GET';$user=requireAuth();
function payId(mixed $v):int|false{return filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);}
function completedAppointmentForClient(PDO $pdo,int $appointmentId,int $clientId):array{
  $q=$pdo->prepare('SELECT id,client_id,status FROM appointments WHERE id=:id LIMIT 1');
  $q->execute(['id'=>$appointmentId]);$a=$q->fetch();
  if(!$a)respond(['error'=>'La cita no existe'],422);
  if((int)$a['client_id']!==$clientId)respond(['error'=>'La cita no pertenece al cliente seleccionado'],422);
  if((string)$a['status']!=='completed')respond(['error'=>'Solo se puede registrar un pago cuando la cita está marcada como realizada'],422);
  return $a;
}
if($method==='GET'){
  if(($user['role']??'')==='client'){
    if(!userCan($user,'payments.own.view'))respond(['error'=>'Sin permiso'],403);$cid=(int)($user['client_id']??0);if($cid<1)respond(['error'=>'Cuenta sin ficha'],403);
    $q=$pdo->prepare("SELECT p.id,p.appointment_id,p.amount,p.method,p.status,p.reference,p.paid_at,p.created_at,s.name service_name,a.starts_at FROM payments p LEFT JOIN appointments a ON a.id=p.appointment_id LEFT JOIN services s ON s.id=a.service_id WHERE p.client_id=:cid ORDER BY COALESCE(p.paid_at,p.created_at) DESC LIMIT 100");$q->execute(['cid'=>$cid]);respond(['data'=>$q->fetchAll()]);
  }
  requirePermission('payments.view');$client=payId($_GET['client_id']??null);$where='';$params=[];if($client!==false){$where=' WHERE p.client_id=:cid';$params['cid']=$client;}$q=$pdo->prepare("SELECT p.*,c.name client_name,s.name service_name,a.starts_at FROM payments p JOIN clients c ON c.id=p.client_id LEFT JOIN appointments a ON a.id=p.appointment_id LEFT JOIN services s ON s.id=a.service_id{$where} ORDER BY COALESCE(p.paid_at,p.created_at) DESC LIMIT 200");$q->execute($params);respond(['data'=>$q->fetchAll()]);
}
if($method==='POST'){
  requirePermission('payments.update');$d=jsonInput();requireFields($d,['client_id','appointment_id','amount']);$cid=payId($d['client_id']);if($cid===false)respond(['error'=>'Cliente inválido'],422);$amount=$d['amount'];if(!is_numeric($amount)||(float)$amount<=0)respond(['error'=>'Importe inválido'],422);$appointment=payId($d['appointment_id']);if($appointment===false)respond(['error'=>'Selecciona una cita realizada'],422);completedAppointmentForClient($pdo,$appointment,$cid);$methodName=(string)($d['method']??'other');$status=(string)($d['status']??'paid');if(!in_array($methodName,['cash','card','transfer','other'],true)||!in_array($status,['pending','paid','refunded','cancelled'],true))respond(['error'=>'Método o estado inválido'],422);$paidAt=$status==='paid'?trim((string)($d['paid_at']??date('Y-m-d H:i:s'))):null;$q=$pdo->prepare('INSERT INTO payments(client_id,appointment_id,amount,method,status,reference,notes,paid_at) VALUES(:client,:appointment,:amount,:method,:status,:reference,:notes,:paid_at)');$q->execute(['client'=>$cid,'appointment'=>$appointment,'amount'=>(float)$amount,'method'=>$methodName,'status'=>$status,'reference'=>trim((string)($d['reference']??''))?:null,'notes'=>trim((string)($d['notes']??''))?:null,'paid_at'=>$paidAt]);respond(['id'=>(int)$pdo->lastInsertId()],201);
}
if($method==='PATCH'){
  requirePermission('payments.update');$d=jsonInput();$id=payId($d['id']??null);if($id===false)respond(['error'=>'Pago inválido'],422);$q=$pdo->prepare('SELECT * FROM payments WHERE id=:id');$q->execute(['id'=>$id]);$cur=$q->fetch();if(!$cur)respond(['error'=>'El pago no existe'],404);$status=(string)($d['status']??$cur['status']);$methodName=(string)($d['method']??$cur['method']);if(!in_array($methodName,['cash','card','transfer','other'],true)||!in_array($status,['pending','paid','refunded','cancelled'],true))respond(['error'=>'Método o estado inválido'],422);$amount=$d['amount']??$cur['amount'];if(!is_numeric($amount)||(float)$amount<=0)respond(['error'=>'Importe inválido'],422);$paidAt=$status==='paid'?($cur['paid_at']?:date('Y-m-d H:i:s')):null;$pdo->prepare('UPDATE payments SET amount=:amount,method=:method,status=:status,reference=:reference,notes=:notes,paid_at=:paid_at WHERE id=:id')->execute(['amount'=>(float)$amount,'method'=>$methodName,'status'=>$status,'reference'=>trim((string)($d['reference']??$cur['reference']??''))?:null,'notes'=>trim((string)($d['notes']??$cur['notes']??''))?:null,'paid_at'=>$paidAt,'id'=>$id]);respond(['ok'=>true]);
}
respond(['error'=>'Método no permitido'],405);
