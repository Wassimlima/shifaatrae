<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';

try {
    $pdo = getDbConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("SELECT setting_key, setting_value, setting_type FROM platform_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $settings = [];
        foreach ($rows as $row) {
            $val = $row['setting_value'];
            if ($row['setting_type'] === 'integer') $val = (int)$val;
            if ($row['setting_type'] === 'boolean') $val = (bool)(int)$val;
            if ($row['setting_type'] === 'json')    $val = json_decode($val, true);
            $settings[$row['setting_key']] = $val;
        }

        sendSuccess($settings);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/../../utils/auth.php';
        requireAdmin();
        $data = getBody();
        $updates = $data['settings'] ?? $data;
        if (!is_array($updates) || empty($updates)) {
            sendError('لا توجد إعدادات للحفظ', 400);
        }
        $stmt = $pdo->prepare("UPDATE platform_settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($updates as $key => $val) {
            if (!is_string($key)) continue;
            $stmt->execute([is_scalar($val) ? (string)$val : json_encode($val), $key]);
        }
        sendSuccess(['message' => 'تم حفظ الإعدادات']);
    } else {
        sendError('Method not allowed', 405);
    }

} catch (PDOException $e) {
    sendError('خطأ في قاعدة البيانات', 500);
}
