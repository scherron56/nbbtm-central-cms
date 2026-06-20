<?php
header('Content-Type: application/json')
// $db is provided by db.php; do not overwrite it here.
require_once __DIR__ . '/config/db.php';

try{
 * Fetch all phone types.
 */
$phonestmt= $db->query("SELECT phone_type_id, phone_type_desc FROM phone_type");
$phonetypes = $phonestmt->fetch_all(MYSQLI_ASSOC);

// Fetch Titles

$titlestmt=$db->query("SELECT title_id, titleabr FROM title")
 $titles = $titlestmt->fetch_all(MYSQLI_ASSOC);

/**
 * Fetch all contacts.
 */
$constmnt=$db->("SELECT contact_id, CONCAT(last_name, ', ', first_name, ' ', middle_name) as fullname FROM contacts")
$contacts = $constmnt->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'phonetypes'=> $phonetypes,
    'titles'=> $titles,
    'contacts'=> $contacts
]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
