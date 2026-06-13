<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$user = requireAuth(['med_rep', 'admin']);
$db   = getDbConnection();

$rep = $db->prepare("SELECT id FROM med_reps WHERE user_id = ? LIMIT 1");
$rep->execute([$user['id']]);
$repRow = $rep->fetch();
if (!$repRow) sendError('ملف مزود الأدوية غير موجود', 404);
$supplierId = (int)$repRow['id'];

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->prepare("SELECT * FROM equipment_inventory WHERE supplier_id = ? ORDER BY created_at DESC");
    $stmt->execute([$supplierId]);
    sendSuccess(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'label' => 'مخزون المعدات']);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $required = ['equipment_name', 'category', 'quantity'];
    foreach ($required as $f) {
        if (!isset($body[$f]) || $body[$f] === '') sendError("الحقل $f مطلوب", 422);
    }

    $stmt = $db->prepare("
        INSERT INTO equipment_inventory
            (supplier_id, equipment_name, equipment_name_ar, category, brand, description, quantity, price, availability)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $supplierId,
        $body['equipment_name'],
        $body['equipment_name_ar'] ?? null,
        $body['category'],
        $body['brand'] ?? null,
        $body['description'] ?? null,
        (int)$body['quantity'],
        isset($body['price']) && $body['price'] !== '' ? (float)$body['price'] : null,
        $body['availability'] ?? 'available',
    ]);
    sendSuccess(['id' => (int)$db->lastInsertId(), 'message' => 'تمت إضافة المعدة بنجاح'], 201);
}

if ($method === 'PUT') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($body['id'] ?? $_GET['id'] ?? 0);
    if (!$id) sendError('معرّف المعدة مطلوب', 400);

    $check = $db->prepare("SELECT id FROM equipment_inventory WHERE id = ? AND supplier_id = ?");
    $check->execute([$id, $supplierId]);
    if (!$check->fetch()) sendError('غير مصرح', 403);

    $stmt = $db->prepare("
        UPDATE equipment_inventory SET
            equipment_name    = COALESCE(?, equipment_name),
            equipment_name_ar = COALESCE(?, equipment_name_ar),
            category          = COALESCE(?, category),
            brand             = COALESCE(?, brand),
            description       = COALESCE(?, description),
            quantity          = COALESCE(?, quantity),
            price             = COALESCE(?, price),
            availability      = COALESCE(?, availability)
        WHERE id = ? AND supplier_id = ?
    ");
    $stmt->execute([
        $body['equipment_name'] ?? null,
        $body['equipment_name_ar'] ?? null,
        $body['category'] ?? null,
        $body['brand'] ?? null,
        $body['description'] ?? null,
        isset($body['quantity']) && $body['quantity'] !== '' ? (int)$body['quantity'] : null,
        isset($body['price']) && $body['price'] !== '' ? (float)$body['price'] : null,
        $body['availability'] ?? null,
        $id,
        $supplierId,
    ]);
    sendSuccess(['message' => 'تم تحديث المعدة بنجاح']);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) sendError('معرّف المعدة مطلوب', 400);
    $stmt = $db->prepare("DELETE FROM equipment_inventory WHERE id = ? AND supplier_id = ?");
    $stmt->execute([$id, $supplierId]);
    sendSuccess(['message' => 'تم حذف المعدة بنجاح']);
}

sendError('طريقة الطلب غير مدعومة', 405);
