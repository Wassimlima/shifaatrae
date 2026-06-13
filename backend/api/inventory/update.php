<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    sendError('Method not allowed', 405);
}

$user = requireAuth(['pharmacist', 'medical_services', 'admin']);
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    sendError('id required');
}

$body = getBody();
$db = getDB();
assertInventoryAccess($db, $id, $user);

$sets = [];
$params = [];
$types = '';

if (isset($body['productName'])) {
    $sets[] = 'product_name=?';
    $params[] = $body['productName'];
    $types .= 's';
}

if (isset($body['quantity'])) {
    $qty = (int) $body['quantity'];
    $status = $qty > 10 ? 'available' : ($qty > 0 ? 'limited' : 'unavailable');
    $sets[] = 'quantity=?';
    $params[] = $qty;
    $types .= 'i';
    $sets[] = 'status=?';
    $params[] = $status;
    $types .= 's';
}

if (isset($body['price'])) {
    $sets[] = 'price=?';
    $params[] = (float) $body['price'];
    $types .= 'd';
}

if (isset($body['status'])) {
    if (!in_array($body['status'], ['available', 'limited', 'unavailable'], true)) {
        sendError('Invalid status', 400);
    }
    $sets[] = 'status=?';
    $params[] = $body['status'];
    $types .= 's';
}

if (isset($body['category'])) {
    $sets[] = 'category=?';
    $params[] = $body['category'];
    $types .= 's';
}

if ($sets === []) {
    sendError('No fields to update');
}

$params[] = $id;
$types .= 'i';
$stmt = $db->prepare('UPDATE inventory SET ' . implode(',', $sets) . ' WHERE id=?');
$stmt->bind_param($types, ...$params);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    sendError('Item not found', 404);
}

$stmt = $db->prepare('SELECT * FROM inventory WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$db->close();
sendJSON($item);