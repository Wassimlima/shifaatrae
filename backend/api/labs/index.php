<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$db     = getDB();
$q      = isset($_GET['q'])      ? '%' . $db->real_escape_string($_GET['q']) . '%' : '%';
$wilaya = isset($_GET['wilaya']) ? $db->real_escape_string($_GET['wilaya'])          : '';

$where = "WHERE (l.name LIKE '$q' OR l.name_ar LIKE '$q' OR l.city LIKE '$q')";
if ($wilaya) $where .= " AND l.wilaya = '$wilaya'";

// Prioritise El Tarf and Annaba
$orderBy = "ORDER BY FIELD(l.wilaya,'الطارف','عنابة') DESC, l.rating DESC, l.name ASC";

$sql = "SELECT l.*,
               (SELECT COUNT(*) FROM lab_analyses la WHERE la.lab_id = l.id) AS analyses_count
        FROM labs l
        $where
        $orderBy
        LIMIT 100";

$result = $db->query($sql);
$labs   = [];
while ($row = $result->fetch_assoc()) $labs[] = $row;

$total = $db->query("SELECT COUNT(*) as c FROM labs l $where")->fetch_assoc()['c'];
$db->close();

sendJSON(['labs' => $labs, 'total' => intval($total)]);
