<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

requireAdmin();

$db = getDbConnection();

$safe = function (string $sql, $db) {
    try { return (int)$db->query($sql)->fetchColumn(); } catch (Throwable $e) { return 0; }
};

$users            = $safe("SELECT COUNT(*) FROM users", $db);
$pharmacies       = $safe("SELECT COUNT(*) FROM pharmacies", $db);
$labs             = $safe("SELECT COUNT(*) FROM labs", $db);
$med_reps         = $safe("SELECT COUNT(*) FROM med_reps", $db);
$medicines        = $safe("SELECT COUNT(*) FROM inventory", $db);
$subs             = $safe("SELECT COUNT(*) FROM subscriptions WHERE is_active = 1", $db);
$donationsPending = $safe("SELECT COUNT(*) FROM donations WHERE status IN ('open','pending')", $db);
$medServices      = $safe("SELECT COUNT(*) FROM users WHERE role = 'medical_services'", $db);
$equipmentCount   = $safe("SELECT COUNT(*) FROM equipment_inventory", $db);

$recent_users = $db->query("
    SELECT id, full_name, email, role, is_verified, is_active, created_at
    FROM users ORDER BY created_at DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$activity = [];
$roleAr = [
    'admin'            => 'مدير',
    'pharmacist'       => 'صيدلاني',
    'med_rep'          => 'مزود أدوية',
    'lab'              => 'مخبر',
    'medical_services' => 'معدات طبية',
    'patient'          => 'مريض',
];
foreach ($recent_users as $u) {
    $activity[] = [
        'type'  => 'user',
        'icon'  => 'blue',
        'title' => 'مستخدم جديد — ' . $u['full_name'],
        'meta'  => $roleAr[$u['role']] ?? $u['role'],
        'time'  => $u['created_at'],
    ];
}

$top_medicines = $db->query("
    SELECT product_name AS name, COUNT(*) AS score FROM inventory
    GROUP BY product_name ORDER BY score DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$wilaya_activity = $db->query("
    SELECT wilaya, COUNT(*) AS cnt FROM pharmacies
    WHERE wilaya IS NOT NULL AND wilaya != ''
    GROUP BY wilaya ORDER BY cnt DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$maxWilaya = max(1, ...array_map(fn($w) => (int)$w['cnt'], $wilaya_activity ?: [['cnt' => 1]]));
$maxMed    = max(1, ...array_map(fn($m) => (int)($m['score'] ?? 1), $top_medicines ?: [['score' => 1]]));

sendSuccess([
    'totals' => [
        'users'           => $users,
        'pharmacies'      => $pharmacies,
        'labs'            => $labs,
        'med_reps'        => $med_reps,
        'med_services'    => $medServices,
        'equipment_count' => $equipmentCount,
        'medicines'       => $medicines,
        'subscriptions'   => $subs,
        'donations'       => $donationsPending,
    ],
    'labels' => [
        'med_reps_label'  => 'مزودو الأدوية',
        'equipment_label' => 'المعدات الطبية',
    ],
    'recent_users'     => $recent_users,
    'recent_activity'  => $activity,
    'top_medicines'    => $top_medicines,
    'wilaya_activity'  => $wilaya_activity,
    'wilaya_max'       => $maxWilaya,
    'medicine_max'     => $maxMed,
]);
