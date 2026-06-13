<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$body     = getBody();
$fullName = trim($body['full_name'] ?? '');
$email    = trim($body['email']     ?? '');
$phone    = trim($body['phone']     ?? '');
$password = $body['password']       ?? '';
$role     = $body['role']           ?? 'patient';

$allowedRoles = ['patient', 'pharmacist', 'med_rep', 'lab', 'medical_services'];
if (!in_array($role, $allowedRoles)) {
    sendError('دور غير مسموح به');
}
if (!$fullName) sendError('الاسم الكامل مطلوب');
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) sendError('البريد الإلكتروني غير صحيح');
if (strlen($password) < 6) sendError('كلمة المرور يجب أن تكون 6 أحرف على الأقل');

$db = getDbConnection();

// Ensure email_verifications table exists
$db->exec("CREATE TABLE IF NOT EXISTS email_verifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(191) NOT NULL,
    code       VARCHAR(10)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Check if email already exists
$stmt = $db->prepare('SELECT id, is_verified FROM users WHERE email = ?');
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing && $existing['is_verified']) {
    sendError('هذا البريد الإلكتروني مسجل بالفعل');
}

// Delete any unverified previous account with same email
if ($existing && !$existing['is_verified']) {
    $db->prepare('DELETE FROM users WHERE email = ? AND is_verified = 0')->execute([$email]);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare('INSERT INTO users (full_name, email, phone, password_hash, role, is_verified, is_active) VALUES (?, ?, ?, ?, ?, 0, 0)');
$stmt->execute([$fullName, $email, $phone ?: null, $passwordHash, $role]);
$userId = $db->lastInsertId();

// Generate 6-digit verification code
$code    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', time() + 900);

$db->prepare('INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, ?)')->execute([$email, $code, $expires]);

// Attempt to send email
$emailSent = false;
$subject   = 'شفاء ديزاد — رمز التحقق';
$body      = "مرحباً {$fullName}،\n\nرمز التحقق الخاص بك هو: {$code}\n\nصالح لمدة 15 دقيقة.\n\n— فريق شفاء ديزاد";
$headers   = "From: noreply@shifaa-dz.com\r\nContent-Type: text/plain; charset=UTF-8";

if (function_exists('mail')) {
    $emailSent = @mail($email, $subject, $body, $headers);
}

sendSuccess([
    'user_id'    => (int) $userId,
    'email'      => $email,
    'email_sent' => $emailSent,
    'message'    => 'تم إنشاء الحساب. أدخل رمز التحقق المرسل إلى بريدك الإلكتروني.',
    'dev_code'   => (getenv('APP_ENV') !== 'production') ? $code : null,
]);
