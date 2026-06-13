<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) sendError('معرّف المزود مطلوب', 400);

$db = getDbConnection();
$stmt = $db->prepare("SELECT mr.*, u.email FROM med_reps mr LEFT JOIN users u ON mr.user_id = u.id WHERE mr.id = ?");
$stmt->execute([$id]);
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$supplier) sendError('مزود الأدوية غير موجود', 404);

$eq = $db->prepare("SELECT * FROM equipment_inventory WHERE supplier_id = ? ORDER BY availability ASC, equipment_name ASC");
$eq->execute([$id]);
$supplier['equipment'] = $eq->fetchAll(PDO::FETCH_ASSOC);

sendSuccess(['data' => $supplier, 'label' => 'مزود أدوية']);
