<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$db = getDB();
$db->set_charset('utf8mb4');

$qRaw    = trim($_GET['q']            ?? '');
$wilaya  = trim($_GET['wilaya']       ?? '');
$type    = trim($_GET['type']         ?? '');
$avail   = trim($_GET['availability'] ?? '');
$page    = max(1, intval($_GET['page']  ?? 1));
$limit   = min(60,  intval($_GET['limit'] ?? 24));
$offset  = ($page - 1) * $limit;

$allowed = ['medicine', 'device', 'special_needs', 'parapharmacy'];

$where = ['i.is_available = 1'];

if ($qRaw !== '') {
    $like = '%' . $db->real_escape_string($qRaw) . '%';
    $where[] = "(i.product_name LIKE '$like' OR i.product_name_ar LIKE '$like')";
}
if ($wilaya !== '') {
    $ew = $db->real_escape_string($wilaya);
    $where[] = "p.wilaya = '$ew'";
}
if ($type !== '' && in_array($type, $allowed, true)) {
    $et = $db->real_escape_string($type);
    $where[] = "i.category = '$et'";
} else {
    $cats = "'" . implode("','", $allowed) . "'";
    $where[] = "i.category IN ($cats)";
}
if ($avail === 'available')   $where[] = 'i.quantity > 5';
if ($avail === 'limited')     $where[] = 'i.quantity BETWEEN 1 AND 5';
if ($avail === 'unavailable') $where[] = 'i.quantity = 0';

$wsql = 'WHERE ' . implode(' AND ', $where);

$sql = "SELECT
    i.id,
    i.product_name    AS name,
    i.product_name_ar AS name_ar,
    i.category        AS type,
    i.quantity,
    p.name            AS pharmacy_name,
    p.wilaya,
    p.city,
    p.phone           AS pharmacy_phone,
    CASE
        WHEN i.quantity > 5 THEN 'available'
        WHEN i.quantity BETWEEN 1 AND 5 THEN 'limited'
        ELSE 'unavailable'
    END AS availability
FROM inventory i
LEFT JOIN pharmacies p ON i.pharmacy_id = p.id
$wsql
ORDER BY i.id DESC
LIMIT $limit OFFSET $offset";

$result = $db->query($sql);
$items  = [];
while ($row = $result->fetch_assoc()) $items[] = $row;

$cRes  = $db->query("SELECT COUNT(*) AS c FROM inventory i LEFT JOIN pharmacies p ON i.pharmacy_id = p.id $wsql");
$total = $cRes ? intval($cRes->fetch_assoc()['c']) : count($items);

$db->close();
sendJSON(['results' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit]);
