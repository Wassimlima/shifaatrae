<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$body  = getBody();
$email = trim($body['email'] ?? '');
if (!$email) sendError('البريد الإلكتروني مطلوب');

$db = getDbConnection();

$stmt = $db->prepare('SELECT id, full_name, is_verified FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) sendError('البريد الإلكتروني غير مسجل');
if ($user['is_verified']) sendError('البريد الإلكتروني محقق بالفعل');

$db->exec("CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(191) NOT NULL,
    code VARCHAR(10) NOT NULL, expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', time() + 900);
$db->prepare('INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, ?)')->execute([$email, $code, $expires]);

$emailSent = false;
$subject   = 'شفاء ديزاد — رمز التحقق';
$body      = "رمز التحقق الخاص بك هو: {$code}\nصالح لمدة 15 دقيقة.";
$headers   = "From: noreply@shifaa-dz.com\r\nContent-Type: text/plain; charset=UTF-8";
if (function_exists('mail')) $emailSent = @mail($email, $subject, $body, $headers);

sendSuccess([
    'message'  => 'تم إرسال رمز تحقق جديد',
    'email_sent' => $emailSent,
    'dev_code' => (getenv('APP_ENV') !== 'production') ? $code : null,
]);
