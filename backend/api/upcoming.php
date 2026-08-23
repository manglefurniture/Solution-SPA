<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
requireAuth();

$pdo = db();
$limit = (int)($_GET['limit'] ?? 3);
$limit = max(1, min(10, $limit));

$sql = "SELECT a.id, a.starts_at, a.status, a.notes,
               c.id AS client_id, c.name AS client_name, c.phone,
               s.id AS service_id, s.name AS service_name, s.duration_minutes
        FROM appointments a
        JOIN clients c ON c.id = a.client_id
        JOIN services s ON s.id = a.service_id
        WHERE a.starts_at >= NOW()
          AND a.status IN ('pending','confirmed')
        ORDER BY a.starts_at ASC
        LIMIT {$limit}";

$stmt = $pdo->query($sql);
respond(['data' => $stmt->fetchAll()]);
