<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$user = requireAuth(['med_rep', 'admin']);

$db = getDB();
$repId = resolveRepId($db, $user, isset($_GET['repId']) ? (int) $_GET['repId'] : null);
$pharmacyId = (int) ($_GET['pharmacyId'] ?? 0);

if (!$pharmacyId) {
    sendError('pharmacyId required');
}

$stmt = $db->prepare("SELECT id FROM partnership_requests WHERE rep_id=? AND pharmacy_id=? AND status='accepted' LIMIT 1");
$stmt->bind_param('ii', $repId, $pharmacyId);
$stmt->execute();

if (!$stmt->get_result()->fetch_assoc()) {
    http_response_code(403);
    sendJSON(['error' => 'No active partnership']);
}

$prods = $db->prepare('SELECT name FROM rep_products WHERE rep_id = ?');
$prods->bind_param('i', $repId);
$prods->execute();
$prodRows = $prods->get_result();
$names = [];

while ($r = $prodRows->fetch_assoc()) {
    $names[] = "'" . $db->real_escape_string($r['name']) . "'";
}

if ($names === []) {
    sendJSON(['items' => [], 'total' => 0, 'pharmacyId' => $pharmacyId]);
}

$inClause = implode(',', $names);
$result = $db->query("SELECT * FROM inventory WHERE pharmacy_id=$pharmacyId AND product_name IN ($inClause)");
$items = [];

while ($r = $result->fetch_assoc()) {
    $items[] = $r;
}

$db->close();
sendJSON(['items' => $items, 'total' => count($items), 'pharmacyId' => $pharmacyId]);