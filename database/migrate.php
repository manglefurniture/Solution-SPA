<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/backend/db.php';

$pdo = db();
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations ("
    . "name VARCHAR(190) PRIMARY KEY, "
    . "applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
    . ") ENGINE=InnoDB"
);

function applied(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE name=:name');
    $stmt->execute(['name' => $name]);
    return (bool)$stmt->fetchColumn();
}

function markApplied(PDO $pdo, string $name): void
{
    $stmt = $pdo->prepare('INSERT IGNORE INTO schema_migrations(name) VALUES(:name)');
    $stmt->execute(['name' => $name]);
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND COLUMN_NAME=:column LIMIT 1'
    );
    $stmt->execute(['table' => $table, 'column' => $column]);
    return (bool)$stmt->fetchColumn();
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.STATISTICS '
        . 'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:table AND INDEX_NAME=:idx LIMIT 1'
    );
    $stmt->execute(['table' => $table, 'idx' => $index]);
    return (bool)$stmt->fetchColumn();
}

function fkExists(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS "
        . "WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME=:name AND CONSTRAINT_TYPE='FOREIGN KEY' LIMIT 1"
    );
    $stmt->execute(['name' => $name]);
    return (bool)$stmt->fetchColumn();
}

function applyMigration(PDO $pdo, string $name, callable $migration): void
{
    if (applied($pdo, $name)) {
        echo "Already applied {$name}\n";
        return;
    }

    try {
        $migration($pdo);
        markApplied($pdo, $name);
        echo "Applied {$name}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Migration {$name} failed: {$e->getMessage()}\n");
        exit(1);
    }
}

applyMigration($pdo, '20260824_productization', static function (PDO $pdo): void {
    if (!columnExists($pdo, 'services', 'image_url')) {
        $pdo->exec('ALTER TABLE services ADD COLUMN image_url VARCHAR(500) NULL AFTER description');
    }
    if (!columnExists($pdo, 'appointments', 'manager_user_id')) {
        $pdo->exec('ALTER TABLE appointments ADD COLUMN manager_user_id BIGINT UNSIGNED NULL AFTER service_id');
    }
    if (!indexExists($pdo, 'appointments', 'idx_appointments_manager')) {
        $pdo->exec('ALTER TABLE appointments ADD INDEX idx_appointments_manager (manager_user_id)');
    }
    if (!fkExists($pdo, 'fk_appointments_manager')) {
        $pdo->exec('ALTER TABLE appointments ADD CONSTRAINT fk_appointments_manager FOREIGN KEY (manager_user_id) REFERENCES users(id) ON DELETE SET NULL');
    }
    if (!indexExists($pdo, 'users', 'uq_users_client_id')) {
        $pdo->exec('ALTER TABLE users ADD UNIQUE INDEX uq_users_client_id (client_id)');
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS payments ("
        . "id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,"
        . "client_id BIGINT UNSIGNED NOT NULL,"
        . "appointment_id BIGINT UNSIGNED NULL,"
        . "amount DECIMAL(10,2) NOT NULL,"
        . "method ENUM('cash','card','transfer','other') NOT NULL DEFAULT 'other',"
        . "status ENUM('pending','paid','refunded','cancelled') NOT NULL DEFAULT 'paid',"
        . "reference VARCHAR(120) NULL,"
        . "notes TEXT NULL,"
        . "paid_at DATETIME NULL,"
        . "created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,"
        . "updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
        . "CONSTRAINT fk_payments_client FOREIGN KEY (client_id) REFERENCES clients(id),"
        . "CONSTRAINT fk_payments_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,"
        . "INDEX idx_payments_client (client_id),"
        . "INDEX idx_payments_appointment (appointment_id),"
        . "INDEX idx_payments_status (status)"
        . ") ENGINE=InnoDB"
    );
});

applyMigration($pdo, '20260825_hache_base_hardening', static function (PDO $pdo): void {
    $path = __DIR__ . '/20260825_hache_base_hardening.sql';
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('Hardening migration file is missing or empty.');
    }
    $pdo->exec($sql);
});

applyMigration($pdo, '20260827_paid_appointment_integrity', static function (PDO $pdo): void {
    $duplicate = $pdo->query(
        "SELECT appointment_id, COUNT(*) AS total FROM payments "
        . "WHERE appointment_id IS NOT NULL AND status='paid' "
        . "GROUP BY appointment_id HAVING COUNT(*) > 1 LIMIT 1"
    )->fetch();
    if ($duplicate) {
        throw new RuntimeException(
            'Existing duplicate paid payments found for appointment ' . (string)$duplicate['appointment_id']
            . '. Resolve them before applying the uniqueness constraint.'
        );
    }

    if (!columnExists($pdo, 'payments', 'paid_appointment_id')) {
        $pdo->exec(
            "ALTER TABLE payments ADD COLUMN paid_appointment_id BIGINT UNSIGNED "
            . "GENERATED ALWAYS AS (CASE WHEN status = 'paid' THEN appointment_id ELSE NULL END) STORED AFTER appointment_id"
        );
    }
    if (!indexExists($pdo, 'payments', 'uq_payments_paid_appointment')) {
        $pdo->exec('ALTER TABLE payments ADD UNIQUE INDEX uq_payments_paid_appointment (paid_appointment_id)');
    }
});

echo "Migrations OK\n";
