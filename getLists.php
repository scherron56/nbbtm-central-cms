<?php


// $db is provided by db.php; do not overwrite it here.
require_once 'config/db.php';

 /* Fetch all phone types.
 */
$phonestmt= $db->query("SELECT phone_type_id, phone_type_desc FROM phone_type ORDER by phone_type_desc");
$phonetype = $phonestmt->fetch_all(MYSQLI_ASSOC);

// Fetch Titles

$titlestmt=$db->query("SELECT title_id, titleabr FROM title ORDER BY titleabr");
 $titles = $titlestmt->fetch_all(MYSQLI_ASSOC);

 //fetch Marital status
 $maritstmt=$db->query("SELECT marital_status_id, marital_status FROM marital_status ORDER BY marital_status");
 $marital = $maritstmt->fetch_all(MYSQLI_ASSOC);
/**
 * Fetch all contacts.
 */
$constmnt=$db->query("SELECT contact_id, CONCAT(last_name, ', ', first_name ) as fullname FROM contacts ORDER BY last_name, first_name");
$contacts = $constmnt->fetch_all(MYSQLI_ASSOC);

// fetch all ministries
$minStmt= $db->query("SELECT group_id, group_name FROM groups ORDER BY group_name");
$ministries = $minStmt->fetch_all(MYSQLI_ASSOC);   

$response = [ 
    "phonetype"  => $phonetype,
"titles"  => $titles,
"marital" => $marital,
"contacts" => $contacts,
"ministries" => $ministries
];

header('Content-Type: application/json');
echo json_encode($response);
exit;

?>