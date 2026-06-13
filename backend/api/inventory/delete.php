<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    sendError('Method not allowed', 405);
}

$user = requireAuth(['pharmacist', 'medical_services', 'admin']);
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    sendError('id required');
}

$db = getDB();
assertInventoryAccess($db, $id, $user);

$stmt = $db->prepare('DELETE FROM inventory WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    sendError('Item not found', 404);
}

$db->close();
sendJSON(['success' => true]);