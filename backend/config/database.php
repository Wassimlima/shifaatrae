<?php
define('DB_SOCKET',   '/home/runner/mysql-run/mysql.sock');
define('DB_NAME',     'shifaa_dizad');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');

function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = 'mysql:unix_socket=' . DB_SOCKET . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function getDB(): mysqli {
    $db = new mysqli('localhost', DB_USER, DB_PASS, DB_NAME, port: 3306, socket: DB_SOCKET);
    if ($db->connect_error) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    $db->set_charset(DB_CHARSET);
    return $db;
}
