<?php
/**
 * patch_and_seed.php — run once via CLI: php backend/scripts/patch_and_seed.php
 * Patches schema, seeds all demo data for Shifaa DZ
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$pdo->exec("SET NAMES utf8mb4");

echo "=== Shifaa DZ — Schema Patch & Seed ===\n\n";

// ─── HELPER ────────────────────────────────────────────────────────────────
function patch(PDO $pdo, string $sql): void {
    try { $pdo->exec($sql); } catch (Throwable $e) { /* silently ignore duplicate column etc */ }
}

// ══════════════════════════════════════════════════════════════════════════════
// 1. SCHEMA PATCHES
// ══════════════════════════════════════════════════════════════════════════════

// Fix donations table — add columns the JS expects
patch($pdo, "ALTER TABLE donations ADD COLUMN donor_name VARCHAR(150) AFTER user_id");
patch($pdo, "ALTER TABLE donations ADD COLUMN donor_phone VARCHAR(30) AFTER donor_name");
patch($pdo, "ALTER TABLE donations ADD COLUMN item_name VARCHAR(200) AFTER donor_phone");
patch($pdo, "ALTER TABLE donations ADD COLUMN quantity INT DEFAULT 1 AFTER item_name");
patch($pdo, "ALTER TABLE donations ADD COLUMN condition_status ENUM('new','good','fair') DEFAULT 'good' AFTER quantity");
patch($pdo, "ALTER TABLE donations ADD COLUMN notes TEXT AFTER wilaya");
patch($pdo, "ALTER TABLE donations ADD COLUMN is_available TINYINT(1) DEFAULT 1 AFTER notes");
// Change status enum to include pending/approved/rejected
patch($pdo, "ALTER TABLE donations MODIFY COLUMN status ENUM('open','closed','fulfilled','pending','approved','rejected') DEFAULT 'pending'");

// Fix labs table — add missing columns
patch($pdo, "ALTER TABLE labs ADD COLUMN name_ar VARCHAR(191) AFTER name");
patch($pdo, "ALTER TABLE labs ADD COLUMN email VARCHAR(191) AFTER phone");
patch($pdo, "ALTER TABLE labs ADD COLUMN speciality VARCHAR(150) AFTER email");
patch($pdo, "ALTER TABLE labs ADD COLUMN rating DECIMAL(3,1) DEFAULT 4.5 AFTER speciality");
patch($pdo, "ALTER TABLE labs ADD COLUMN subscription_plan VARCHAR(50) AFTER rating");

// Fix medical_services table — add missing columns for my-services.php
patch($pdo, "ALTER TABLE medical_services ADD COLUMN provider_user_id INT AFTER user_id");
patch($pdo, "ALTER TABLE medical_services ADD COLUMN name_ar VARCHAR(191) AFTER name");
patch($pdo, "ALTER TABLE medical_services ADD COLUMN description TEXT AFTER name_ar");
patch($pdo, "ALTER TABLE medical_services ADD COLUMN category VARCHAR(100) AFTER description");
patch($pdo, "ALTER TABLE medical_services ADD COLUMN price DECIMAL(10,2) AFTER category");

// Fix pharmacies — add missing columns
patch($pdo, "ALTER TABLE pharmacies ADD COLUMN email VARCHAR(191) AFTER phone");
patch($pdo, "ALTER TABLE pharmacies ADD COLUMN subscription_plan VARCHAR(50) AFTER email");
patch($pdo, "ALTER TABLE pharmacies ADD COLUMN is_verified TINYINT(1) DEFAULT 0 AFTER subscription_plan");

