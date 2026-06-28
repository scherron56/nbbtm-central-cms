<?php
// $db is provided by db.php; do not overwrite it here.

header('Content-Type: application/json');
require_once('config/db.php');
try {
    // Fetch all phone types.
    $phonestmt = $db->query("SELECT * FROM phone_type");
    $phonetypes = $phonestmt->fetch_all(MYSQLI_ASSOC);

    // Fetch Titles
    $titlestmt = $db->query("SELECT * FROM title");
    $titles = $titlestmt->fetch_all(MYSQLI_ASSOC);

    // Fetch all contacts.
    $contactsStmt = $db->query("SELECT contact_id, first_name, middle_name, last_name FROM contacts");
    $contacts = $contactsStmt->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        "phonetypes" => $phonetypes,
        "titles"     => $titles,
        "contacts"   => $contacts
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
