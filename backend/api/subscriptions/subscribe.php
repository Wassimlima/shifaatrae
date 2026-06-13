<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../utils/auth.php';
require_once __DIR__ . '/../../utils/redirects.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

$PLANS = [
    // Legacy keys
    'pharmacy-free'          => ['role_type' => 'pharmacy',         'plan_name' => 'free',         'billing_cycle' => 'monthly', 'price' => 0],
    'pharmacy-pro'           => ['role_type' => 'pharmacy',         'plan_name' => 'professional', 'billing_cycle' => 'monthly', 'price' => 3900],
    'pharmacy-enterprise'    => ['role_type' => 'pharmacy',         'plan_name' => 'enterprise',   'billing_cycle' => 'monthly', 'price' => 7000],
    'medrep-free'            => ['role_type' => 'med_rep',          'plan_name' => 'free',         'billing_cycle' => 'monthly', 'price' => 0],
    'medrep-monthly'         => ['role_type' => 'med_rep',          'plan_name' => 'monthly',      'billing_cycle' => 'monthly', 'price' => 2500],
    'medrep-yearly'          => ['role_type' => 'med_rep',          'plan_name' => 'yearly',       'billing_cycle' => 'yearly',  'price' => 25000],
    'lab-free'               => ['role_type' => 'lab',              'plan_name' => 'free',         'billing_cycle' => 'monthly', 'price' => 0],
    'lab-premium'            => ['role_type' => 'lab',              'plan_name' => 'premium',      'billing_cycle' => 'monthly', 'price' => 4500],
    'lab-enterprise'         => ['role_type' => 'lab',              'plan_name' => 'enterprise',   'billing_cycle' => 'monthly', 'price' => 8500],
    'medservices-free'       => ['role_type' => 'medical_services', 'plan_name' => 'free',         'billing_cycle' => 'monthly', 'price' => 0],
    'medservices-pro'        => ['role_type' => 'medical_services', 'plan_name' => 'professional', 'billing_cycle' => 'monthly', 'price' => 4200],
    'medservices-enterprise' => ['role_type' => 'medical_services', 'plan_name' => 'enterprise',   'billing_cycle' => 'monthly', 'price' => 10500],
    // plans.php generated keys (tier = starter / pro)
    'pharmacy-starter'       => ['role_type' => 'pharmacy',         'plan_name' => 'starter',      'billing_cycle' => 'monthly', 'price' => 1999],
    'pharmacy-pro'           => ['role_type' => 'pharmacy',         'plan_name' => 'professional', 'billing_cycle' => 'monthly', 'price' => 3999],
    'lab-starter'            => ['role_type' => 'lab',              'plan_name' => 'starter',      'billing_cycle' => 'monthly', 'price' => 1999],
    'lab-pro'                => ['role_type' => 'lab',              'plan_name' => 'professional', 'billing_cycle' => 'monthly', 'price' => 3999],
    'med_rep-starter'        => ['role_type' => 'med_rep',          'plan_name' => 'starter',      'billing_cycle' => 'monthly', 'price' => 1999],
    'med_rep-pro'            => ['role_type' => 'med_rep',          'plan_name' => 'professional', 'billing_cycle' => 'monthly', 'price' => 3999],
    'medical_services-starter' => ['role_type' => 'medical_services', 'plan_name' => 'starter',   'billing_cycle' => 'monthly', 'price' => 1999],
    'medical_services-pro'   => ['role_type' => 'medical_services', 'plan_name' => 'professional', 'billing_cycle' => 'monthly', 'price' => 3999],
];

$body = getBody();
$planId = trim($body['plan_id'] ?? $body['plan'] ?? '');
$sessionUser = currentUser();

if (!isset($PLANS[$planId])) {
    sendError('باقة غير صالحة');
}

$plan = $PLANS[$planId];
$userRole = roleTypeToUserRole($plan['role_type']);

if ($userRole === 'patient') {
    sendError('باقة غير مدعومة');
}

$fullName = trim($body['full_name'] ?? $body['name'] ?? '');
$email    = trim($body['email'] ?? '');
$phone    = trim($body['phone'] ?? '');
$password = $body['password'] ?? '';
$orgName  = trim($body['organization_name'] ?? $body['business_name'] ?? $fullName);

$db = getDbConnection();

