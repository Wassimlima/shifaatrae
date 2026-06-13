<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$db = getDB();
$wilaya = isset($_GET['wilaya']) ? $db->real_escape_string($_GET['wilaya']) : '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(50, intval($_GET['limit'] ?? 12));
$offset = ($page - 1) * $limit;

$where = $wilaya ? "WHERE wilaya = '$wilaya'" : "WHERE 1=1";
$result = $db->query("SELECT * FROM donations $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$donations = [];
while ($row = $result->fetch_assoc()) $donations[] = $row;
$total = $db->query("SELECT COUNT(*) as c FROM donations $where")->fetch_assoc()['c'];
$db->close();
sendJSON(['donations' => $donations, 'total' => intval($total)]);
