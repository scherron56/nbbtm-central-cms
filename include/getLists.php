<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); 

header('Content-Type: application/json');
require_once 'config/db.php';

try {
    $response = [
        'status' => 'success',
        'contacts' => [],
        'titles' => []
    ];

    // 1. Fetch Contacts
    $sqlContacts = "SELECT contact_id, 
            CONCAT(last_name, ', ', first_name, IF(middle_name IS NOT NULL AND middle_name != '', CONCAT(' ', middle_name), '')) as fullname 
            FROM contacts";
    $constmnt = $db->query($sqlContacts);
    if (!$constmnt) {
        throw new Exception("Contacts Query Failed: " . $db->error);
    }
    $response['contacts'] = $constmnt->fetch_all(MYSQLI_ASSOC);

    // 2. Fetch Titles (Uncommented and operational)
    $sqlTitles = "SELECT title_id, titleabr FROM title";
    $titlestmt = $db->query($sqlTitles);
    if (!$titlestmt) {
        throw new Exception("Titles Query Failed: " . $db->error);
    }
    $response['titles'] = $titlestmt->fetch_all(MYSQLI_ASSOC);

    // Output unified object
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}
?>

