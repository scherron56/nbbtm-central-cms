<?php
define('HOST', 'localhost');
define('DB_NAME', 'nbbtm_central');
define('USER', 'nbadmin');
define('PASSWORD', 'w3C@n');
$host = 'localhost';
$port = 3306;
$dbname = 'nbbtm_central';
$user = 'root';
$pswd = 'w3C@n';
$charset = 'utf8mb4';

mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db = new mysqli($host, $user, $pswd, $dbname, $port);
// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
 } ;
//  else {
//     echo "Successfully connected ! . <br><br>";
//     echo "Server is . $host . and user is . $user  ";
// };
$db->set_charset($charset);
$db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);

// unset connect info.  not needednow
unset($host, $dbname, $user, $pswd, $charset, $port);