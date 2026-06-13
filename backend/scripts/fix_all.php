<?php
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$db  = getDB();
$db->set_charset('utf8mb4');

echo "[fix] Starting comprehensive DB fix...\n";

// ── 1. Fix donations table — actual schema: id,user_id,title,description,wilaya,status,created_at ──
$dcols    = $db->query("SHOW COLUMNS FROM donations")->fetch_all(MYSQLI_ASSOC);
$dColNames = array_column($dcols, 'Field');

if (!in_array('item_name',    $dColNames)) { $db->query("ALTER TABLE donations ADD COLUMN item_name VARCHAR(200)"); echo "[fix] donations.item_name added\n"; }
if (!in_array('item_name_ar', $dColNames)) { $db->query("ALTER TABLE donations ADD COLUMN item_name_ar VARCHAR(200)"); echo "[fix] donations.item_name_ar added\n"; }
if (!in_array('city',         $dColNames)) { $db->query("ALTER TABLE donations ADD COLUMN city VARCHAR(100)"); echo "[fix] donations.city added\n"; }
if (!in_array('category',     $dColNames)) { $db->query("ALTER TABLE donations ADD COLUMN category VARCHAR(50) DEFAULT 'device'"); echo "[fix] donations.category added\n"; }
if (!in_array('condition',    $dColNames)) { $db->query("ALTER TABLE donations ADD COLUMN `condition` ENUM('new','good','fair') DEFAULT 'good'"); echo "[fix] donations.condition added\n"; }
if (!in_array('donor_name',   $dColNames)) { $db->query("ALTER TABLE donations ADD COLUMN donor_name VARCHAR(150)"); echo "[fix] donations.donor_name added\n"; }
if (!in_array('donor_phone',  $dColNames)) { $db->query("ALTER TABLE donations ADD COLUMN donor_phone VARCHAR(30)"); echo "[fix] donations.donor_phone added\n"; }

// ── 2. Fix medical_services table — actual schema: id,user_id,name,service_type,wilaya,city,phone,address ──
$mscols    = $db->query("SHOW COLUMNS FROM medical_services")->fetch_all(MYSQLI_ASSOC);
$msColNames = array_column($mscols, 'Field');

if (!in_array('type',        $msColNames)) { $db->query("ALTER TABLE medical_services ADD COLUMN type VARCHAR(60)"); echo "[fix] medical_services.type added\n"; }
if (!in_array('description', $msColNames)) { $db->query("ALTER TABLE medical_services ADD COLUMN description TEXT"); echo "[fix] medical_services.description added\n"; }

