<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$user = requireAuth();

if (!isset($_FILES['prescription']) || $_FILES['prescription']['error'] !== UPLOAD_ERR_OK) {
    sendError('No file uploaded or upload error');
}

$file = $_FILES['prescription'];
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($file['type'], $allowed, true)) {
    sendError('Invalid file type. Use JPG, PNG, or WebP.');
}
if ($file['size'] > 5 * 1024 * 1024) {
    sendError('File too large. Max 5MB.');
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
    sendError('Invalid file extension');
}

$filename = uniqid('rx_', true) . '.' . $ext;
$uploadDir = __DIR__ . '/../../uploads/';
$uploadPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    sendError('Upload failed');
}

$imageUrl = '/shifaa_dizad/backend/uploads/' . $filename;
$patientName = $_POST['patientName'] ?? 'مجهول';
$notes = $_POST['notes'] ?? null;

$db = getDB();
$stmt = $db->prepare("INSERT INTO prescriptions (user_id, image_url, patient_name, notes, status) VALUES (?, ?, ?, ?, 'pending')");
$stmt->bind_param('isss', $user['id'], $imageUrl, $patientName, $notes);
$stmt->execute();
$id = $db->insert_id;

$stmt = $db->prepare('SELECT * FROM prescriptions WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$rx = $stmt->get_result()->fetch_assoc();
$db->close();
sendJSON($rx, 201);