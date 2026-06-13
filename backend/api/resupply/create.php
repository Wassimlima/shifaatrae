<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$user = requireAuth(['med_rep', 'admin']);
$body = getBody();

if (empty($body['pharmacyId']) || empty($body['productName'])) {
    sendError('Missing required fields');
}

$db = getDB();
$repId = resolveRepId($db, $user, isset($body['repId']) ? (int) $body['repId'] : null);
$pharmacyId = (int) $body['pharmacyId'];

$active = $db->prepare("SELECT id FROM partnership_requests WHERE rep_id=? AND pharmacy_id=? AND status='accepted' LIMIT 1");
$active->bind_param('ii', $repId, $pharmacyId);
$active->execute();

if (!$active->get_result()->fetch_assoc()) {
    http_response_code(403);
    sendJSON(['error' => 'No active partnership']);
}

$qty = (int) ($body['requestedQuantity'] ?? 1);
$msg = (string) ($body['message'] ?? '');

$stmt = $db->prepare('INSERT INTO resupply_requests (rep_id, pharmacy_id, product_name, requested_quantity, message, status) VALUES (?,?,?,?,?,?)');
$status = 'pending';
$stmt->bind_param('iisiss', $repId, $pharmacyId, $body['productName'], $qty, $msg, $status);
$stmt->execute();
$id = $db->insert_id;

$stmt = $db->prepare('SELECT * FROM resupply_requests WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$db->close();
sendJSON($req, 201);