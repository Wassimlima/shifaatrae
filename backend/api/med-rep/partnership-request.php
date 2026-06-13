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

if (empty($body['pharmacyId'])) {
    sendError('pharmacyId required');
}

$db = getDB();
$repId = resolveRepId($db, $user, isset($body['repId']) ? (int) $body['repId'] : null);
$pharmacyId = (int) $body['pharmacyId'];

$existing = $db->prepare("SELECT id FROM partnership_requests WHERE rep_id=? AND pharmacy_id=? AND status IN ('pending','accepted')");
$existing->bind_param('ii', $repId, $pharmacyId);
$existing->execute();

if ($existing->get_result()->fetch_assoc()) {
    sendError('Partnership request already exists');
}

$stmt = $db->prepare("INSERT INTO partnership_requests (rep_id, pharmacy_id, status, message) VALUES (?,?,'pending',?)");
$msg = $body['message'] ?? null;
$stmt->bind_param('iis', $repId, $pharmacyId, $msg);
$stmt->execute();
$id = $db->insert_id;

$stmt = $db->prepare('SELECT * FROM partnership_requests WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$db->close();
sendJSON($req, 201);