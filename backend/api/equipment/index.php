<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';

$db     = getDbConnection();
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$q        = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$avail    = trim($_GET['availability'] ?? '');

$where = ['1=1'];
$params = [];

if ($q) {
    $where[] = '(ei.equipment_name LIKE ? OR ei.equipment_name_ar LIKE ? OR ei.brand LIKE ?)';
    $like = "%$q%";
    $params = array_merge($params, [$like, $like, $like]);
}
if ($category) { $where[] = 'ei.category = ?'; $params[] = $category; }
if ($avail)    { $where[] = 'ei.availability = ?'; $params[] = $avail; }

$whereStr = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) FROM equipment_inventory ei WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$stmt = $db->prepare("
    SELECT ei.*, mr.name AS supplier_name, mr.company, mr.wilaya, mr.phone
    FROM equipment_inventory ei
    LEFT JOIN med_reps mr ON ei.supplier_id = mr.id
    WHERE $whereStr
    ORDER BY ei.last_updated DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

sendSuccess([
    'items'       => $items,
    'total'       => $total,
    'page'        => $page,
    'limit'       => $limit,
    'total_pages' => (int)ceil($total / max(1, $limit)),
    'label'       => 'المعدات الطبية',
]);
