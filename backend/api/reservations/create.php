<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$body = getBody();
$required = ['medicineId', 'pharmacyId', 'patientName', 'patientPhone'];

foreach ($required as $f) {
    if (empty($body[$f])) {
        sendError("$f is required");
    }
}

$medicineId  = (int) $body['medicineId'];
$pharmacyId  = (int) $body['pharmacyId'];
$patientName = trim((string) $body['patientName']);
$patientPhone = trim((string) $body['patientPhone']);
$qty         = max(1, (int) ($body['quantity'] ?? 1));
$notes       = isset($body['notes']) ? trim((string) $body['notes']) : '';
if ($notes === '') {
    $notes = null;
}

$db = getDB();

$check = $db->prepare('SELECT id FROM medicines WHERE id = ? AND pharmacy_id = ? LIMIT 1');
$check->bind_param('ii', $medicineId, $pharmacyId);
$check->execute();

if (!$check->get_result()->fetch_assoc()) {
    sendError('Medicine not found for this pharmacy', 404);
}

$stmt = $db->prepare(
    'INSERT INTO reservations (medicine_id, pharmacy_id, patient_name, patient_phone, quantity, notes, status)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$status = 'pending';
$stmt->bind_param('iississ', $medicineId, $pharmacyId, $patientName, $patientPhone, $qty, $notes, $status);

if (!$stmt->execute()) {
    sendError('Failed to create reservation: ' . $stmt->error, 500);
}

$id = (int) $db->insert_id;

$sel = $db->prepare(
    'SELECT r.*, m.name AS medicine_name, p.name AS pharmacy_name
     FROM reservations r
     LEFT JOIN medicines m ON m.id = r.medicine_id
     LEFT JOIN pharmacies p ON p.id = r.pharmacy_id
     WHERE r.id = ?'
);
$sel->bind_param('i', $id);
$sel->execute();
$res = $sel->get_result()->fetch_assoc();

$db->close();
sendJSON($res, 201);