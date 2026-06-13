<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$body  = getBody();
$email = trim($body['email'] ?? '');
$code  = trim($body['code']  ?? '');

if (!$email || !$code) sendError('البريد الإلكتروني والرمز مطلوبان');

$db = getDbConnection();

$stmt = $db->prepare('SELECT id, code, expires_at, used FROM email_verifications WHERE email = ? AND used = 0 ORDER BY id DESC LIMIT 1');
$stmt->execute([$email]);
$row = $stmt->fetch();

if (!$row) sendError('لم يتم العثور على رمز تحقق لهذا البريد', 400);
if ($row['used']) sendError('تم استخدام هذا الرمز مسبقاً', 400);
if (new DateTime() > new DateTime($row['expires_at'])) sendError('انتهت صلاحية الرمز، يرجى طلب رمز جديد', 400);
if ($row['code'] !== $code) sendError('الرمز غير صحيح', 400);

$db->prepare('UPDATE email_verifications SET used = 1 WHERE id = ?')->execute([$row['id']]);
$db->prepare('UPDATE users SET is_verified = 1, is_active = 1 WHERE email = ?')->execute([$email]);

sendSuccess(['message' => 'تم التحقق من بريدك الإلكتروني. يمكنك الآن تسجيل الدخول.']);
