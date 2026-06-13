<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

define('DB_SOCK_INSTALL', '/home/runner/mysql-run/mysql.sock');
define('DB_USER_INSTALL', 'root');
define('DB_PASS_INSTALL', '');
define('DB_NAME_INSTALL', 'shifaa_dizad');

$conn = new mysqli(null, DB_USER_INSTALL, DB_PASS_INSTALL, null, null, DB_SOCK_INSTALL);
if ($conn->connect_error) {
    die('<p style="color:red">MySQL connection failed: ' . $conn->connect_error . '</p>');
}
$conn->set_charset('utf8mb4');

$demoAccounts = [
    [
        'full_name'     => 'Admin Shifaa',
        'email'         => 'admin@shifaa.dz',
        'phone'         => '0555000001',
        'password'      => 'Admin123!',
        'role'          => 'admin',
        'is_verified'   => 1,
    ],
    [
        'full_name'     => 'صيدلية النور',
        'email'         => 'pharma@shifaa.dz',
        'phone'         => '0555000010',
        'password'      => 'Demo123!',
        'role'          => 'pharmacist',
        'is_verified'   => 1,
    ],
    [
        'full_name'     => 'كريم بن يوسف',
        'email'         => 'medrep@shifaa.dz',
        'phone'         => '0555000011',
        'password'      => 'Demo123!',
        'role'          => 'med_rep',
        'is_verified'   => 1,
    ],
    [
        'full_name'     => 'مخبر ابن سينا',
        'email'         => 'lab@shifaa.dz',
        'phone'         => '0555000012',
        'password'      => 'Demo123!',
        'role'          => 'lab',
        'is_verified'   => 1,
    ],
    [
        'full_name'     => 'ميدي ستور',
        'email'         => 'medservices@shifaa.dz',
        'phone'         => '0555000013',
        'password'      => 'Demo123!',
        'role'          => 'medical_services',
        'is_verified'   => 1,
    ],
];

function executeSqlFile(mysqli $c, string $path): array {
    $r = ['success' => 0, 'errors' => []];
    if (!file_exists($path)) { $r['errors'][] = "File not found: $path"; return $r; }
    $sql = file_get_contents($path);
    foreach (preg_split('/;[\r\n]+/', $sql) as $q) {
        $q = trim($q);
        if (!$q) continue;
        if ($c->query($q)) $r['success']++;
        else $r['errors'][] = $c->error . ': ' . substr($q, 0, 80);
    }
    return $r;
}

