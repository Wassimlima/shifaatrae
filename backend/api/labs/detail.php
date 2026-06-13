<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) sendError('معرّف المخبر مطلوب', 400);

$db = getDbConnection();
$stmt = $db->prepare("SELECT * FROM labs WHERE id = ?");
$stmt->execute([$id]);
$lab = $stmt->fetch();
if (!$lab) sendError('المخبر غير موجود', 404);

$analyses = $db->prepare("SELECT * FROM lab_analyses WHERE lab_id = ? ORDER BY category, name");
$analyses->execute([$id]);
$lab['analyses'] = $analyses->fetchAll();

sendSuccess($lab);
