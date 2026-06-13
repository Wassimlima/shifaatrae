<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$db = getDB();
$q = isset($_GET['q']) ? '%' . $db->real_escape_string($_GET['q']) . '%' : '%';
$stmt = $db->prepare("SELECT DISTINCT name FROM medicines WHERE name LIKE ? OR name_ar LIKE ? LIMIT 8");
$stmt->bind_param('ss', $q, $q);
$stmt->execute();
$result = $stmt->get_result();
$suggestions = [];
while ($row = $result->fetch_assoc()) $suggestions[] = $row['name'];
$db->close();
sendJSON($suggestions);
