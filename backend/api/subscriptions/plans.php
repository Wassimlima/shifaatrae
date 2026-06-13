<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendError('Method not allowed', 405);

$professions = [
    ['role_type' => 'pharmacy',         'emoji' => '🏥', 'label' => 'الصيدليات'],
    ['role_type' => 'lab',              'emoji' => '🧪', 'label' => 'مخابر التحاليل'],
    ['role_type' => 'med_rep',          'emoji' => '💼', 'label' => 'مزودو الأدوية الطبيون'],
    ['role_type' => 'medical_services', 'emoji' => '🩺', 'label' => 'المعدات الطبية'],
];

$tiers = [
    [
        'tier'        => 'starter',
        'name_suffix' => 'المبتدئ',
        'price'       => 1999,
        'features'    => [
            'ظهور في نتائج البحث',
            'صفحة ملف احترافي',
            'بيانات الاتصال والموقع',
            'دعم بالبريد الإلكتروني',
        ],
    ],
    [
        'tier'        => 'pro',
        'name_suffix' => 'الاحترافي',
        'price'       => 3999,
        'features'    => [
            'كل مميزات المبتدئ',
            'ظهور مميز في الأعلى',
            'إحصائيات تفصيلية',
            'إدارة كاملة للمحتوى',
            'دعم فني أولوية 24/7',
        ],
    ],
];

$plans = [];
foreach ($professions as $prof) {
    foreach ($tiers as $tier) {
        $plans[] = [
            'id'           => $prof['role_type'] . '-' . $tier['tier'],
            'role_type'    => $prof['role_type'],
            'emoji'        => $prof['emoji'],
            'label'        => $prof['label'],
            'tier'         => $tier['tier'],
            'name'         => $prof['emoji'] . ' ' . $prof['label'] . ' — ' . $tier['name_suffix'],
            'price'        => $tier['price'],
            'billing_cycle'=> 'monthly',
            'free_months'  => 3,
            'features'     => $tier['features'],
        ];
    }
}

sendSuccess(['plans' => $plans]);
