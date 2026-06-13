<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

requireAdmin();
$db = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search = $_GET['search'] ?? '';
    $where = ['1=1'];
    $params = [];
    if ($search) {
        $where[] = '(mr.name LIKE ? OR u.email LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql = "
        SELECT mr.*, u.email, u.is_verified, u.is_active, u.full_name AS user_name
        FROM med_reps mr
        JOIN users u ON u.id = mr.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY mr.created_at DESC
        LIMIT 100
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendSuccess(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'label' => 'مزودو الأدوية']);
}

if ($method === 'POST') {
    $body = getBody();
    $action = $body['action'] ?? '';
    $id = (int)($body['id'] ?? 0);
    if ($action === 'toggle_active' && $id) {
        $row = $db->prepare("SELECT user_id FROM med_reps WHERE id = ?");
        $row->execute([$id]);
        $uid = $row->fetchColumn();
        if ($uid) {
            $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$uid]);
            sendSuccess(['message' => 'تم تحديث حالة مزود الأدوية']);
        }
        sendError('لم يتم العثور على مزود الأدوية', 404);
    }
}

sendError('طريقة غير مدعومة', 405);
