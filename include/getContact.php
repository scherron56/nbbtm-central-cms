<?php
// $db is provided by db.php; do not overwrite it here.
require_once 'config/db.php';
     $contact_id=intval($_GET['contact_id']);
    // return the first contact (adjust query if you need a specific contact_id)
    $sql = 'SELECT contact_id, title_id, last_name, first_name, middle_name, date_of_birth, gender, address_1, city, state, zipcode, phone_1, phone_1_type, phone_2, phone_2_type, phone_3, phone_3_type, c_email, is_member, is_baptized, anniv_date, marital_status, join_date, baptized_date, is_child, is_active  FROM contacts WHERE contact_id=$contact_id';
    $result = $db->query($sql);

    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    } else {
        echo json_encode(array('error'=> '
        '));
    }
    $db->close();
    
