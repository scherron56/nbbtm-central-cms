<?php
// $db is provided by db.php; do not overwrite it here.
require_once __DIR__ . '/config/db.php';

    $contact_id=intval($_GET['contact_id']);
    // return the first contact (adjust query if you need a specific contact_id)
    $sql = 'SELECT contact_id, title_id, first_name, middle_name, last_name, address_1, city, state, zipcode FROM contacts WHERE contact_id=$contact_id';
    $result = $db->query($sql);

    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(array('error'=> '
        '));
    }
    $db->close();
    