// Create lab_analyses table
patch($pdo, "CREATE TABLE IF NOT EXISTS lab_analyses (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    lab_id           INT,
    name             VARCHAR(200) NOT NULL,
    name_ar          VARCHAR(200),
    category         VARCHAR(100),
    price            DECIMAL(10,2),
    preparation_time VARCHAR(50),
    description      TEXT,
    is_active        TINYINT(1) DEFAULT 1,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create rep_products table
patch($pdo, "CREATE TABLE IF NOT EXISTS rep_products (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    rep_id       INT,
    name         VARCHAR(200) NOT NULL,
    total_stock  INT DEFAULT 0,
    low_stock_pharmacies INT DEFAULT 0,
    status       ENUM('good','warning','critical') DEFAULT 'good',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rep_id) REFERENCES med_reps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create rep_alerts table
patch($pdo, "CREATE TABLE IF NOT EXISTS rep_alerts (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    rep_id           INT,
    pharmacy_id      INT,
    product_name     VARCHAR(200),
    pharmacy_name    VARCHAR(191),
    pharmacy_phone   VARCHAR(30),
    remaining_stock  INT DEFAULT 0,
    severity         ENUM('high','medium','low') DEFAULT 'medium',
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rep_id) REFERENCES med_reps(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create partnership_requests table
patch($pdo, "CREATE TABLE IF NOT EXISTS partnership_requests (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    rep_id      INT,
    pharmacy_id INT,
    status      ENUM('pending','accepted','rejected','revoked') DEFAULT 'pending',
    message     TEXT,
    rep_name    VARCHAR(191),
    rep_phone   VARCHAR(30),
    rep_email   VARCHAR(191),
    rep_region  VARCHAR(100),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rep_id)      REFERENCES med_reps(id)   ON DELETE CASCADE,
    FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create reservations table (pharmacy reservations)
patch($pdo, "CREATE TABLE IF NOT EXISTS reservations (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    pharmacy_id  INT,
    patient_name VARCHAR(191),
    item_name    VARCHAR(200),
    quantity     INT DEFAULT 1,
    status       ENUM('pending','confirmed','ready','cancelled') DEFAULT 'pending',
    notes        TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create resupply_requests table
patch($pdo, "CREATE TABLE IF NOT EXISTS resupply_requests (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    rep_id             INT,
    pharmacy_id        INT,
    product_name       VARCHAR(200),
    requested_quantity INT DEFAULT 1,
    status             ENUM('pending','confirmed','sent','rejected') DEFAULT 'pending',
    message            TEXT,
    rep_name           VARCHAR(191),
    rep_phone          VARCHAR(30),
    pharmacy_name      VARCHAR(191),
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rep_id)      REFERENCES med_reps(id)   ON DELETE SET NULL,
    FOREIGN KEY (pharmacy_id) REFERENCES pharmacies(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "✓ Schema patched\n";

// ══════════════════════════════════════════════════════════════════════════════
// 2. SEED DEMO USERS (password: demo123)
// ══════════════════════════════════════════════════════════════════════════════
$pw = password_hash('demo123', PASSWORD_BCRYPT);
$accounts = [
    ['مدير النظام',         'admin@shifaa.dz',   'admin'],
    ['صيدلية التجريب',      'pharma@shifaa.dz',  'pharmacist'],
    ['مزود أدوية تجريبي',    'medrep@shifaa.dz',  'med_rep'],
    ['مخبر التجريب',        'lab@shifaa.dz',     'lab'],
    ['معدات طبية تجريبية',  'service@shifaa.dz', 'medical_services'],
];
$s = $pdo->prepare("INSERT INTO users (full_name,email,password_hash,role,is_active,is_verified) VALUES (?,?,?,?,1,1)
    ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),is_active=1,is_verified=1");
foreach ($accounts as [$name,$email,$role]) { $s->execute([$name,$email,$pw,$role]); }
echo "✓ Demo users seeded (password: demo123)\n";

// ══════════════════════════════════════════════════════════════════════════════
// 3. SEED 25 PHARMACIES
// ══════════════════════════════════════════════════════════════════════════════
$pharmUserId = (int)$pdo->query("SELECT id FROM users WHERE email='pharma@shifaa.dz' LIMIT 1")->fetchColumn();

$pharmacies = [
    ['صيدلية الشفاء',      'شارع الاستقلال 12', 'تارف',             'تارف',             '038 60 11 22', 'pharma@shifaa.dz',     'plan_2', $pharmUserId],
    ['صيدلية النور',        'حي الزيتونة',        'عنابة',            'عنابة',            '038 70 33 44', 'nour@pharma.dz',       'plan_1', null],
    ['صيدلية السلامة',      'شارع بومرداس',        'تارف',             'تارف',             '038 61 55 66', 'salama@pharma.dz',     'plan_1', null],
    ['صيدلية الأمل',        'شارع سعيد حجار',     'عنابة',            'عنابة',            '038 72 77 88', 'amal@pharma.dz',       'plan_2', null],
    ['صيدلية الحياة',       'المركز التجاري',      'تارف',             'تارف',             '038 62 99 00', 'hayat@pharma.dz',      'plan_1', null],
    ['صيدلية الرحمة',       'حي 1200 مسكن',       'عنابة',            'عنابة',            '038 74 11 22', 'rahma@pharma.dz',      'plan_1', null],
    ['صيدلية العافية',      'شارع الثورة',          'الجزائر العاصمة', 'الجزائر العاصمة', '021 30 11 22', 'afiya@pharma.dz',      'plan_2', null],
    ['صيدلية الرعاية',      'حي العلمة',            'وهران',            'وهران',            '041 40 33 44', 'riaya@pharma.dz',      'plan_1', null],
    ['صيدلية ابن سينا',     'شارع ديدوش مراد',     'الجزائر العاصمة', 'الجزائر العاصمة', '021 31 55 66', 'ibnsina@pharma.dz',    'plan_2', null],
    ['صيدلية الوفاء',       'حي حسان',              'قسنطينة',          'قسنطينة',          '031 50 77 88', 'wafa@pharma.dz',       'plan_1', null],
    ['صيدلية الفجر',        'شارع العقيد لطفي',    'سطيف',             'سطيف',             '036 60 99 00', 'fadjr@pharma.dz',      'plan_1', null],
    ['صيدلية الصحة',        'حي سيدي سعيد',        'بجاية',            'بجاية',            '034 70 11 22', 'sihha@pharma.dz',      'plan_2', null],
    ['صيدلية الضوء',        'شارع العربي بن مهيدي', 'تيزي وزو',        'تيزي وزو',        '026 80 33 44', 'daou@pharma.dz',       'plan_1', null],
    ['صيدلية الإشراق',      'شارع مولود فرعون',    'باتنة',            'باتنة',            '033 90 55 66', 'ichraq@pharma.dz',     'plan_1', null],
    ['صيدلية الكرامة',      'حي النصر',             'بسكرة',            'بسكرة',            '033 40 77 88', 'karama@pharma.dz',     'plan_2', null],
    ['صيدلية التوازن',      'شارع زيغود يوسف',     'تلمسان',           'تلمسان',           '043 50 99 00', 'tawazoun@pharma.dz',   'plan_1', null],
    ['صيدلية الخير',        'حي بن بادة',           'مستغانم',          'مستغانم',          '045 60 11 22', 'khair@pharma.dz',      'plan_1', null],
    ['صيدلية الهداية',      'شارع الشهداء',          'ورقلة',            'ورقلة',            '029 70 33 44', 'hidaya@pharma.dz',     'plan_2', null],
    ['صيدلية القدس',        'حي المسجد',             'الشلف',            'الشلف',            '027 80 55 66', 'qods@pharma.dz',       'plan_1', null],
    ['صيدلية الصداقة',      'شارع 1 نوفمبر',        'سكيكدة',           'سكيكدة',           '038 20 77 88', 'sadaqa@pharma.dz',     'plan_1', null],
    ['صيدلية الربيع',       'حي شاطئ',              'عنابة',            'عنابة',            '038 71 99 00', 'rabii@pharma.dz',      'plan_2', null],
    ['صيدلية السعادة',      'شارع حسيبة بن بوعلي', 'الجزائر العاصمة', 'الجزائر العاصمة', '021 32 11 22', 'saada@pharma.dz',      'plan_1', null],
    ['صيدلية الثقة',        'حي بلاطة',              'قسنطينة',          'قسنطينة',          '031 51 33 44', 'thiqa@pharma.dz',      'plan_2', null],
    ['صيدلية البيضاء',      'شارع التحرير',          'وهران',            'وهران',            '041 41 55 66', 'baidaa@pharma.dz',     'plan_1', null],
    ['صيدلية الزهراء',      'حي فارس',              'تيزي وزو',         'تيزي وزو',         '026 81 77 88', 'zahraa@pharma.dz',     'plan_1', null],
];

$pdo->exec("DELETE FROM pharmacies WHERE email IS NULL OR email NOT IN ('pharma@shifaa.dz')");
$stmt = $pdo->prepare("INSERT IGNORE INTO pharmacies (name,address,wilaya,city,phone,email,subscription_plan,is_active,is_verified,user_id) VALUES (?,?,?,?,?,?,?,1,1,?)");
foreach ($pharmacies as $p) { $stmt->execute($p); }
echo "✓ 25 pharmacies seeded\n";

$pharmacyId = (int)$pdo->query("SELECT id FROM pharmacies WHERE email='pharma@shifaa.dz' LIMIT 1")->fetchColumn() ?: 1;

// ══════════════════════════════════════════════════════════════════════════════
// 4. SEED 25 LABORATORIES
// ══════════════════════════════════════════════════════════════════════════════
$labUserId = (int)$pdo->query("SELECT id FROM users WHERE email='lab@shifaa.dz' LIMIT 1")->fetchColumn();

$labs = [
    ['مخبر الحياة للتحاليل',      'شارع الاستقلال 12', 'تارف',            'تارف',            '038 60 22 33', 'lab@shifaa.dz',     'بيولوجيا طبية',    $labUserId],
    ['مخبر النور الطبي',            'حي 500 مسكن',        'تارف',            'تارف',            '038 61 44 55', 'nour@lab.dz',       'بيولوجيا وكيمياء', null],
    ['مخبر الشفاء للتحاليل',       'شارع بلعباس',         'تارف',            'تارف',            '038 62 66 77', 'shifa@lab.dz',      'بيولوجيا طبية',    null],
    ['مخبر الأمل البيولوجي',       'المركز الصحي',        'تارف',            'تارف',            '038 63 88 99', 'amal@lab.dz',       'بيولوجيا ومناعة', null],
    ['مخبر الرعاية',                'حي الزهراء',           'تارف',            'تارف',            '038 64 00 11', 'riaya@lab.dz',      'بيولوجيا طبية',    null],
    ['مخبر إبن سينا',               'شارع العربي بن مهيدي','عنابة',           'عنابة',           '038 70 22 33', 'ibnsina@lab.dz',    'تحاليل شاملة',     null],
    ['مخبر باستور للتحاليل',       'حي سيدي مبروك',       'عنابة',           'عنابة',           '038 71 44 55', 'pasteur@lab.dz',    'بكتيريولوجيا',     null],
    ['مخبر العلوم الطبية',         'شارع العلمة',           'عنابة',           'عنابة',           '038 72 66 77', 'ulum@lab.dz',       'هرمونات ومناعة',  null],
    ['مخبر الوفاء للتحاليل',       'حي 1200 مسكن',        'عنابة',           'عنابة',           '038 73 88 99', 'wafa@lab.dz',       'بيولوجيا طبية',    null],
    ['مخبر الصحة الجيدة',          'شارع لخضر بن طوبال',  'عنابة',           'عنابة',           '038 74 00 11', 'sihha@lab.dz',      'تحاليل شاملة',     null],
    ['مخبر المستقبل',               'حي العالية',            'عنابة',           'عنابة',           '038 75 22 33', 'mustaqbal@lab.dz',  'بيولوجيا ووراثة', null],
    ['مخبر اليقين',                 'شارع الإخوة عيسى',    'عنابة',           'عنابة',           '038 76 44 55', 'yaqin@lab.dz',      'بيولوجيا طبية',    null],
    ['مخبر سيرتا البيولوجي',       'شارع رضا حوحو',        'قسنطينة',         'قسنطينة',         '031 50 66 77', 'cirta@lab.dz',      'بيولوجيا طبية',    null],
    ['مخبر الجزائر المركزي',       'شارع ديدوش مراد',      'الجزائر العاصمة','الجزائر العاصمة','021 30 88 99', 'central@lab.dz',    'تحاليل متخصصة',    null],
    ['مخبر وهران للتحاليل',        'حي السانية',            'وهران',           'وهران',           '041 40 00 11', 'oran@lab.dz',       'بيولوجيا طبية',    null],
    ['مخبر سطيف البيولوجي',        'شارع الشهداء',          'سطيف',            'سطيف',            '036 60 22 33', 'setif@lab.dz',      'بيولوجيا ومناعة', null],
    ['مخبر بجاية الطبي',           'حي الصياد',             'بجاية',           'بجاية',           '034 70 44 55', 'bejaia@lab.dz',     'هرمونات وسكري',   null],
    ['مخبر تيزي وزو',              'شارع أول نوفمبر',       'تيزي وزو',        'تيزي وزو',        '026 80 66 77', 'tizi@lab.dz',       'بيولوجيا طبية',    null],
    ['مخبر باتنة للتحاليل',        'حي بوياقي',             'باتنة',           'باتنة',           '033 90 88 99', 'batna@lab.dz',      'تحاليل شاملة',     null],
    ['مخبر بسكرة الطبي',           'شارع النهضة',           'بسكرة',           'بسكرة',           '033 40 00 11', 'biskra@lab.dz',     'بيولوجيا طبية',    null],
    ['مخبر تلمسان البيولوجي',      'حي المنصورة',           'تلمسان',          'تلمسان',          '043 50 22 33', 'tlemcen@lab.dz',    'بيولوجيا وكيمياء',null],
    ['مخبر مستغانم الطبي',         'شارع بن باديس',         'مستغانم',         'مستغانم',         '045 60 44 55', 'mostaganem@lab.dz', 'بكتيريولوجيا',     null],
    ['مخبر ورقلة للتحاليل',        'حي حسي مسعود',         'ورقلة',           'ورقلة',           '029 70 66 77', 'ouargla@lab.dz',    'بيولوجيا طبية',    null],
    ['مخبر الشلف الطبي',           'شارع زيغود يوسف',      'الشلف',           'الشلف',           '027 80 88 99', 'chlef@lab.dz',      'تحاليل شاملة',     null],
    ['مخبر سكيكدة البيولوجي',      'حي الأول نوفمبر',      'سكيكدة',          'سكيكدة',          '038 20 00 11', 'skikda@lab.dz',     'بيولوجيا طبية',    null],
];

$pdo->exec("DELETE FROM labs WHERE user_id IS NULL");
$stmt = $pdo->prepare("INSERT IGNORE INTO labs (name,address,wilaya,city,phone,email,speciality,is_active,user_id) VALUES (?,?,?,?,?,?,?,1,?)");
foreach ($labs as $l) { $stmt->execute($l); }
echo "✓ 25 labs seeded\n";

$labId = (int)$pdo->query("SELECT id FROM labs WHERE email='lab@shifaa.dz' LIMIT 1")->fetchColumn() ?: 1;

// ══════════════════════════════════════════════════════════════════════════════
// 5. SEED 40 LAB ANALYSES for demo lab
// ══════════════════════════════════════════════════════════════════════════════
$pdo->exec("DELETE FROM lab_analyses WHERE lab_id=$labId");
$analyses = [
    [$labId,'تحليل الدم الكامل (NFS)',               'تعداد كامل لخلايا الدم',          'دم كامل',      450,  '4 ساعات'],
    [$labId,'سكر الدم الصيامي',                        'قياس مستوى الجلوكوز',             'سكري',         300,  '2 ساعة'],
    [$labId,'هيموغلوبين السكري HbA1c',                'متابعة السكري على 3 أشهر',        'سكري',         700,  '6 ساعات'],
    [$labId,'وظائف الكلى (Créatinine+Urée)',           'تقييم الوظيفة الكلوية',           'كلى',          500,  '4 ساعات'],
    [$labId,'وظائف الكبد (Bilan hépatique)',           'تقييم وظائف الكبد',               'كبد',          800,  '6 ساعات'],
    [$labId,'دهون الدم (Bilan lipidique)',             'كوليسترول وثلاثيات',              'قلب',          600,  '4 ساعات'],
    [$labId,'هرمون الغدة الدرقية TSH',                'تشخيص الغدة الدرقية',             'هرمونات',      800,  '8 ساعات'],
    [$labId,'هرمون T3 وT4',                            'تحليل كامل للدرقية',              'هرمونات',      1000, '8 ساعات'],
    [$labId,'فيروس التهاب الكبد B (HBs Ag)',          'كشف التهاب الكبد ب',              'فيروسات',      600,  '4 ساعات'],
    [$labId,'فيروس التهاب الكبد C (Anti-HCV)',        'كشف التهاب الكبد ج',              'فيروسات',      700,  '4 ساعات'],
    [$labId,'فيروس نقص المناعة HIV',                   'فحص الأيدز',                       'فيروسات',      800,  '24 ساعة'],
    [$labId,'تحليل البول الكامل (ECBU)',               'فحص البول الشامل',                'بول',          400,  '4 ساعات'],
    [$labId,'زراعة بكتيرية (Hémoculture)',             'كشف العدوى البكتيرية',            'بكتيريولوجيا', 1200, '48 ساعة'],
    [$labId,'اختبار الحمل (Beta HCG)',                 'تأكيد الحمل',                      'هرمونات',      500,  '2 ساعة'],
    [$labId,'مجموعة الدم (Groupage)',                  'تحديد فصيلة الدم',                'دم كامل',      300,  '1 ساعة'],
    [$labId,'تحليل البراز (Coprologie)',               'فحص الجهاز الهضمي',               'هضم',          400,  '24 ساعة'],
    [$labId,'بروتين سي التفاعلي CRP',                  'مؤشر الالتهاب',                    'التهاب',       450,  '2 ساعة'],
    [$labId,'معدل ترسيب الكريات VS',                   'مؤشر الأمراض الالتهابية',         'التهاب',       350,  '2 ساعة'],
    [$labId,'فيريتين الحديد',                          'احتياطي الحديد في الجسم',          'معادن',        600,  '4 ساعات'],
    [$labId,'مستوى الحديد في الدم',                    'قياس مستوى الحديد',               'معادن',        400,  '4 ساعات'],
    [$labId,'فيتامين د (25-OH)',                       'قياس مستوى فيتامين د',             'فيتامينات',    900,  '8 ساعات'],
    [$labId,'فيتامين ب12',                             'قياس مستوى ب12',                   'فيتامينات',    700,  '6 ساعات'],
    [$labId,'حمض الفوليك',                             'فيتامين ب9',                        'فيتامينات',    650,  '6 ساعات'],
    [$labId,'هرمون الكورتيزول',                        'قياس هرمون الإجهاد',               'هرمونات',      800,  '6 ساعات'],
    [$labId,'هرمونات الخصوبة FSH/LH',                 'تقييم الخصوبة',                    'هرمونات',      1200, '8 ساعات'],
    [$labId,'هرمون البرولاكتين',                       'كشف فرط برولاكتين الدم',           'هرمونات',      700,  '6 ساعات'],
    [$labId,'مضادات النواة ANA',                        'كشف أمراض المناعة الذاتية',        'مناعة',        1500, '24 ساعة'],
    [$labId,'عامل الروماتيزم FR',                      'تشخيص التهاب المفاصل',             'مناعة',        600,  '4 ساعات'],
    [$labId,'كشف السل PPD',                            'اختبار التوبركولين',               'فيروسات',      400,  '72 ساعة'],
    [$labId,'تحليل السائل المنوي (Spermogramme)',      'تقييم الخصوبة لدى الرجل',         'خصوبة',        1500, '24 ساعة'],
    [$labId,'صورة الكريات البيضاء',                    'تفصيل خلايا الدفاع',               'دم كامل',      500,  '4 ساعات'],
    [$labId,'الصفائح الدموية',                          'عدد الصفيحات',                     'دم كامل',      300,  '2 ساعة'],
    [$labId,'بوتاسيوم وصوديوم',                        'الأملاح المعدنية في الدم',          'معادن',        450,  '2 ساعة'],
    [$labId,'كالسيوم وفوسفور',                         'معادن العظام',                      'معادن',        400,  '2 ساعة'],
    [$labId,'أميلاز وليباز',                            'إنزيمات البنكرياس',                'هضم',          600,  '4 ساعات'],
    [$labId,'ترانسامينازات SGOT/SGPT',                 'إنزيمات الكبد',                    'كبد',          500,  '4 ساعات'],
    [$labId,'بيلروبين كلي ومباشر',                    'تحليل اليرقان',                     'كبد',          450,  '4 ساعات'],
    [$labId,'بروثرومبين TP/TCA',                       'تقييم تخثر الدم',                  'دم كامل',      700,  '4 ساعات'],
    [$labId,'كشف كوفيد-19 PCR',                       'تشخيص كوفيد',                       'فيروسات',      2000, '6 ساعات'],
    [$labId,'كشف الأنفلونزا السريع',                    'تشخيص سريع للأنفلونزا',            'فيروسات',      800,  '2 ساعة'],
];
$s = $pdo->prepare("INSERT INTO lab_analyses (lab_id,name,description,category,price,preparation_time,is_active) VALUES (?,?,?,?,?,?,1)");
foreach ($analyses as $a) { $s->execute($a); }
echo "✓ 40 lab analyses seeded\n";

// ══════════════════════════════════════════════════════════════════════════════
// 6. SEED DONATIONS
// ══════════════════════════════════════════════════════════════════════════════
$pdo->exec("DELETE FROM donations");
$donations = [
    ['أحمد بوعزيز',   '0555 11 22 33', 'كرسي متحرك',          1, 'good',  'approved', 'تارف',   'كرسي بحالة جيدة، مستعمل 6 أشهر'],
    ['فاطمة بن علي',  '0661 44 55 66', 'جهاز ضغط الدم',       1, 'new',   'pending',  'عنابة',  'جهاز جديد لم يستعمل'],
    ['محمد شريف',      '0770 77 88 99', 'سماعة طبية',           1, 'good',  'pending',  'قسنطينة','سماعة ليتمان'],
    ['خديجة حمداني',  '0555 00 11 22', 'عكاز طبي',             2, 'fair',  'approved', 'تارف',   'عكازان بحالة مقبولة'],
    ['يوسف بلعيد',    '0661 33 44 55', 'جهاز قياس السكر',     1, 'new',   'pending',  'سطيف',   'مع شرائط القياس'],
    ['نورة قاسمي',    '0770 66 77 88', 'مراتب طبية',           1, 'good',  'rejected', 'بجاية',  'مرتبة مضادة لقرح الفراش'],
    ['كريم سعدي',     '0555 99 00 11', 'جهاز نيبوليزر',        1, 'good',  'pending',  'وهران',  'نيبوليزر منزلي'],
    ['سامية بوكرمة',  '0661 22 33 44', 'كرسي استحمام',         1, 'fair',  'approved', 'الجزائر العاصمة', 'للمرضى وكبار السن'],
    ['رشيد بن عمر',   '0770 55 66 77', 'جهاز قياس الأكسجين',  1, 'new',   'approved', 'باتنة',  'جهاز بولس أوكسيمتر'],
    ['سلمى عبدالله',  '0555 88 99 00', 'عكاز متعدد الوظائف',  1, 'good',  'pending',  'تلمسان', 'عكاز قابل للطي'],
    ['عمر بوزيد',     '0661 11 22 33', 'ميزان طبي',             1, 'good',  'approved', 'تارف',   'ميزان رقمي دقيق'],
    ['حنان مصطفى',   '0770 44 55 66', 'جهاز ترمومتر',         1, 'new',   'pending',  'عنابة',  'ترمومتر رقمي'],
    ['عادل زروق',     '0555 77 88 99', 'سرير طبي متحرك',       1, 'fair',  'rejected', 'قسنطينة','سرير للمرضى'],
    ['ليلى حمادي',    '0661 00 11 22', 'جهاز ضغط وأكسجين',   1, 'good',  'approved', 'سطيف',   'جهاز مركب'],
    ['بشير كريمي',    '0770 33 44 55', 'حوض مساج الأقدام',    1, 'new',   'pending',  'بجاية',  'للعلاج بالماء الدافئ'],
    ['وردة ساعد',     '0555 66 77 88', 'كرسي متحرك',           1, 'good',  'approved', 'وهران',  'كرسي خفيف الوزن'],
    ['مراد بلحاج',    '0661 99 00 11', 'جهاز تنفس CPAP',       1, 'fair',  'pending',  'الجزائر العاصمة', 'لعلاج انقطاع النفس'],
    ['زينب أحمد',     '0770 22 33 44', 'مطهرات وشاش طبي',     5, 'new',   'approved', 'تارف',   'مواد تضميد للمسجد والمدرسة'],
    ['طارق لعبيدي',   '0555 55 66 77', 'جهاز قياس درجة الحرارة',1,'new',  'pending',  'باتنة',  'ترمومتر بالأشعة تحت الحمراء'],
    ['أسماء بولعراس', '0661 88 99 00', 'أدوات إسعاف أولية',   1, 'good',  'approved', 'عنابة',  'حقيبة إسعافات متكاملة'],
];
$s = $pdo->prepare("INSERT INTO donations (donor_name,donor_phone,item_name,quantity,condition_status,status,wilaya,notes,is_available) VALUES (?,?,?,?,?,?,?,?,1)");
foreach ($donations as $d) { $s->execute($d); }
echo "✓ 20 donations seeded\n";

// ══════════════════════════════════════════════════════════════════════════════
// 7. SEED MED_REPS + REP_PRODUCTS + REP_ALERTS
// ══════════════════════════════════════════════════════════════════════════════
$repUserId = (int)$pdo->query("SELECT id FROM users WHERE email='medrep@shifaa.dz' LIMIT 1")->fetchColumn();

$pdo->exec("DELETE FROM med_reps WHERE user_id=$repUserId");
$pdo->prepare("INSERT INTO med_reps (user_id,name,company,wilaya,phone,is_active) VALUES (?,?,?,?,?,1)")
    ->execute([$repUserId,'مزود أدوية تجريبي','شركة الدواء الجزائرية','عنابة','0550 11 22 33']);
$repId = (int)$pdo->lastInsertId();

$pdo->exec("DELETE FROM rep_products WHERE rep_id=$repId");
$products = [
    [$repId,'دولوبران 500 مغ',    3200, 2, 'good'],
    [$repId,'أموكسيسيلين 500 مغ', 1800, 4, 'warning'],
    [$repId,'أوميبرازول 20 مغ',   2400, 1, 'good'],
    [$repId,'ميتفورمين 850 مغ',   900,  6, 'critical'],
    [$repId,'أملوديبين 5 مغ',     1500, 3, 'warning'],
    [$repId,'أتورفاستاتين 20 مغ', 2100, 2, 'good'],
    [$repId,'فيتامين د3 1000 وحدة',3500,1,'good'],
    [$repId,'سيتيريزين 10 مغ',    2800, 2, 'good'],
];
$s = $pdo->prepare("INSERT INTO rep_products (rep_id,name,total_stock,low_stock_pharmacies,status) VALUES (?,?,?,?,?)");
foreach ($products as $p) { $s->execute($p); }

$pdo->exec("DELETE FROM rep_alerts WHERE rep_id=$repId");
$alerts = [
    [$repId,'ميتفورمين 850 مغ',   'صيدلية الشفاء',   '038 60 11 22', 45,  'high'],
    [$repId,'أموكسيسيلين 500 مغ', 'صيدلية النور',    '038 70 33 44', 120, 'medium'],
    [$repId,'أملوديبين 5 مغ',     'صيدلية السلامة',  '038 61 55 66', 80,  'medium'],
    [$repId,'ميتفورمين 850 مغ',   'صيدلية الرحمة',   '038 74 11 22', 30,  'high'],
];
$s = $pdo->prepare("INSERT INTO rep_alerts (rep_id,product_name,pharmacy_name,pharmacy_phone,remaining_stock,severity) VALUES (?,?,?,?,?,?)");
foreach ($alerts as $a) { $s->execute($a); }
echo "✓ Med_rep seeded with products and alerts\n";

// ══════════════════════════════════════════════════════════════════════════════
// 8. SEED MEDICAL SERVICES for demo service user
// ══════════════════════════════════════════════════════════════════════════════
$svcUserId = (int)$pdo->query("SELECT id FROM users WHERE email='service@shifaa.dz' LIMIT 1")->fetchColumn();

$pdo->exec("DELETE FROM medical_services WHERE user_id=$svcUserId OR provider_user_id=$svcUserId");
$services = [
    [$svcUserId,$svcUserId,'رعاية منزلية',        'رعاية منزلية',        'خدمات الرعاية الطبية في المنزل', 'home_care',        2500],
    [$svcUserId,$svcUserId,'تمريض منزلي',          'تمريض منزلي',          'خدمات التمريض المتخصصة',         'nursing',          1800],
    [$svcUserId,$svcUserId,'كينيزيثيرابي',          'كينيزيثيرابي',          'العلاج الطبيعي وإعادة التأهيل',  'physiotherapy',    3000],
    [$svcUserId,$svcUserId,'نقل طبي إسعافي',        'نقل طبي',              'سيارات الإسعاف والنقل الطبي',    'medical_transport', 5000],
    [$svcUserId,$svcUserId,'متابعة مزمنين',         'رعاية مزمنين',          'متابعة مرضى الأمراض المزمنة',    'nursing',          3500],
    [$svcUserId,$svcUserId,'تضميد وحقن منزلي',      'تضميد وحقن',           'خدمات التضميد والحقن في المنزل', 'nursing',          1200],
    [$svcUserId,$svcUserId,'تأهيل ما بعد الجراحة',  'تأهيل بعد جراحة',     'إعادة تأهيل ما بعد العمليات',   'physiotherapy',    4000],
    [$svcUserId,$svcUserId,'نقل مرضى مزمنين',       'نقل المزمنين',          'نقل مرضى الغسيل الكلوي',        'medical_transport', 3000],
];
$s = $pdo->prepare("INSERT INTO medical_services (user_id,provider_user_id,name,service_type,description,category,price,is_active) VALUES (?,?,?,?,?,?,?,1)");
foreach ($services as $sv) { $s->execute($sv); }
echo "✓ 8 medical services seeded\n";

// ══════════════════════════════════════════════════════════════════════════════
// 9. SEED INVENTORY for demo pharmacy
// ══════════════════════════════════════════════════════════════════════════════
$pdo->exec("DELETE FROM inventory WHERE pharmacy_id=$pharmacyId");
$invItems = [
    [$pharmacyId,'Doliprane 1000mg',         'دولبيران 1000 ملغ',           'medicine',      150, 1],
    [$pharmacyId,'Amoxicilline 500mg',        'أموكسيسيلين 500 ملغ',         'medicine',      80,  1],
    [$pharmacyId,'Ibuprofène 400mg',          'إيبوبروفين 400 ملغ',           'medicine',      120, 1],
    [$pharmacyId,'Metformine 500mg',          'ميتفورمين 500 ملغ',            'medicine',      200, 1],
    [$pharmacyId,'Amlodipine 5mg',            'أملوديبين 5 ملغ',              'medicine',      90,  1],
    [$pharmacyId,'Oméprazole 20mg',           'أوميبرازول 20 ملغ',            'medicine',      160, 1],
    [$pharmacyId,'Atorvastatine 20mg',        'أتورفاستاتين 20 ملغ',          'medicine',      75,  1],
    [$pharmacyId,'Lisinopril 10mg',           'ليزينوبريل 10 ملغ',            'medicine',      110, 1],
    [$pharmacyId,'Levothyrox 50mcg',          'ليفوتيروكس 50 ميكروغرام',     'medicine',      45,  1],
    [$pharmacyId,'Ventoline 100mcg',          'فانتولين 100 ميكروغرام',       'medicine',      55,  1],
    [$pharmacyId,'Augmentin 1g',              'أوغمانتين 1 غ',                'medicine',      70,  1],
    [$pharmacyId,'Ciprofloxacine 500mg',      'سيبروفلوكساسين 500 ملغ',       'medicine',      85,  1],
    [$pharmacyId,'Paracétamol 500mg Enfant',  'باراسيتامول للأطفال',          'medicine',      200, 1],
    [$pharmacyId,'Vitamine C 1000mg',         'فيتامين ج 1000 ملغ',           'medicine',      300, 1],
    [$pharmacyId,'Vitamine D3 1000UI',        'فيتامين د3 1000 وحدة',         'medicine',      250, 1],
    [$pharmacyId,'Acide folique 5mg',         'حمض الفوليك 5 ملغ',            'medicine',      160, 1],
    [$pharmacyId,'Fer 50mg',                  'حديد 50 ملغ',                  'medicine',      140, 1],
    [$pharmacyId,'Zinc 15mg',                 'زنك 15 ملغ',                   'medicine',      180, 1],
    [$pharmacyId,'Loratadine 10mg',           'لوراتادين 10 ملغ',             'medicine',      130, 1],
    [$pharmacyId,'Spasfon 80mg',              'سباسفون 80 ملغ',               'medicine',      110, 0],
    [$pharmacyId,'Tensiomètre électronique',  'جهاز قياس ضغط الدم',           'device',        25,  1],
    [$pharmacyId,'Glucomètre',                'جهاز قياس السكر',              'device',        30,  1],
    [$pharmacyId,'Thermomètre infrarouge',    'ميزان الحرارة بالأشعة',        'device',        40,  1],
    [$pharmacyId,'Oxymètre de pouls',         'جهاز قياس الأكسجين',           'device',        35,  1],
    [$pharmacyId,'Nébuliseur',                'جهاز البخار',                  'device',        15,  1],
    [$pharmacyId,'Chaise roulante',           'كرسي متحرك',                   'special_needs', 8,   1],
    [$pharmacyId,'Béquilles',                 'عكازات',                       'special_needs', 20,  1],
    [$pharmacyId,'Crème solaire SPF50',       'كريم واقي من الشمس',           'parapharmacy',  80,  1],
    [$pharmacyId,'Gel hydroalcoolique 500ml', 'جل مطهر للأيدي',               'parapharmacy',  200, 1],
    [$pharmacyId,'Lait infantile 1er âge',    'حليب الرضع المرحلة 1',         'parapharmacy',  40,  0],
];
$s = $pdo->prepare("INSERT INTO inventory (pharmacy_id,product_name,product_name_ar,category,quantity,is_available) VALUES (?,?,?,?,?,?)");
foreach ($invItems as $it) { $s->execute($it); }
echo "✓ 30 inventory items seeded for pharmacy_id=$pharmacyId\n";

// ══════════════════════════════════════════════════════════════════════════════
// 10. DONE
// ══════════════════════════════════════════════════════════════════════════════
echo "\n=== ALL DONE ===\n";
$tables = ['users','pharmacies','labs','lab_analyses','donations','med_reps','rep_products','rep_alerts','medical_services','inventory'];
foreach ($tables as $t) {
    try {
        $cnt = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "  $t: $cnt rows\n";
    } catch (Throwable $e) { echo "  $t: ERROR\n"; }
}
