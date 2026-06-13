<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

requireAdmin();

$db = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $role   = $_GET['role']   ?? '';
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';

    $where = ['1=1'];
    $params = [];
    if ($role)   { $where[] = 'role = ?';               $params[] = $role; }
    if ($search) { $where[] = '(full_name LIKE ? OR email LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($status === 'active')   { $where[] = 'is_active = 1'; }
    if ($status === 'inactive') { $where[] = 'is_active = 0'; }

    $sql  = "SELECT id, full_name, email, phone, role, is_verified, is_active, last_login, created_at FROM users WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendSuccess($stmt->fetchAll());
}

if ($method === 'POST') {
    $body = getBody();
    $action = $body['action'] ?? '';

    if ($action === 'toggle_active') {
        $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$body['user_id']]);
        sendSuccess(['message' => 'تم تحديث حالة المستخدم']);
    }
    if ($action === 'toggle_verified') {
        $stmt = $db->prepare("UPDATE users SET is_verified = NOT is_verified WHERE id = ?");
        $stmt->execute([$body['user_id']]);
        sendSuccess(['message' => 'تم تحديث حالة التحقق']);
    }
    if ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$body['user_id']]);
        sendSuccess(['message' => 'تم حذف المستخدم']);
    }
    if ($action === 'create') {
        if (empty($body['password']) || strlen($body['password']) < 8) {
            sendError('كلمة مرور مطلوبة (8 أحرف على الأقل)', 400);
        }
        $hash = password_hash($body['password'], PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (full_name, email, phone, password_hash, role, is_verified, is_active) VALUES (?,?,?,?,?,1,1)");
        $stmt->execute([$body['full_name'], $body['email'], $body['phone'] ?? '', $hash, $body['role'] ?? 'patient']);
        sendSuccess(['id' => $db->lastInsertId(), 'message' => 'تم إنشاء المستخدم']);
    }
}

sendError('طريقة غير مدعومة', 405);
