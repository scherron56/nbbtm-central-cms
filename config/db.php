<?php
define('HOST', 'localhost');
define('DB_NAME', 'nbbtm_central');
define('USER', 'centadmin'); // Change to 'root' if your local DB uses root without passwords
define('PASSWORD', 'cuhqsUsj80');

$host = HOST;
$port = 3306;
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