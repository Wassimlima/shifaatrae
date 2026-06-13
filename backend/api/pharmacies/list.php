<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$db = getDB();
$db->set_charset('utf8mb4');

$wilaya = isset($_GET['wilaya']) ? $db->real_escape_string($_GET['wilaya']) : '';
$q      = isset($_GET['q'])      ? '%' . $db->real_escape_string($_GET['q']) . '%' : '%';
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = min(50, intval($_GET['limit'] ?? 12));
$offset = ($page - 1) * $limit;

$where = "WHERE (p.name LIKE '$q' OR p.city LIKE '$q')";
if ($wilaya) $where .= " AND p.wilaya = '$wilaya'";

$result     = $db->query("SELECT p.*, (SELECT COUNT(*) FROM inventory i WHERE i.pharmacy_id = p.id) AS items_count
                           FROM pharmacies p $where ORDER BY p.name ASC LIMIT $limit OFFSET $offset");
$pharmacies = [];
while ($row = $result->fetch_assoc()) $pharmacies[] = $row;
$total = (int)$db->query("SELECT COUNT(*) as c FROM pharmacies p $where")->fetch_assoc()['c'];
$db->close();

sendJSON(['success' => true, 'pharmacies' => $pharmacies, 'data' => $pharmacies, 'total' => $total]);
