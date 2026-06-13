<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/redirects.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$body = getBody();
$email    = trim($body['email'] ?? '');
$password = $body['password'] ?? '';

if (!$email || !$password) {
    sendError('البريد الإلكتروني وكلمة المرور مطلوبان');
}

$db   = getDbConnection();
$stmt = $db->prepare('SELECT id, full_name, email, password_hash, role, is_active FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    sendError('البريد الإلكتروني أو كلمة المرور غير صحيحة', 401);
}

if (!$user['is_active']) {
    sendError('الحساب موقوف، تواصل مع الدعم', 403);
}

$db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

loginUser($user);

$dbMysqli = getDB();
$context = getUserContext($dbMysqli, currentUser());
$dbMysqli->close();

sendSuccess([
    'id'          => (int) $user['id'],
    'name'        => $user['full_name'],
    'email'       => $user['email'],
    'role'        => $user['role'],
    'pharmacy_id' => $context['pharmacy_id'],
    'rep_id'      => $context['rep_id'],
    'redirect'    => getRoleRedirect($user['role']),
]);