<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) sendError('معرّف الصيدلية مطلوب', 400);

$db = getDbConnection();
$stmt = $db->prepare("SELECT * FROM pharmacies WHERE id = ?");
$stmt->execute([$id]);
$pharmacy = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pharmacy) sendError('الصيدلية غير موجودة', 404);

$meds = $db->prepare("
    SELECT id, name, name_ar, brand, dosage, form_type, type, quantity, availability, price, updated_at, created_at
    FROM medicines
    WHERE pharmacy_id = ?
    ORDER BY name
");
$meds->execute([$id]);
$pharmacy['medicines'] = $meds->fetchAll(PDO::FETCH_ASSOC);

sendSuccess(['data' => $pharmacy]);
