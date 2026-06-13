<?php
/**
 * GET|POST /api/labs/my-analyses.php
 * Lab user: full CRUD for lab_analyses.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$user = requireAuth(['lab', 'admin']);
$db   = getDbConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $labRow = $db->prepare("SELECT id FROM labs WHERE user_id = ? LIMIT 1");
    $labRow->execute([$user['id']]);
    $lab = $labRow->fetch();
    if (!$lab) {
        $labRow2 = $db->query("SELECT id FROM labs LIMIT 1")->fetch();
        $labId = $labRow2 ? (int)$labRow2['id'] : 1;
    } else {
        $labId = (int)$lab['id'];
    }

    $stmt = $db->prepare("SELECT * FROM lab_analyses WHERE lab_id = ? ORDER BY created_at DESC");
    $stmt->execute([$labId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendSuccess(['data' => $items]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $data['action'] ?? 'create';

    $labRow = $db->prepare("SELECT id FROM labs WHERE user_id = ? LIMIT 1");
    $labRow->execute([$user['id']]);
    $lab = $labRow->fetch();
    if (!$lab) {
        $labRow2 = $db->query("SELECT id FROM labs LIMIT 1")->fetch();
        $labId = $labRow2 ? (int)$labRow2['id'] : 1;
    } else {
        $labId = (int)$lab['id'];
    }

    if ($action === 'create') {
        $stmt = $db->prepare("INSERT INTO lab_analyses (lab_id, name, name_ar, category, price, preparation_time, description, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([
            $labId,
            $data['name'] ?? '',
            $data['name_ar'] ?? null,
            $data['category'] ?? null,
            (float)($data['price'] ?? 0),
            $data['preparation_time'] ?? null,
            $data['description'] ?? null,
        ]);
        sendSuccess(['id' => $db->lastInsertId()], 201);
    }

    if ($action === 'update') {
        $id = (int)($data['id'] ?? 0);
        $stmt = $db->prepare("UPDATE lab_analyses SET name=?, name_ar=?, category=?, price=?, preparation_time=?, description=? WHERE id=? AND lab_id=?");
        $stmt->execute([
            $data['name'] ?? '',
            $data['name_ar'] ?? null,
            $data['category'] ?? null,
            (float)($data['price'] ?? 0),
            $data['preparation_time'] ?? null,
            $data['description'] ?? null,
            $id,
            $labId,
        ]);
        sendSuccess([], 200);
    }

    if ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        $db->prepare("DELETE FROM lab_analyses WHERE id=? AND lab_id=?")->execute([$id, $labId]);
        sendSuccess([], 200);
    }

    sendError('إجراء غير معروف', 400);
}

sendError('طريقة غير مدعومة', 405);
