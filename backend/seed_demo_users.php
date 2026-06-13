<?php
require_once __DIR__ . '/config/database.php';

$accounts = [
    ['admin@shifaa.dz',   'demo123', 'مدير النظام',          'admin'],
    ['pharma@shifaa.dz',  'demo123', 'صيدلية التجريب',       'pharmacist'],
    ['medrep@shifaa.dz',  'demo123', 'مزود أدوية تجريبي',     'med_rep'],
    ['lab@shifaa.dz',     'demo123', 'مخبر التجريب',         'lab'],
    ['service@shifaa.dz', 'demo123', 'معدات طبية تجريبية',   'medical_services'],
];

try {
    $pdo = getDbConnection();

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        full_name     VARCHAR(191) NOT NULL,
        email         VARCHAR(191) NOT NULL UNIQUE,
        phone         VARCHAR(30),
        password_hash VARCHAR(255) NOT NULL,
        role          VARCHAR(50) NOT NULL DEFAULT 'patient',
        is_active     TINYINT(1) NOT NULL DEFAULT 1,
        is_verified   TINYINT(1) NOT NULL DEFAULT 1,
        last_login    DATETIME,
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $insert = $pdo->prepare(
        'INSERT INTO users (full_name, email, password_hash, role, is_active, is_verified)
         VALUES (?, ?, ?, ?, 1, 1)
         ON DUPLICATE KEY UPDATE
           password_hash = VALUES(password_hash),
           is_active     = 1,
           is_verified   = 1'
    );

    foreach ($accounts as [$email, $password, $name, $role]) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $insert->execute([$name, $email, $hash, $role]);
        echo "[demo] Upserted: $email ($role)\n";
    }

    $pharmUser = $pdo->query("SELECT id FROM users WHERE email='pharma@shifaa.dz'")->fetch();
    if ($pharmUser) {
        $uid = $pharmUser['id'];
        $exists = $pdo->prepare("SELECT id FROM pharmacies WHERE user_id = ?");
        $exists->execute([$uid]);
        if (!$exists->fetch()) {
            $pdo->prepare("INSERT IGNORE INTO pharmacies (user_id, name, wilaya, city, phone, is_active) VALUES (?, 'صيدلية التجريب', 'الجزائر', 'الجزائر العاصمة', '0550000000', 1)")->execute([$uid]);
            echo "[demo] Demo pharmacy created for pharma@shifaa.dz\n";
        }
    }

    echo "[demo] Done — all demo accounts are ready.\n";

} catch (Throwable $e) {
    echo "[demo] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
