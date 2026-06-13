<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$db = getDB();
$db->set_charset('utf8mb4');

$category = isset($_GET['category']) ? $db->real_escape_string($_GET['category']) : '';
$page     = max(1, intval($_GET['page'] ?? 1));
$limit    = min(50, intval($_GET['limit'] ?? 20));
$offset   = ($page - 1) * $limit;

$where = "WHERE i.is_available = 1";
if ($category) $where .= " AND i.category = '$category'";

$sql = "SELECT i.id, i.product_name AS name, i.product_name_ar AS name_ar,
               i.category, i.quantity, i.is_available,
               p.id AS pharmacy_id, p.name AS pharmacy_name, p.wilaya, p.city
        FROM inventory i
        LEFT JOIN pharmacies p ON p.id = i.pharmacy_id
        $where
        ORDER BY i.product_name ASC
        LIMIT $limit OFFSET $offset";

$result = $db->query($sql);
$items  = [];
while ($row = $result->fetch_assoc()) $items[] = $row;
$total = (int)$db->query("SELECT COUNT(*) as c FROM inventory i $where")->fetch_assoc()['c'];
$db->close();

sendJSON(['success' => true, 'data' => $items, 'total' => $total]);
