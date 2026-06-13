<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$user = requireAuth(['pharmacist', 'medical_services', 'med_rep', 'admin']);

$db = getDB();
$where = 'WHERE 1=1';
$types = '';
$params = [];

if (isAdmin($user)) {
    if (!empty($_GET['repId'])) {
        $where .= ' AND rr.rep_id = ?';
        $types .= 'i';
        $params[] = (int) $_GET['repId'];
    }
    if (!empty($_GET['pharmacyId'])) {
        $where .= ' AND rr.pharmacy_id = ?';
        $types .= 'i';
        $params[] = (int) $_GET['pharmacyId'];
    }
} elseif ($user['role'] === 'med_rep') {
    $repId = resolveRepId($db, $user, null);
    $where .= ' AND rr.rep_id = ?';
    $types .= 'i';
    $params[] = $repId;
} else {
    $pharmacyId = resolvePharmacyId($db, $user, isset($_GET['pharmacyId']) ? (int) $_GET['pharmacyId'] : null);
    $where .= ' AND rr.pharmacy_id = ?';
    $types .= 'i';
    $params[] = $pharmacyId;
}

$sql = "
    SELECT rr.*,
           p.name AS pharmacy_name,
           r.company_name AS rep_name,
           r.phone AS rep_phone
    FROM resupply_requests rr
    LEFT JOIN pharmacies p ON rr.pharmacy_id = p.id
    LEFT JOIN med_reps r ON rr.rep_id = r.id
    $where
    ORDER BY rr.created_at DESC
";

$stmt = $db->prepare($sql);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/** Map DB ENUM to UI labels used by pharmacy/med-rep dashboards */
$dbToUi = [
    'pending'   => 'pending',
    'approved'  => 'confirmed',
    'completed' => 'sent',
    'rejected'  => 'rejected',
];
foreach ($list as &$row) {
    $row['status'] = $dbToUi[$row['status']] ?? $row['status'];
}
unset($row);

$db->close();
sendJSON($list);