$runSetup = isset($_POST['run_setup']);
?>
<!DOCTYPE html><html lang="ar" dir="rtl">
<head><meta charset="UTF-8"><title>تثبيت شفاء ديزاد</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Tajawal',sans-serif;background:#f0fdf4;color:#0f172a;padding:40px 20px}
.container{max-width:900px;margin:auto}
h1{font-size:2rem;margin-bottom:1.5rem;color:#059669}
.card{background:white;border-radius:16px;padding:24px;margin-bottom:20px;box-shadow:0 4px 20px rgba(0,0,0,.06)}
.ok{color:#059669;font-weight:700}.err{color:#dc2626}.warn{color:#d97706}
.btn{display:inline-block;padding:12px 28px;background:#10b981;color:white;border:none;border-radius:12px;font-family:inherit;font-size:1rem;font-weight:700;cursor:pointer;text-decoration:none;margin:4px}
table{width:100%;border-collapse:collapse;font-size:.875rem}
td,th{padding:.5rem .75rem;border:1px solid #e2e8f0;text-align:right}
th{background:#f8fafc;font-weight:800}
pre{background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;font-size:.75rem;overflow:auto;margin-top:8px}
</style>
</head><body><div class="container">
<h1>🏥 تثبيت منصة شفاء ديزاد</h1>

<?php if ($conn->connect_error): ?>
<div class="card"><p class="err">❌ فشل الاتصال: <?= htmlspecialchars($conn->connect_error) ?></p></div>
<?php else: ?>
<div class="card"><p class="ok">✅ MySQL متصل بنجاح (<?= $conn->server_info ?>)</p></div>

<?php if (!$runSetup): ?>
<div class="card">
<h2 style="margin-bottom:1rem">🗄️ تثبيت قاعدة البيانات</h2>
<p style="color:#64748b;margin-bottom:1.5rem;line-height:1.8">
سيتم: إنشاء قاعدة البيانات · الجداول · البيانات التجريبية · الحسابات الافتراضية
</p>
<form method="POST">
<button type="submit" name="run_setup" value="1" class="btn">🚀 بدء التثبيت</button>
</form>
</div>
<?php else:
    $conn->query("CREATE DATABASE IF NOT EXISTS shifaa_dizad CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db(DB_NAME_INSTALL);
    $conn->set_charset('utf8mb4');
    $conn->query('SET NAMES utf8mb4');

    $schemaR    = executeSqlFile($conn, __DIR__ . '/database/schema.sql');
    $extR       = executeSqlFile($conn, __DIR__ . '/database/schema_extension.sql');
    $seedR      = executeSqlFile($conn, __DIR__ . '/database/seed.sql');

    $errors = array_merge($schemaR['errors'], $extR['errors'], $seedR['errors']);

    foreach ($demoAccounts as $acc) {
        $hash = password_hash($acc['password'], PASSWORD_BCRYPT);
        $stmt = $conn->prepare("
            INSERT INTO users (full_name, email, phone, password_hash, role, is_verified, is_active)
            VALUES (?, ?, ?, ?, ?, 1, 1)
            ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                password_hash = VALUES(password_hash),
                role = VALUES(role),
                is_verified = 1,
                is_active = 1
        ");
        $stmt->bind_param('sssssi', $acc['full_name'], $acc['email'], $acc['phone'], $hash, $acc['role'], $acc['is_verified']);
        if (!$stmt->execute()) {
            $errors[] = 'User insert error: ' . $stmt->error;
        }
    }

    // Link medical_services demo account to a dedicated pharmacy + sample inventory
    $msRow = $conn->query("SELECT id FROM users WHERE email = 'medservices@shifaa.dz' LIMIT 1")->fetch_assoc();
    if ($msRow) {
        $msId = (int) $msRow['id'];
        $conn->query("
            INSERT INTO pharmacies
                (owner_user_id, name, address, wilaya, city, phone, email, description, rating, review_count, is_open, opening_hours, plan, is_verified)
            SELECT $msId, 'ميدي ستور', '15 شارع ديدوش مراد', 'الجزائر', 'الجزائر العاصمة', '0555000013', 'medservices@shifaa.dz',
                   'متجر معدات طبية — أجهزة ومستلزمات', 4.8, 95, 1, '08:00 - 20:00', 'enterprise', 1
            FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM pharmacies WHERE owner_user_id = $msId LIMIT 1)
        ");
        $phRow = $conn->query("SELECT id FROM pharmacies WHERE owner_user_id = $msId ORDER BY id ASC LIMIT 1")->fetch_assoc();
        if ($phRow) {
            $phId = (int) $phRow['id'];
            $conn->query("
                INSERT INTO inventory (pharmacy_id, product_name, category, quantity, minimum_stock, status, price)
                SELECT $phId, 'جهاز قياس الضغط', 'device', 25, 5, 'available', 7500
                FROM DUAL
                WHERE NOT EXISTS (SELECT 1 FROM inventory WHERE pharmacy_id = $phId LIMIT 1)
            ");
            $conn->query("
                INSERT INTO subscriptions (user_id, plan_name, role_type, billing_cycle, price, status)
                SELECT $msId, 'Enterprise', 'medical_services', 'yearly', 59000, 'active'
                FROM DUAL
                WHERE NOT EXISTS (SELECT 1 FROM subscriptions WHERE user_id = $msId AND status = 'active' LIMIT 1)
            ");
        }
    }
?>
<div class="card">
<?php if (empty($errors)): ?>
<p class="ok" style="font-size:1.1rem;margin-bottom:1rem">✅ اكتمل التثبيت بنجاح!</p>
<?php else: ?>
<p class="warn" style="margin-bottom:1rem">⚠️ اكتمل مع <?= count($errors) ?> تحذيرات</p>
<?php foreach(array_slice($errors,0,5) as $e): ?>
<pre class="err"><?= htmlspecialchars($e) ?></pre>
<?php endforeach; ?>
<?php endif; ?>
</div>

<div class="card">
<h2 style="margin-bottom:1rem">👤 حسابات تجريبية</h2>
<table>
<tr><th>البريد الإلكتروني</th><th>كلمة المرور</th><th>الدور</th></tr>
<?php foreach($demoAccounts as $a): ?>
<tr><td><?= $a['email'] ?></td><td><?= $a['password'] ?></td><td><?= $a['role'] ?></td></tr>
<?php endforeach; ?>
</table>
</div>

<div class="card">
<h2 style="margin-bottom:1rem">🔗 روابط</h2>
<a href="../frontend/pages/login.html" class="btn">🔐 صفحة تسجيل الدخول</a>
<a href="../frontend/index.html" class="btn" style="background:#3b82f6">🏠 الصفحة الرئيسية</a>
<a href="../frontend/pages/admin/dashboard.html" class="btn" style="background:#6366f1">🛡️ لوحة الإدارة</a>
</div>
<?php endif; ?>
<?php endif; ?>
</div></body></html>
