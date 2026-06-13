<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$db = getDB();
$db->set_charset('utf8mb4');

$total         = (int)$db->query("SELECT COUNT(*) as c FROM donations")->fetch_assoc()['c'];
$available     = (int)$db->query("SELECT COUNT(*) as c FROM donations WHERE status = 'open'")->fetch_assoc()['c'];
$wilayas       = (int)$db->query("SELECT COUNT(DISTINCT wilaya) as c FROM donations WHERE wilaya IS NOT NULL AND wilaya != ''")->fetch_assoc()['c'];
$beneficiaries = (int)$db->query("SELECT COUNT(*) as c FROM donations WHERE status = 'fulfilled'")->fetch_assoc()['c'];

$db->close();
sendJSON([
    'total'         => $total,
    'available'     => $available,
    'wilayas'       => $wilayas,
    'beneficiaries' => $beneficiaries,
]);
