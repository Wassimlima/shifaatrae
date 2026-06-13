<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$user = requireAuth(['medical_services', 'pharmacist', 'lab', 'admin']);
$db = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->prepare("
        SELECT id, title, title_ar, description, ad_type, status, impressions, clicks,
               starts_at, ends_at, created_at
        FROM advertisements
        WHERE advertiser_user_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user['id']]);
    sendSuccess(['campaigns' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $data = getBody();
    if (empty($data['title'])) {
        sendError('عنوان الحملة مطلوب', 400);
    }
    $stmt = $db->prepare("
        INSERT INTO advertisements
            (advertiser_user_id, title, title_ar, description, ad_type, target_audience,
             target_wilaya, starts_at, ends_at, status)
        VALUES (?,?,?,?,?,?,?,?,?,'pending')
    ");
    $stmt->execute([
        $user['id'],
        $data['title'],
        $data['title_ar'] ?? null,
        $data['description'] ?? null,
        $data['ad_type'] ?? 'featured_product',
        $data['target_audience'] ?? 'all',
        $data['target_wilaya'] ?? null,
        $data['starts_at'] ?? date('Y-m-d H:i:s'),
        $data['ends_at'] ?? date('Y-m-d H:i:s', strtotime('+30 days')),
    ]);
    sendSuccess(['id' => (int)$db->lastInsertId(), 'message' => 'تم إرسال الحملة للمراجعة'], 201);
}

sendError('طريقة غير مدعومة', 405);