// ── 3. Create lab_analyses table ─────────────────────────────────────────
$db->query("CREATE TABLE IF NOT EXISTS lab_analyses (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    lab_id      INT NOT NULL,
    name        VARCHAR(255) NOT NULL,
    name_ar     VARCHAR(255),
    price       DECIMAL(10,2),
    duration    VARCHAR(50),
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "[fix] lab_analyses table OK\n";

// ── 4. Create medical_service_providers table ─────────────────────────────
$db->query("CREATE TABLE IF NOT EXISTS medical_service_providers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    service_id   INT,
    provider_name VARCHAR(150) NOT NULL,
    address      VARCHAR(255),
    city         VARCHAR(100),
    wilaya       VARCHAR(100),
    phone        VARCHAR(30),
    availability ENUM('available','limited','unavailable') DEFAULT 'available',
    is_active    TINYINT(1) DEFAULT 1,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "[fix] medical_service_providers table OK\n";

// ── 5. Update demo passwords to demo123 ──────────────────────────────────
$hash = password_hash('demo123', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = ?, is_active = 1, is_verified = 1 WHERE email = ?");
$accounts = [
    'admin@shifaa.dz', 'pharma@shifaa.dz', 'medrep@shifaa.dz', 'lab@shifaa.dz', 'service@shifaa.dz'
];
foreach ($accounts as $email) {
    $stmt->execute([$hash, $email]);
    echo "[fix] Password updated: $email\n";
}

// ── 6. Seed 15 labs ───────────────────────────────────────────────────────
$labs = [
  ['مخبر الحياة للتحاليل',   'مخبر الحياة',    'شارع الاستقلال رقم 12، الطارف',     'الطارف', 'الطارف', '038 60 22 33', 'بيولوجيا طبية',  4.8, 25],
  ['مخبر النور الطبي',        'مخبر النور',     'حي 500 مسكن، الطارف',               'الطارف', 'الطارف', '038 61 44 55', 'بيولوجيا وكيمياء', 4.6, 30],
  ['مخبر الشفاء للتحاليل',   'مخبر الشفاء',   'شارع بلعباس، الطارف',                'الطارف', 'الطارف', '038 62 66 77', 'بيولوجيا طبية',  4.7, 20],
  ['مخبر الأمل البيولوجي',   'مخبر الأمل',    'المركز الصحي، الطارف',                'الطارف', 'الطارف', '038 63 88 99', 'بيولوجيا ومناعة', 4.5, 35],
  ['مخبر الرعاية',            'مخبر الرعاية',  'حي الزهراء، الطارف',                 'الطارف', 'الطارف', '038 64 00 11', 'بيولوجيا طبية',  4.4, 40],
  ['مخبر إبن سينا',           'مخبر ابن سينا', 'شارع العربي بن مهيدي، عنابة',        'عنابة',  'عنابة',  '038 70 22 33', 'تحاليل شاملة',   4.9, 15],
  ['مخبر باستور للتحاليل',   'مخبر باستور',   'حي سيدي مبروك، عنابة',               'عنابة',  'عنابة',  '038 71 44 55', 'بكتيريولوجيا',   4.7, 20],
  ['مخبر العلوم الطبية',      'مخبر العلوم',   'شارع العلمة، عنابة',                  'عنابة',  'عنابة',  '038 72 66 77', 'هرمونات ومناعة', 4.6, 30],
  ['مخبر الوفاء للتحاليل',   'مخبر الوفاء',   'حي 1200 مسكن، عنابة',                'عنابة',  'عنابة',  '038 73 88 99', 'بيولوجيا طبية',  4.5, 25],
  ['مخبر الصحة الجيدة',       'مخبر الصحة',    'شارع لخضر بن طوبال، عنابة',          'عنابة',  'عنابة',  '038 74 00 11', 'تحاليل شاملة',   4.8, 20],
  ['مخبر المستقبل',           'مخبر المستقبل', 'حي العالية، عنابة',                   'عنابة',  'عنابة',  '038 75 22 33', 'بيولوجيا ووراثة', 4.6, 35],
  ['مخبر اليقين',             'مخبر اليقين',   'شارع الإخوة عيسى، عنابة',             'عنابة',  'عنابة',  '038 76 44 55', 'بيولوجيا طبية',  4.4, 30],
  ['مخبر الضوء',              'مخبر الضوء',    'حي الحاسي، عنابة',                    'عنابة',  'عنابة',  '038 77 66 77', 'كيمياء حيوية',   4.7, 25],
  ['مخبر سيرتا البيولوجي',   'مخبر سيرتا',   'شارع رضا حوحو، عنابة',                'عنابة',  'عنابة',  '038 78 88 99', 'بيولوجيا طبية',  4.5, 40],
  ['مخبر السلامة الطبية',     'مخبر السلامة',  'حي بلوزداد، عنابة',                   'عنابة',  'عنابة',  '038 79 00 11', 'تحاليل متخصصة',  4.6, 30],
];

$lStmt = $pdo->prepare("INSERT INTO labs (name, name_ar, address, city, wilaya, phone, speciality, rating, wait_time, is_active)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE rating=VALUES(rating), speciality=VALUES(speciality)");
foreach ($labs as $l) {
    $lStmt->execute($l);
}
echo "[fix] " . count($labs) . " labs seeded\n";

// ── 7. Seed donations ─────────────────────────────────────────────────────
$donations = [
  ['أحمد بوعزيز',   '0555 11 22 33', 'Wheelchair',             'كرسي متحرك',         'كرسي بحالة جيدة مستعمل 6 أشهر', 'الطارف',  'الطارف',  'device',        'good',  'open'],
  ['فاطمة بن علي',  '0661 44 55 66', 'Blood Pressure Monitor', 'جهاز ضغط الدم',      'جهاز جديد لم يستعمل',            'عنابة',   'عنابة',   'device',        'new',   'open'],
  ['محمد شريف',     '0770 77 88 99', 'Stethoscope',            'سماعة طبية',          'سماعة ليتمان أصلية',              'الجزائر', 'الجزائر', 'device',        'good',  'open'],
  ['خديجة حمداني', '0555 00 11 22', 'Medical Cane',           'عكاز طبي',            'عكازان بحالة مقبولة',             'وهران',   'وهران',   'device',        'fair',  'open'],
  ['يوسف بلعيد',   '0661 33 44 55', 'Glucometer',             'جهاز قياس السكر',     'مع شرائط القياس',                 'الطارف',  'عنابة',   'device',        'new',   'open'],
  ['نورة قاسمي',   '0770 66 77 88', 'Medical Mattress',       'مراتب طبية',          'مرتبة مضادة لقرح الفراش',         'عنابة',   'عنابة',   'device',        'good',  'open'],
  ['كريم سعدي',    '0555 99 00 11', 'Nebulizer',              'جهاز نيبوليزر',       'نيبوليزر منزلي للاستنشاق',        'قسنطينة', 'قسنطينة', 'device',        'good',  'open'],
  ['سامية بوكرمة', '0661 22 33 44', 'Shower Chair',           'كرسي استحمام',        'للمرضى وكبار السن',               'الطارف',  'الطارف',  'special_needs', 'fair',  'open'],
  ['علي بوشلاغم',  '0770 55 66 77', 'Wheelchair',             'كرسي متحرك كهربائي', 'كرسي متحرك كهربائي متطور',        'وهران',   'وهران',   'special_needs', 'good',  'open'],
  ['زهرة مداني',   '0555 88 99 00', 'Crutches',               'عكازات إبطية',        'زوج عكازات إبطية للبالغين',       'عنابة',   'عنابة',   'device',        'good',  'open'],
];

$dStmt = $pdo->prepare('INSERT INTO donations (donor_name, donor_phone, item_name, item_name_ar, description, wilaya, city, category, `condition`, status) VALUES (?,?,?,?,?,?,?,?,?,?)');
foreach ($donations as $d) {
    $dStmt->execute($d);
}
echo "[fix] " . count($donations) . " donations seeded\n";

// ── 8. Seed medical services ──────────────────────────────────────────────
$svcs = [
    ['home_care',         'رعاية منزلية',  'home_care',         'خدمات الرعاية الطبية في المنزل'],
    ['nursing',           'تمريض منزلي',   'nursing',           'خدمات التمريض المتخصصة'],
    ['physiotherapy',     'كينيزيثيرابي', 'physiotherapy',     'العلاج الطبيعي وإعادة التأهيل'],
    ['medical_transport', 'نقل طبي',       'medical_transport', 'سيارات الإسعاف والنقل الطبي'],
];
$svStmt = $pdo->prepare("INSERT IGNORE INTO medical_services (name, service_type, type, description, is_active) VALUES (?,?,?,?,1)");
foreach ($svcs as $s) {
    $svStmt->execute([$s[1], $s[0], $s[2], $s[3]]);
}

// Get service IDs by type
$svcIds = [];
foreach ($pdo->query("SELECT id, COALESCE(type, service_type) as stype FROM medical_services")->fetchAll() as $row) {
    $svcIds[$row['stype']] = $row['id'];
}

$providers = [
  [$svcIds['home_care']??1,         'فريق الرعاية المنزلية الطارف',    'حي الزيتونة',           'الطارف',   'الطارف',   '0555 10 20 30', 'available'],
  [$svcIds['home_care']??1,         'خدمات الصحة المنزلية عنابة',      'حي سيدي مبروك',         'عنابة',    'عنابة',    '0661 40 50 60', 'available'],
  [$svcIds['nursing']??2,           'تمريض الشفاء الطارف',             'شارع الاستقلال',        'الطارف',   'الطارف',   '0770 70 80 90', 'available'],
  [$svcIds['nursing']??2,           'ممرضات عنابة الخبيرات',            'حي العلمة',              'عنابة',    'عنابة',    '0555 11 22 33', 'limited'],
  [$svcIds['physiotherapy']??3,     'مركز العلاج الطبيعي الطارف',       'المركز الطبي',          'الطارف',   'الطارف',   '0661 44 55 66', 'available'],
  [$svcIds['physiotherapy']??3,     'عيادة الكينيزيثيرابي عنابة',       'حي 1200 مسكن',          'عنابة',    'عنابة',    '0770 77 88 99', 'available'],
  [$svcIds['medical_transport']??4, 'إسعاف النقل الطبي الطارف',         'مستشفى الطارف',         'الطارف',   'الطارف',   '0555 00 11 22', 'available'],
  [$svcIds['medical_transport']??4, 'سيارات النقل الطبي عنابة',         'مستشفى عنابة الجامعي', 'عنابة',    'عنابة',    '0661 33 44 55', 'limited'],
];

$pStmt = $pdo->prepare("INSERT IGNORE INTO medical_service_providers
    (service_id, provider_name, address, city, wilaya, phone, availability, is_active)
    VALUES (?,?,?,?,?,?,?,1)");
foreach ($providers as $p) { $pStmt->execute($p); }
echo "[fix] Medical services & providers seeded\n";

// ── 9. Verify row counts ───────────────────────────────────────────────────
$tables = ['users','pharmacies','inventory','labs','donations','medical_services','medical_service_providers'];
foreach ($tables as $t) {
    $cnt = $db->query("SELECT COUNT(*) as c FROM `$t`")->fetch_assoc()['c'];
    echo "[fix] $t: $cnt rows\n";
}

// ── 10. Verify demo passwords ─────────────────────────────────────────────
echo "\n[verify] Demo account passwords:\n";
$vStmt = $pdo->prepare("SELECT email, password_hash FROM users WHERE email IN ('admin@shifaa.dz','pharma@shifaa.dz','medrep@shifaa.dz','lab@shifaa.dz','service@shifaa.dz')");
$vStmt->execute();
foreach ($vStmt->fetchAll() as $row) {
    $ok = password_verify('demo123', $row['password_hash']);
    echo "[verify] " . ($ok ? "✓" : "✗") . " " . $row['email'] . "\n";
}

$db->close();
echo "\n[fix] ✅ ALL FIXES APPLIED\n";
