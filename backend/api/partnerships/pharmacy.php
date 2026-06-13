<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$user = requireAuth(['pharmacist', 'medical_services', 'admin']);

$db = getDB();
$pharmacyId = resolvePharmacyId($db, $user, isset($_GET['pharmacyId']) ? (int) $_GET['pharmacyId'] : null);

$result = $db->query("
    SELECT pr.*,
           r.company_name AS rep_name,
           r.wilaya AS rep_region,
           r.phone AS rep_phone
    FROM partnership_requests pr
    LEFT JOIN med_reps r ON pr.rep_id = r.id
    WHERE pr.pharmacy_id = $pharmacyId
    ORDER BY pr.created_at DESC
");

$all = [];
while ($r = $result->fetch_assoc()) {
    $all[] = $r;
}

$pending  = array_values(array_filter($all, fn ($r) => $r['status'] === 'pending'));
$accepted = array_values(array_filter($all, fn ($r) => $r['status'] === 'accepted'));
$rejected = array_values(array_filter($all, fn ($r) => $r['status'] === 'rejected'));
$revoked  = array_values(array_filter($all, fn ($r) => $r['status'] === 'revoked'));

$db->close();
sendJSON([
    'pending'  => $pending,
    'accepted' => $accepted,
    'rejected' => $rejected,
    'revoked'  => $revoked,
    'total'    => count($all),
    'pharmacyId' => $pharmacyId,
]);