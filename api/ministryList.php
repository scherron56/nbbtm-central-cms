<?php


// $db is provided by db.php; do not overwrite it here.
require_once 'config/db.php';

 /* Fetch all phone types.
 */
$minStmt= $db->query("SELECT group_id group_name FROM groups ORDER BY group_name");
$ministryList = $minStmt->fetch_all(MYSQLI_ASSOC);

$roleStmt= $db->query("SELECT role_id role_name FROM roles ORDER BY role_name");
$roleList = $roleStmt->fetch_all(MYSQLI_ASSOC);

$response = [ 
    "ministryList"  => $ministryList,
"roleList" => $roleList
];
header('Content-Type: application/json');
echo json_encode($response);
exit;

?>