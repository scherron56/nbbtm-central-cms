<?php

header('Content-Type: application/json');
// $db is provided by db.php; do not overwrite it here.
require_once 'config/db.php';

 /* Fetch all phone types.
 */
$phonestmt= $db->query("SELECT phone_type_id, phone_type_desc FROM phone_type");
$phonetype = $phonestmt->fetch_all(MYSQLI_ASSOC);

// Fetch Titles

$titlestmt=$db->query("SELECT title_id, titleabr FROM title");
 $titles = $titlestmt->fetch_all(MYSQLI_ASSOC);

/**
 * Fetch all contacts.
 */
$constmnt=$db->query("SELECT contact_id, CONCAT(last_name, ', ', first_name, COALESCE(('  ' + middle_name),' ') ) as fullname FROM contacts");
$contacts = $constmnt->fetch_all(MYSQLI_ASSOC);

$response = [ 
    "phonetype"  => $phonetype,
"titles"  => $titles,
"contacts" => $contacts
];

echo json_encode($response);
exit;

?>