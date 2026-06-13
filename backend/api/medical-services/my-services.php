<?php
/**
 * GET|POST /api/medical-services/my-services.php
 * Medical Equipment user: full CRUD for their items.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$user = requireAuth(['medical_services', 'admin']);
$db   = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("SELECT * FROM medical_services WHERE user_id = ? OR provider_user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id'], $user['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    sendSuccess(['data' => $items, 'label' => 'معدات طبية']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? 'create';

    if ($action === 'create') {
        $stmt = $db->prepare("INSERT INTO medical_services (user_id, provider_user_id, name, name_ar, description, category, price, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([
            $user['id'],
            $user['id'],
            $data['name'] ?? '',
            $data['name_ar'] ?? null,
            $data['description'] ?? null,
            $data['category'] ?? null,
            (float)($data['price'] ?? 0),
        ]);
        sendSuccess(['id' => $db->lastInsertId(), 'message' => 'تمت إضافة المعدة بنجاح'], 201);
    }

    if ($action === 'update') {
        $id = (int)($data['id'] ?? 0);
        $stmt = $db->prepare("UPDATE medical_services SET name=?, name_ar=?, description=?, category=?, price=? WHERE id=? AND (user_id=? OR provider_user_id=?)");
        $stmt->execute([
            $data['name'] ?? '',
            $data['name_ar'] ?? null,
            $data['description'] ?? null,
            $data['category'] ?? null,
            (float)($data['price'] ?? 0),
            $id,
            $user['id'],
            $user['id'],
        ]);
        sendSuccess(['message' => 'تم تحديث المعدة بنجاح'], 200);
    }

    if ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        $db->prepare("DELETE FROM medical_services WHERE id=? AND (user_id=? OR provider_user_id=?)")->execute([$id, $user['id'], $user['id']]);
        sendSuccess(['message' => 'تم حذف المعدة بنجاح'], 200);
    }

    sendError('إجراء غير معروف', 400);
}

sendError('طريقة غير مدعومة', 405);
