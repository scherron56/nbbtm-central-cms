<?php
require_once __DIR__ . '/env.php';

define('HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', (int) ($_ENV['DB_PORT'] ?? 3306));
define('DB_NAME', $_ENV['DB_NAME'] ?? 'nbbtm_central');
define('USER', trim((string) ($_ENV['DB_USER'] ?? '')) ?: 'admincentral');
define('PASSWORD', $_ENV['DB_PASSWORD'] ?? '');

$host = HOST;
$port = DB_PORT;
$dbname = DB_NAME;
$user = USER;
$pswd = PASSWORD;
$charset = 'utf8mb4';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 1. Initialize MySQLi instance first
    $db = mysqli_init();

    // 2. Set options BEFORE connecting
    $db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

    // 3. Establish the database connection
    $db->real_connect($host, $user, $pswd, $dbname, $port);

    // 4. Set character set
    $db->set_charset($charset);

} catch (mysqli_sql_exception $e) {
    // Return clean JSON instead of crashing with raw HTML/PHP traces
    http_response_code(500); // <-- ADD THIS LINE
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}


// Clean up unused configuration variables
unset($host, $dbname, $user, $pswd, $charset, $port);