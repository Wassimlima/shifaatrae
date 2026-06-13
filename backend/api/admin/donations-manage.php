<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

requireAdmin();

$db     = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query("SELECT * FROM donations ORDER BY created_at DESC");
    sendSuccess(['donations' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $body   = getBody();
    $action = $body['action'] ?? '';
    $id     = (int)($body['id'] ?? 0);

    if (!$id) sendError('معرّف التبرع مطلوب', 400);

    if ($action === 'approve') {
        $db->prepare("UPDATE donations SET status='approved', is_available=1 WHERE id=?")->execute([$id]);
        sendSuccess(['message' => 'تم قبول التبرع']);
    }
    if ($action === 'reject') {
        $db->prepare("UPDATE donations SET status='rejected', is_available=0 WHERE id=?")->execute([$id]);
        sendSuccess(['message' => 'تم رفض التبرع']);
    }
    if ($action === 'toggle') {
        $db->prepare("UPDATE donations SET is_available = 1 - COALESCE(is_available,0) WHERE id=?")->execute([$id]);
        sendSuccess(['message' => 'تم التحديث']);
    }
    if ($action === 'delete') {
        $db->prepare("DELETE FROM donations WHERE id=?")->execute([$id]);
        sendSuccess(['message' => 'تم الحذف']);
    }
    sendError('إجراء غير معروف', 400);
}

sendError('طريقة غير مدعومة', 405);
