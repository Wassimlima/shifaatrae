<?php
function roleTypeToUserRole(string $roleType): string {
    $map = [
        'pharmacy'         => 'pharmacist',
        'pharmacist'       => 'pharmacist',
        'lab'              => 'lab',
        'med_rep'          => 'med_rep',
        'medical_services' => 'medical_services',
        'patient'          => 'patient',
    ];
    return $map[$roleType] ?? 'patient';
}

function getRoleRedirect(string $role): string {
    $map = [
        'admin'            => '/pages/admin/dashboard.html',
        'pharmacist'       => '/pages/professional/pharmacy-dashboard.html',
        'med_rep'          => '/pages/professional/medrep-dashboard.html',
        'lab'              => '/pages/professional/laboratory-dashboard.html',
        'medical_services' => '/pages/professional/medical-services-dashboard.html',
        'patient'          => '/index.html',
    ];
    return $map[$role] ?? '/index.html';
}
