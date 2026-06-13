<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

requireAdmin();
$db = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('طريقة غير مدعومة', 405);
}

$status = $_GET['status'] ?? '';
$where = ['1=1'];
$params = [];
if ($status) {
    $where[] = 's.status = ?';
    $params[] = $status;
}

$sql = "
    SELECT s.*, u.full_name, u.email, u.role
    FROM subscriptions s
    JOIN users u ON u.id = s.user_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.starts_at DESC
    LIMIT 100
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
sendSuccess($stmt->fetchAll());