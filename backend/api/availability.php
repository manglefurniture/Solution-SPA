<?php

declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
$user=requireAuth();
if(($user['role']??'')!=='client')respond(['error'=>'Disponible para clientes'],403);
$pdo=db();
$date=trim((string)($_GET['date']??''));
$serviceId=filter_var($_GET['service_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||$serviceId===false)respond(['error'=>'Fecha o servicio inválido'],422);
$day=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('America/Cancun'));
if(!$day||$day->format('Y-m-d')!==$date)respond(['error'=>'Fecha inválida'],422);
if($day<new DateTimeImmutable('today',new DateTimeZone('America/Cancun')))respond(['data'=>[]]);
$s=$pdo->prepare('SELECT duration_minutes,active FROM services WHERE id=:id LIMIT 1');$s->execute(['id'=>$serviceId]);$service=$s->fetch();
if(!$service||(int)$service['active']!==1)respond(['error'=>'Servicio no disponible'],422);
$duration=max(5,(int)$service['duration_minutes']);
$managers=$pdo->query("SELECT id,name FROM users WHERE role='operator' AND active=1 ORDER BY name")->fetchAll();
$start=$day->setTime(8,0);$close=$day->setTime(16,0);$slots=[];$now=new DateTimeImmutable('now',new DateTimeZone('America/Cancun'));
for($slot=$start;$slot->modify('+'.$duration.' minutes')<=$close;$slot=$slot->modify('+30 minutes')){
  if($slot<=$now)continue;
  $end=$slot->modify('+'.$duration.' minutes');
  $availableManager=null;
  if($managers){
    foreach($managers as $m){
      $q=$pdo->prepare("SELECT 1 FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.manager_user_id=:manager AND a.status IN ('pending','confirmed') AND a.starts_at<:end AND DATE_ADD(a.starts_at,INTERVAL s.duration_minutes MINUTE)>:start LIMIT 1");
      $q->execute(['manager'=>$m['id'],'end'=>$end->format('Y-m-d H:i:s'),'start'=>$slot->format('Y-m-d H:i:s')]);
      if(!$q->fetchColumn()){$availableManager=(int)$m['id'];break;}
    }
  }else{
    $q=$pdo->prepare("SELECT 1 FROM appointments a JOIN services s ON s.id=a.service_id WHERE a.status IN ('pending','confirmed') AND a.starts_at<:end AND DATE_ADD(a.starts_at,INTERVAL s.duration_minutes MINUTE)>:start LIMIT 1");
    $q->execute(['end'=>$end->format('Y-m-d H:i:s'),'start'=>$slot->format('Y-m-d H:i:s')]);
    if(!$q->fetchColumn())$availableManager=0;
  }
  if($availableManager!==null)$slots[]=['time'=>$slot->format('H:i'),'starts_at'=>$slot->format('Y-m-d H:i:s')];
}
respond(['data'=>$slots,'meta'=>['open'=>'08:00','close'=>'16:00','duration_minutes'=>$duration]]);