if ($sessionUser) {
    $stmt = $db->prepare('SELECT id, full_name, email, phone, role, is_active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $sessionUser['id']]);
    $existing = $stmt->fetch();

    if (!$existing) {
        sendError('الجلسة غير صالحة، أعد تسجيل الدخول', 401);
    }
    if ($existing['role'] === 'admin') {
        sendError('حساب المسؤول لا يمكن ربطه باشتراك مهني', 403);
    }
    if (!$existing['is_active']) {
        sendError('الحساب موقوف', 403);
    }

    $fullName = $fullName ?: $existing['full_name'];
    $email    = $existing['email'];
    $phone    = $phone ?: ($existing['phone'] ?? '');
    $orgName  = $orgName ?: $fullName;

    $upd = $db->prepare('UPDATE users SET role = ?, full_name = ?, phone = COALESCE(NULLIF(?, ""), phone), is_verified = 1 WHERE id = ?');
    $upd->execute([$userRole, $fullName, $phone, $existing['id']]);
    $userId = (int) $existing['id'];
    $userRow = [
        'id'        => $userId,
        'full_name' => $fullName,
        'email'     => $email,
        'role'      => $userRole,
    ];
} else {
    if (!$email || !$password || !$fullName) {
        sendError('الاسم والبريد وكلمة المرور مطلوبة');
    }

    if (strlen($password) < 6) {
        sendError('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
    }

    $stmt = $db->prepare('SELECT id, full_name, email, password_hash, role, is_active FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        if (!password_verify($password, $existing['password_hash'])) {
            sendError('البريد مستخدم بالفعل — سجّل الدخول أو استخدم كلمة المرور الصحيحة', 409);
        }
        if ($existing['role'] === 'admin') {
            sendError('حساب المسؤول لا يمكن ربطه باشتراك مهني', 403);
        }
        if (!$existing['is_active']) {
            sendError('الحساب موقوف', 403);
        }

        $upd = $db->prepare('UPDATE users SET role = ?, full_name = ?, phone = COALESCE(NULLIF(?, ""), phone), is_verified = 1 WHERE id = ?');
        $upd->execute([$userRole, $fullName, $phone, $existing['id']]);
        $userId = (int) $existing['id'];
        $userRow = [
            'id'         => $userId,
            'full_name'  => $fullName,
            'email'      => $email,
            'role'       => $userRole,
        ];
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $phoneVal = $phone !== '' ? $phone : null;
        $ins = $db->prepare('INSERT INTO users (full_name, email, phone, password_hash, role, is_verified, is_active) VALUES (?, ?, ?, ?, ?, 1, 1)');
        $ins->execute([$fullName, $email, $phoneVal, $hash, $userRole]);
        $userId = (int) $db->lastInsertId();
        $userRow = [
            'id'        => $userId,
            'full_name' => $fullName,
            'email'     => $email,
            'role'      => $userRole,
        ];
    }
}

ensureProfessionalEntity($db, $userId, $userRole, $orgName, $email, $phone, $plan['plan_name']);

$db->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE user_id = ? AND status = 'active'")->execute([$userId]);

$subIns = $db->prepare('
    INSERT INTO subscriptions (user_id, plan_name, role_type, billing_cycle, price, status)
    VALUES (?, ?, ?, ?, ?, ?)
');
$status = $plan['price'] > 0 ? 'trial' : 'active';
$subIns->execute([
    $userId,
    $plan['plan_name'],
    $plan['role_type'],
    $plan['billing_cycle'],
    $plan['price'],
    $status,
]);

loginUser($userRow);

$dbMysqli = getDB();
$context = getUserContext($dbMysqli, ['id' => $userId, 'role' => $userRole]);
$dbMysqli->close();

sendSuccess([
    'id'          => $userId,
    'name'        => $fullName,
    'email'       => $email,
    'role'        => $userRole,
    'plan_id'     => $planId,
    'pharmacy_id' => $context['pharmacy_id'],
    'rep_id'      => $context['rep_id'],
    'redirect'    => getRoleRedirect($userRole),
]);

function ensureProfessionalEntity(PDO $db, int $userId, string $role, string $orgName, string $email, string $phone, string $planName): void
{
    if ($role === 'pharmacist') {
        $chk = $db->prepare('SELECT id FROM pharmacies WHERE user_id = ? LIMIT 1');
        $chk->execute([$userId]);
        if ($chk->fetch()) return;

        $ins = $db->prepare('INSERT INTO pharmacies (user_id, name, wilaya, city, phone, address, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
        $ins->execute([
            $userId,
            $orgName ?: 'منشأة جديدة',
            'الجزائر',
            'الجزائر العاصمة',
            $phone ?: '0000000000',
            'الجزائر',
        ]);
        return;
    }

    if ($role === 'medical_services') {
        $chk = $db->prepare('SELECT id FROM medical_services WHERE user_id = ? OR provider_user_id = ? LIMIT 1');
        $chk->execute([$userId, $userId]);
        if ($chk->fetch()) return;

        $ins = $db->prepare('INSERT INTO medical_services (user_id, provider_user_id, name, name_ar, description, category, price, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
        $ins->execute([
            $userId,
            $userId,
            $orgName ?: 'معدة طبية جديدة',
            $orgName ?: 'معدة طبية جديدة',
            'معدة طبية مفعلة عبر الاشتراك',
            'home_care',
            0,
        ]);
        return;
    }

    if ($role === 'lab') {
        $chk = $db->prepare('SELECT id FROM labs WHERE user_id = ? LIMIT 1');
        $chk->execute([$userId]);
        if ($chk->fetch()) return;

        $ins = $db->prepare('INSERT INTO labs (user_id, name, wilaya, city, phone, address, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
        $ins->execute([
            $userId,
            $orgName ?: 'مخبر جديد',
            'الجزائر',
            'الجزائر العاصمة',
            $phone ?: '0000000000',
            'الجزائر',
        ]);
        return;
    }

    if ($role === 'med_rep') {
        $chk = $db->prepare('SELECT id FROM med_reps WHERE user_id = ? LIMIT 1');
        $chk->execute([$userId]);
        if ($chk->fetch()) return;

        $ins = $db->prepare('INSERT INTO med_reps (user_id, name, company, wilaya, phone) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([$userId, $orgName ?: 'مزود أدوية', $orgName ?: 'شركة دوائية', 'الجزائر', $phone ?: '0000000000']);
    }
}
