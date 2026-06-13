<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$db = getDB();
$result = $db->query("
    SELECT i.product_name AS name, i.product_name_ar AS name_ar,
           i.category AS type, i.quantity,
           p.name AS pharmacy_name, p.wilaya
    FROM inventory i
    LEFT JOIN pharmacies p ON i.pharmacy_id = p.id
    WHERE i.is_available = 1 AND i.quantity > 5
    ORDER BY i.quantity DESC
    LIMIT 6
");
$medicines = [];
while ($row = $result->fetch_assoc()) $medicines[] = $row;
$db->close();
sendJSON($medicines);
