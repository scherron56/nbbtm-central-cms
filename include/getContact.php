<?php
// $db is provided by db.php; do not overwrite it here.
require_once '../config/db.php';
$data = [];
    
if ($_SERVER["REQUEST_METHOD"] == "POST"){

    if (!empty($_POST["contacts"])){

    $contact_id = (int)$_POST["contacts"];

    echo "Successfully retrieved contact ID:  " . $contact_id;

    $stmt=mysqli_prepare($db,"SELECT contact_id, title_id, last_name, first_name, middle_name, date_of_birth, gender, address_1, city, state, zipcode, phone_1, phone_1_type, phone_2, phone_2_type, phone_3, phone_3_type, c_email, is_member, is_baptized, anniv_date, marital_status, join_date, baptized_date, is_child, is_active  FROM contacts WHERE contact_id = ?");
    mysqli_stmt_bind_param($stmt,"i", $contact_id);
    mysqli_stmt_execute($stmt);
    $data = mysqli_stmt_get_result($stmt);
}    
else{

echo "Please select a contact or enter new";

}

     echo json_encode($data);     
    // $stmt=mysqli_prepare($db,"SELECT contact_id, title_id, last_name, first_name, middle_name, date_of_birth, gender, address_1, city, state, zipcode, phone_1, phone_1_type, phone_2, phone_2_type, phone_3, phone_3_type, c_email, is_member, is_baptized, anniv_date, marital_status, join_date, baptized_date, is_child, is_active  FROM contacts WHERE contact_id = ?");
    // mysqli_stmt_bind_param($stmt,"i", $contact_id);
    // mysqli_stmt_execute($stmt);
    // $data = mysqli_stmt_get_result($stmt);

 


    $db->close();
    
}