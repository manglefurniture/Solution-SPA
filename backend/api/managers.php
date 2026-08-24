<?php

declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
requirePermission('appointments.view');
$pdo=db();
$stmt=$pdo->query("SELECT id,name,email FROM users WHERE role='operator' AND active=1 ORDER BY name");
respond(['data'=>$stmt->fetchAll()]);
