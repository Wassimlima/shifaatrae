<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$user = requireAuth(['pharmacist', 'medical_services', 'admin']);

$db = getDB();
$pharmacyId = resolvePharmacyId($db, $user, isset($_GET['pharmacyId']) ? (int) $_GET['pharmacyId'] : null);

$total      = (int) $db->query("SELECT COUNT(*) AS c FROM inventory WHERE pharmacy_id=$pharmacyId")->fetch_assoc()['c'];
$lowStock   = 0;
$outOfStock = (int) $db->query("SELECT COUNT(*) AS c FROM inventory WHERE pharmacy_id=$pharmacyId AND is_available=0")->fetch_assoc()['c'];
$todayRes   = (int) $db->query("SELECT COUNT(*) AS c FROM reservations WHERE pharmacy_id=$pharmacyId AND DATE(created_at)=CURDATE()")->fetch_assoc()['c'];
$pendingRes = (int) $db->query("SELECT COUNT(*) AS c FROM reservations WHERE pharmacy_id=$pharmacyId AND status='pending'")->fetch_assoc()['c'];
$available  = $total > 0 ? round((($total - $outOfStock) / $total) * 100) : 100;

$subscriptionPlan = 'free';
$subRow = $db->query(
    "SELECT COALESCE(tier, plan_id, 'free') AS plan_label FROM subscriptions WHERE user_id={$user['id']} AND is_active=1 ORDER BY created_at DESC LIMIT 1"
)->fetch_assoc();
if ($subRow && !empty($subRow['plan_label'])) {
    $subscriptionPlan = $subRow['plan_label'];
}

$db->close();

sendJSON([
    'totalProducts'       => $total,
    'lowStockCount'       => $lowStock,
    'todayReservations'   => $todayRes,
    'availabilityRate'    => $available,
    'pharmacyId'          => $pharmacyId,
    // Aliases for medical-services dashboard
    'products'            => $total,
    'active_orders'       => $todayRes,
    'notifications'       => $pendingRes,
    'subscription_plan'   => $subscriptionPlan,
]);