<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';

require_once __DIR__ . '/../../utils/auth.php';
requireAdmin();

$db = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $wilaya = $_GET['wilaya'] ?? '';
    $search = $_GET['search'] ?? '';
    $where = ['1=1'];
    $params = [];
    if ($wilaya) { $where[] = 'wilaya = ?'; $params[] = $wilaya; }
    if ($search)  { $where[] = 'name LIKE ?'; $params[] = "%$search%"; }

    $sql  = "SELECT * FROM pharmacies WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    sendSuccess($stmt->fetchAll());
}

if ($method === 'POST') {
    $body   = getBody();
    $action = $body['action'] ?? '';
    if ($action === 'toggle_verified') {
        $stmt = $db->prepare("UPDATE pharmacies SET is_verified = NOT is_verified WHERE id = ?");
        $stmt->execute([$body['id']]);
        sendSuccess(['message' => 'تم التحديث']);
    }
    if ($action === 'toggle_active') {
        $stmt = $db->prepare("UPDATE pharmacies SET is_open = NOT is_open WHERE id = ?");
        $stmt->execute([$body['id']]);
        sendSuccess(['message' => 'تم التحديث']);
    }
    if ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM pharmacies WHERE id = ?");
        $stmt->execute([$body['id']]);
        sendSuccess(['message' => 'تم الحذف']);
    }
}

sendError('طريقة غير مدعومة', 405);
