<?php
/**
 * PHP built-in server router.
 * Run as: php -S 0.0.0.0:5000 -t frontend/ router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (strpos($uri, '/shifaa_dizad') === 0) {
    $uri = substr($uri, strlen('/shifaa_dizad'));
    if ($uri === '' || $uri === false) $uri = '/';
}

$apiAliases = [
    '/api/pharmacies'              => __DIR__ . '/backend/api/pharmacies/list.php',
    '/api/pharmacies/detail'       => __DIR__ . '/backend/api/pharmacies/detail.php',
    '/api/labs'                    => __DIR__ . '/backend/api/labs/list.php',
    '/api/labs/detail'             => __DIR__ . '/backend/api/labs/detail.php',
    '/api/labs/analyses'           => __DIR__ . '/backend/api/labs/detail.php',
    '/api/equipment'               => __DIR__ . '/backend/api/equipment/index.php',
    '/api/equipment/list'          => __DIR__ . '/backend/api/equipment/list.php',
    '/api/equipment/supplier'      => __DIR__ . '/backend/api/equipment/supplier.php',
    '/api/equipment/my'            => __DIR__ . '/backend/api/equipment/my-equipment.php',
    '/api/medical-services'        => __DIR__ . '/backend/api/medical-services/index.php',
    '/api/medical-services/list'   => __DIR__ . '/backend/api/medical-services/list.php',
];

$executePhp = function (string $real): bool {
    if (is_file($real)) {
        chdir(dirname($real));
        require $real;
        return true;
    }
    return false;
};

if (isset($apiAliases[$uri])) {
    if ($executePhp($apiAliases[$uri])) {
        return true;
    }
}

if (strpos($uri, '/api/') === 0) {
    $real = __DIR__ . '/backend' . $uri;
    if (is_file($real)) {
        if (str_ends_with(strtolower($real), '.php')) {
            return $executePhp($real);
        }
        $mime = mime_content_type($real) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        readfile($real);
        return true;
    }

    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not found: ' . $uri], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return true;
}

if (strpos($uri, '/backend/') === 0) {
    $real = __DIR__ . $uri;

    if (is_file($real)) {
        if (str_ends_with(strtolower($real), '.php')) {
            return $executePhp($real);
        }
        $mime = mime_content_type($real) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        readfile($real);
        return true;
    }

    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not found: ' . $uri], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return true;
}

return false;
