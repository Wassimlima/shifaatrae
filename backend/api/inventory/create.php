<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$user = requireAuth(['pharmacist', 'medical_services', 'admin']);
$body = getBody();

if (empty($body['productName']) || !isset($body['quantity']) || empty($body['category'])) {
    sendError('Missing required fields');
}

$db = getDB();
$pharmacyId = resolvePharmacyId(
    $db,
    $user,
    isset($body['pharmacyId']) ? (int) $body['pharmacyId'] : null
);

$qty = (int) $body['quantity'];
$status = $qty > 10 ? 'available' : ($qty > 0 ? 'limited' : 'unavailable');
$price = $body['price'] ?? null;

$stmt = $db->prepare('INSERT INTO inventory (pharmacy_id, product_name, quantity, status, price, category) VALUES (?,?,?,?,?,?)');
$stmt->bind_param('isisds', $pharmacyId, $body['productName'], $qty, $status, $price, $body['category']);
$stmt->execute();
$id = $db->insert_id;

$stmt = $db->prepare('SELECT * FROM inventory WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$db->close();
sendJSON($item, 201);