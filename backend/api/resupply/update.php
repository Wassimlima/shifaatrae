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

$uiStatuses = ['pending', 'confirmed', 'sent', 'rejected', 'approved', 'completed'];
if (!in_array($status, $uiStatuses, true)) {
    sendError('Invalid status');
}

/** Map UI labels to DB ENUM (pending, approved, rejected, completed) */
$statusMap = [
    'pending'   => 'pending',
    'confirmed' => 'approved',
    'approved'  => 'approved',
    'sent'      => 'completed',
    'completed' => 'completed',
    'rejected'  => 'rejected',
];
$dbStatus = $statusMap[$status];

$db = getDB();
assertResupplyRequestAccess($db, $id, $user);

$stmt = $db->prepare('UPDATE resupply_requests SET status = ? WHERE id = ?');
$stmt->bind_param('si', $dbStatus, $id);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    sendError('Request not found', 404);
}

$stmt = $db->prepare('SELECT * FROM resupply_requests WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$req = $stmt->get_result()->fetch_assoc();

/** Return UI-friendly status for dashboards */
$reverseMap = [
    'pending'   => 'pending',
    'approved'  => 'confirmed',
    'completed' => 'sent',
    'rejected'  => 'rejected',
];
$req['status'] = $reverseMap[$req['status']] ?? $req['status'];

$db->close();
sendJSON($req);