<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';

$db     = getDbConnection();
$wilaya = trim($_GET['wilaya'] ?? '');
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = ['mr.is_active = 1'];
$params = [];

if ($wilaya) { $where[] = 'mr.wilaya = ?'; $params[] = $wilaya; }
if ($q) {
    $where[] = '(mr.name LIKE ? OR mr.company LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$whereStr = implode(' AND ', $where);

$count = $db->prepare("SELECT COUNT(DISTINCT mr.id) FROM med_reps mr WHERE $whereStr");
$count->execute($params);
$total = (int)$count->fetchColumn();

$stmt = $db->prepare("
    SELECT mr.id, mr.name, mr.company, mr.wilaya, mr.phone,
           COUNT(ei.id) AS equipment_count
    FROM med_reps mr
    LEFT JOIN equipment_inventory ei ON ei.supplier_id = mr.id
    WHERE $whereStr
    GROUP BY mr.id, mr.name, mr.company, mr.wilaya, mr.phone
    ORDER BY equipment_count DESC, mr.name ASC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);

sendSuccess([
    'suppliers'   => $stmt->fetchAll(PDO::FETCH_ASSOC),
    'total'       => $total,
    'page'        => $page,
    'total_pages' => (int)ceil($total / $limit),
    'label'       => 'مزودو الأدوية',
]);
