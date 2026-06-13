<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) sendError('معرّف التبرع مطلوب', 400);

$db = getDB();
$result = $db->query("SELECT * FROM donations WHERE id = $id LIMIT 1");
$donation = $result ? $result->fetch_assoc() : null;
if (!$donation) sendError('التبرع غير موجود', 404);
$db->close();
sendJSON(['data' => $donation]);
