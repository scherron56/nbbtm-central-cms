<?php
// $db is provided by db.php; do not overwrite it here.
require_once 'config/db.php';
$response = [];
$query="";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // FIX: Look for 'contactid' matching your JS data key
    if (!empty($_POST["contactid"])) {

        $contactid = (int)$_POST["contactid"];

        $query = "SELECT contact_id, title_id, last_name, first_name, middle_name, date_of_birth, gender, address_1, city, state, zipcode, phone_1, phone_1_type, phone_2, phone_2_type, emergency_contact, phone_3, phone_3_type, c_email, is_member, is_baptized, anniv_date, marital_status, join_date, baptized_date, is_child, is_head, is_active FROM contacts WHERE contact_id = ?";
        
        if ($stmt = mysqli_prepare($db, $query)) {
            mysqli_stmt_bind_param($stmt, "i", $contactid);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            // FIX: Fetch the actual row data as an associative array
            if ($row = mysqli_fetch_assoc($result)) {
                $response = $row;
            } else {
                $response = ['error' => 'Contact not found'];
            }
            mysqli_stmt_close($stmt);
        } else {
            $response = ['error' => 'Database query failed'];
        }
    } else {
        $response = ['error' => 'No contact ID provided'];
    }
} else {
    $response = ['error' => 'Invalid request method'];
}

 header('Content-Type: application/json');
echo json_encode($response);     

if (isset($db) && $db instanceof mysqli) {
    
    mysqli_close($db);
    
}