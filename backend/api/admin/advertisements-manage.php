<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

requireAdmin();
$db = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $status = $_GET['status'] ?? '';
    $where = ['1=1'];
    $params = [];
    if ($status) {
        $where[] = 'a.status = ?';
        $params[] = $status;
    }
    $sql = "
        SELECT a.*, u.full_name AS advertiser_name, u.email AS advertiser_email
        FROM advertisements a
        LEFT JOIN users u ON u.id = a.advertiser_user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.created_at DESC
        LIMIT 100
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendSuccess($stmt->fetchAll());
}

if ($method === 'POST') {
    $body = getBody();
    $action = $body['action'] ?? '';
    $id = (int)($body['id'] ?? 0);
    if (!$id) sendError('معرف الإعلان مطلوب', 400);

    $admin = requireAdmin();
    if ($action === 'approve') {
        $stmt = $db->prepare("
            UPDATE advertisements SET status='active', reviewed_by=?, reviewed_at=NOW(), admin_notes=?
            WHERE id=?
        ");
        $stmt->execute([$admin['id'], $body['notes'] ?? '', $id]);
        sendSuccess(['message' => 'تمت الموافقة على الإعلان']);
    }
    if ($action === 'reject') {
        $stmt = $db->prepare("
            UPDATE advertisements SET status='rejected', reviewed_by=?, reviewed_at=NOW(), admin_notes=?
            WHERE id=?
        ");
        $stmt->execute([$admin['id'], $body['notes'] ?? '', $id]);
        sendSuccess(['message' => 'تم رفض الإعلان']);
    }
    if ($action === 'pause') {
        $stmt = $db->prepare("UPDATE advertisements SET status='paused' WHERE id=?");
        $stmt->execute([$id]);
        sendSuccess(['message' => 'تم إيقاف الإعلان']);
    }
}

sendError('طريقة غير مدعومة', 405);