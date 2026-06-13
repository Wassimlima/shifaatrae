<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$db = getDB();
$pharmacies = $db->query("SELECT COUNT(*) as c FROM pharmacies")->fetch_assoc()['c'];
$wilayas = $db->query("SELECT COUNT(DISTINCT wilaya) as c FROM pharmacies")->fetch_assoc()['c'];
$medicines = $db->query("SELECT COUNT(*) as c FROM inventory")->fetch_assoc()['c'];
$db->close();
sendJSON(['totalPharmacies' => intval($pharmacies), 'totalWilayas' => intval($wilayas), 'totalMedicines' => intval($medicines), 'dailySearches' => 1200]);
