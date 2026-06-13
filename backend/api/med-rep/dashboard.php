<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$user = requireAuth(['med_rep', 'admin']);
$pdo  = getDbConnection();

// Resolve rep record — create demo one if missing
$repRow = $pdo->prepare("SELECT * FROM med_reps WHERE user_id = ? LIMIT 1");
$repRow->execute([$user['id']]);
$rep = $repRow->fetch();

if (!$rep) {
    $pdo->prepare("INSERT INTO med_reps (user_id, name, company, wilaya, phone, is_active) VALUES (?, ?, 'شركة الدواء الجزائرية', 'عنابة', '0550 00 00 00', 1)")
        ->execute([$user['id'], $user['name'] ?? 'مزود أدوية']);
    $repId = (int)$pdo->lastInsertId();
    $repRow2 = $pdo->prepare("SELECT * FROM med_reps WHERE id = ? LIMIT 1");
    $repRow2->execute([$repId]);
    $rep = $repRow2->fetch();
}

$repId = (int)$rep['id'];

// Products
$productsStmt = $pdo->prepare("SELECT * FROM rep_products WHERE rep_id = ? ORDER BY status DESC");
$productsStmt->execute([$repId]);
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// Alerts
$alertsStmt = $pdo->prepare("SELECT * FROM rep_alerts WHERE rep_id = ? ORDER BY FIELD(severity,'high','medium','low'), created_at DESC");
$alertsStmt->execute([$repId]);
$alerts = $alertsStmt->fetchAll(PDO::FETCH_ASSOC);

// Partnerships
$partnersStmt = $pdo->prepare("
    SELECT pr.*, p.name AS pharmacy_name, p.city, p.phone AS pharmacy_phone, p.wilaya AS rep_region
    FROM partnership_requests pr
    LEFT JOIN pharmacies p ON pr.pharmacy_id = p.id
    WHERE pr.rep_id = ?
    ORDER BY pr.created_at DESC
");
$partnersStmt->execute([$repId]);
$partners = $partnersStmt->fetchAll(PDO::FETCH_ASSOC);

$acceptedCount = count(array_filter($partners, fn($p) => $p['status'] === 'accepted'));
$urgentAlerts  = count(array_filter($alerts, fn($a) => $a['severity'] === 'high'));

// Pending resupply
try {
    $pendingResupply = (int)$pdo->prepare("SELECT COUNT(*) FROM resupply_requests WHERE rep_id=? AND status='pending'")->execute([$repId]) 
        ? $pdo->query("SELECT COUNT(*) FROM resupply_requests WHERE rep_id=$repId AND status='pending'")->fetchColumn()
        : 0;
} catch (Throwable $e) { $pendingResupply = 0; }

sendJSON([
    'success'         => true,
    'rep'             => [
        'id'     => $repId,
        'name'   => $rep['name'] ?? $rep['company_name'] ?? 'مزود أدوية',
        'region' => $rep['wilaya'] ?? '',
        'phone'  => $rep['phone'] ?? '',
    ],
    'stats'           => [
        'totalProducts'     => count($products),
        'partnerPharmacies' => $acceptedCount,
        'urgentAlerts'      => $urgentAlerts,
        'pendingResupply'   => $pendingResupply,
    ],
    'products'        => $products,
    'alerts'          => $alerts,
    'partnerPharmacies' => $partners,
    'repId'           => $repId,
]);
