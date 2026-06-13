<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';

$user = currentUser();

if ($user === null) {
    sendSuccess(['logged_in' => false]);
}

$db = getDB();
$context = getUserContext($db, $user);
$db->close();

sendSuccess([
    'logged_in'   => true,
    'id'          => $user['id'],
    'name'        => $user['name'],
    'email'       => $user['email'],
    'role'        => $user['role'],
    'pharmacy_id' => $context['pharmacy_id'],
    'rep_id'      => $context['rep_id'],
]);