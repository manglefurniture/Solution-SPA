<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/backend/db.php';

$pdo=db();
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");

function applied(PDO $pdo,string $name):bool{$s=$pdo->prepare('SELECT 1 FROM schema_migrations WHERE name=:n');$s->execute(['n'=>$name]);return (bool)$s->fetchColumn();}
function columnExists(PDO $pdo,string $table,string $column):bool{$s=$pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND COLUMN_NAME=:c LIMIT 1");$s->execute(['t'=>$table,'c'=>$column]);return (bool)$s->fetchColumn();}
function indexExists(PDO $pdo,string $table,string $index):bool{$s=$pdo->prepare("SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:t AND INDEX_NAME=:i LIMIT 1");$s->execute(['t'=>$table,'i'=>$index]);return (bool)$s->fetchColumn();}
function fkExists(PDO $pdo,string $name):bool{$s=$pdo->prepare("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME=:n AND CONSTRAINT_TYPE='FOREIGN KEY' LIMIT 1");$s->execute(['n'=>$name]);return (bool)$s->fetchColumn();}

$name='20260824_productization';
if(!applied($pdo,$name)){
  $pdo->beginTransaction();
  try{
    if(!columnExists($pdo,'services','image_url'))$pdo->exec("ALTER TABLE services ADD COLUMN image_url VARCHAR(500) NULL AFTER description");
    if(!columnExists($pdo,'appointments','manager_user_id'))$pdo->exec("ALTER TABLE appointments ADD COLUMN manager_user_id BIGINT UNSIGNED NULL AFTER service_id");
    if(!indexExists($pdo,'appointments','idx_appointments_manager'))$pdo->exec("ALTER TABLE appointments ADD INDEX idx_appointments_manager (manager_user_id)");
    if(!fkExists($pdo,'fk_appointments_manager'))$pdo->exec("ALTER TABLE appointments ADD CONSTRAINT fk_appointments_manager FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE SET NULL");
    if(!indexExists($pdo,'users','uq_users_client_id'))$pdo->exec("ALTER TABLE users ADD UNIQUE INDEX uq_users_client_id (client_id)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      client_id BIGINT UNSIGNED NOT NULL,
      appointment_id BIGINT UNSIGNED NULL,
      amount DECIMAL(10,2) NOT NULL,
      method ENUM('cash','card','transfer','other') NOT NULL DEFAULT 'other',
      status ENUM('pending','paid','refunded','cancelled') NOT NULL DEFAULT 'paid',
      reference VARCHAR(120) NULL,
      notes TEXT NULL,
      paid_at DATETIME NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_payments_client FOREIGN KEY (client_id) REFERENCES clients(id),
      CONSTRAINT fk_payments_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
      INDEX idx_payments_client (client_id), INDEX idx_payments_appointment (appointment_id), INDEX idx_payments_status (status)
    ) ENGINE=InnoDB");
    $s=$pdo->prepare('INSERT INTO schema_migrations(name) VALUES(:n)');$s->execute(['n'=>$name]);
    $pdo->commit();echo "Applied {$name}\n";
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,"Migration failed: {$e->getMessage()}\n");exit(1);}
}else echo "Already applied {$name}\n";
