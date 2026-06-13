<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$user = requireAuth(['pharmacist', 'medical_services', 'med_rep', 'admin']);
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    sendError('id required');
}

$body = getBody();
$status = $body['status'] ?? '';

if (!in_array($status, ['accepted', 'rejected', 'revoked'], true)) {
    sendError('Invalid status');
}

$db = getDB();
assertPartnershipRequestAccess($db, $id, $user);

$stmt = $db->prepare('UPDATE partnership_requests SET status = ? WHERE id = ?');
$stmt->bind_param('si', $status, $id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    sendError('Request not found', 404);
}

if ($status === 'revoked') {
    $prStmt = $db->prepare('SELECT rep_id, pharmacy_id FROM partnership_requests WHERE id = ?');
    $prStmt->bind_param('i', $id);
    $prStmt->execute();
    $pr = $prStmt->get_result()->fetch_assoc();

    if ($pr) {
        $rej = $db->prepare("UPDATE resupply_requests SET status='rejected' WHERE rep_id=? AND pharmacy_id=? AND status='pending'");
        $rej->bind_param('ii', $pr['rep_id'], $pr['pharmacy_id']);
        $rej->execute();
    }
}

$stmt = $db->prepare('SELECT * FROM partnership_requests WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();
$db->close();
sendJSON($req);