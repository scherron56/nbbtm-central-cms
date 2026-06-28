<?php
define('HOST', '172.24.90.189');
define('DB_NAME', 'nbbtm_central');
define('USER', 'nbadmin');
define('PASSWORD', 'dAfvod4');
$host = '172.24.90.189';
$port = 3306;
$dbname = 'nbbtm_central';
$user = 'nbadmin';
$pswd = 'dAfvod4';
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