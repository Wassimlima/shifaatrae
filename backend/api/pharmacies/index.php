<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$db = getDB();
$wilaya = isset($_GET['wilaya']) ? $db->real_escape_string($_GET['wilaya']) : '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(50, intval($_GET['limit'] ?? 12));
$offset = ($page - 1) * $limit;

$where = $wilaya ? "WHERE wilaya = '$wilaya'" : '';
$result = $db->query("SELECT * FROM pharmacies $where ORDER BY name ASC LIMIT $limit OFFSET $offset");
$pharmacies = [];
while ($row = $result->fetch_assoc()) $pharmacies[] = $row;
$total = $db->query("SELECT COUNT(*) as c FROM pharmacies $where")->fetch_assoc()['c'];
$db->close();
sendJSON(['pharmacies' => $pharmacies, 'total' => intval($total)]);
