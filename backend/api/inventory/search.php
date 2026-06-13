<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$db = getDB();
$db->set_charset('utf8mb4');

$q        = isset($_GET['q'])        ? '%' . $db->real_escape_string(trim($_GET['q'])) . '%'     : '%';
$category = isset($_GET['category']) ? $db->real_escape_string($_GET['category'])                  : '';
$wilaya   = isset($_GET['wilaya'])   ? $db->real_escape_string($_GET['wilaya'])                    : '';
$page     = max(1, intval($_GET['page'] ?? 1));
$limit    = min(60, intval($_GET['limit'] ?? 20));
$offset   = ($page - 1) * $limit;

$where = "WHERE (i.product_name LIKE '$q' OR i.product_name_ar LIKE '$q')";
if ($category) $where .= " AND i.category = '$category'";
if ($wilaya)   $where .= " AND p.wilaya = '$wilaya'";

$sql = "SELECT i.id, i.product_name AS name, i.product_name_ar AS name_ar,
               i.category, i.quantity, i.is_available,
               p.id AS pharmacy_id, p.name AS pharmacy_name, p.wilaya, p.city, p.phone
        FROM inventory i
        LEFT JOIN pharmacies p ON p.id = i.pharmacy_id
        $where
        ORDER BY i.is_available DESC, i.product_name ASC
        LIMIT $limit OFFSET $offset";

$result = $db->query($sql);
$items  = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id'            => (int)$row['id'],
        'name'          => $row['name'],
        'name_ar'       => $row['name_ar'],
        'category'      => $row['category'],
        'is_available'  => (bool)$row['is_available'],
        'quantity'      => (int)$row['quantity'],
        'pharmacy_id'   => (int)$row['pharmacy_id'],
        'pharmacy_name' => $row['pharmacy_name'],
        'wilaya'        => $row['wilaya'],
        'city'          => $row['city'],
        'phone'         => $row['phone'],
    ];
}

$countQ = "SELECT COUNT(*) as c FROM inventory i LEFT JOIN pharmacies p ON p.id = i.pharmacy_id $where";
$total  = (int)$db->query($countQ)->fetch_assoc()['c'];
$db->close();

sendJSON(['success' => true, 'results' => $items, 'data' => $items, 'total' => $total]);
