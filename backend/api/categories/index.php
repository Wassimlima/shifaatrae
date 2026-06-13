<?php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$db = getDB();

$cats = [
    ['id'=>1, 'slug'=>'medicine',       'name'=>'Médicaments',         'name_ar'=>'الأدوية',             'icon'=>'💊'],
    ['id'=>2, 'slug'=>'device',         'name'=>'Appareils médicaux',  'name_ar'=>'أجهزة طبية',          'icon'=>'🩺'],
    ['id'=>3, 'slug'=>'special_needs',  'name'=>'Besoins spéciaux',    'name_ar'=>'احتياجات خاصة',       'icon'=>'♿'],
    ['id'=>4, 'slug'=>'parapharmacy',   'name'=>'Parapharmacie',       'name_ar'=>'باراصيدلانية',        'icon'=>'🧴'],
];

$db->close();
sendJSON($cats);
