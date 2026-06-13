<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendError('Method not allowed', 405);

$user = currentUser();
$userId = $user ? (int) $user['id'] : null;

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $body = getBody();
} else {
    $body = $_POST;
}

$required = ['item_name','item_name_ar','wilaya','city','donor_name','condition','category'];
foreach ($required as $f) { if (empty($body[$f])) sendError("$f is required"); }

$imageUrl = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    if (in_array($file['type'], $allowed) && $file['size'] <= 5 * 1024 * 1024) {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('don_', true) . '.' . $ext;
        $uploadDir = __DIR__ . '/../../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $imageUrl = '/shifaa_dizad/backend/uploads/' . $filename;
        }
    }
}

$db = getDB();
$stmt = $db->prepare("INSERT INTO donations (user_id,item_name,item_name_ar,description,wilaya,city,donor_name,`condition`,category,image_url) VALUES (?,?,?,?,?,?,?,?,?,?)");
$desc = $body['description'] ?? null;
$stmt->bind_param('isssssssss', $userId, $body['item_name'], $body['item_name_ar'], $desc, $body['wilaya'], $body['city'], $body['donor_name'], $body['condition'], $body['category'], $imageUrl);

if (!$stmt->execute()) {
    sendError('Failed to save donation: ' . $stmt->error, 500);
}
$id = $db->insert_id;
$donation = $db->query("SELECT * FROM donations WHERE id=$id")->fetch_assoc();
$db->close();
sendJSON($donation, 201);
