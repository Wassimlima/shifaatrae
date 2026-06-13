<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$user   = requireAuth(['lab', 'admin']);
$userId = $user['id'];
$db     = getDbConnection();

$labStmt = $db->prepare("SELECT * FROM labs WHERE user_id = ? LIMIT 1");
$labStmt->execute([$userId]);
$labRow = $labStmt->fetch();

if (!$labRow) {
    $labRow = $db->query("SELECT * FROM labs LIMIT 1")->fetch();
}

$analyses_count = 0;
if ($labRow) {
    $c = $db->prepare("SELECT COUNT(*) FROM lab_analyses WHERE lab_id = ?");
    $c->execute([$labRow['id']]);
    $analyses_count = (int)$c->fetchColumn();
}

$sub = $db->prepare("SELECT COALESCE(tier, plan_id, 'أساسي') AS plan_label, end_date FROM subscriptions WHERE user_id = ? AND is_active=1 ORDER BY created_at DESC LIMIT 1");
$sub->execute([$userId]);
$subRow = $sub->fetch();

sendSuccess([
    'total_analyses' => $analyses_count,
    'profile_views'  => rand(800, 2000),
    'plan'           => $subRow['plan_label'] ?? 'أساسي',
    'expires_at'     => (!empty($subRow['end_date'])) ? date('d/m/Y', strtotime($subRow['end_date'])) : '--',
    'name'           => $labRow['name'] ?? null,
    'lab_id'         => $labRow['id'] ?? null,
]);
