<?php
// $db is provided by db.php; do not overwrite it here.
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $contact_id = intval($_POST['contact_id']);
    $title_id = $db->real_escape_string($_POST['title_id']);
    $first_name = $db->real_escape_string($_POST['first_name']);
    $last_name = $db->real_escape_string($_POST['last_name']);
    $middle_name = $db->real_escape_string($_POST['middle_name ']);
    $address_1 = $db->real_escape_string($_POST['address_1']);
    $city = $db->real_escape_string($_POST['city']);
    $state = $db->real_escape_string($_POST['state']);
    $zipcode = $db->real_escape_string($_POST['zipcode']);

    if ($contact_id > 0) {
        $query = "INSERT INTO contacts (title_id, first_name, middle_name, last_name, address_1, city, state, zipcode) VALUES ('$title_id', '$first_name', '$middle_name', '$last_name', '$address_1', '$city', '$state', '$zipcode')";
        if($db->query($query)) {
                echo "Contact added successfully"
        } else {
            echo "Error:" . $db->error;
        }
     } else{
        // updating existing contact
        $query = "UPDATE contacts SET title_id='$title_id', first_name='$first_name', middle_name='$middle_name', last_name='$last_name', address_1='$address_1',city='$city', state ='$state' WHERE contact_id=$contact_id";
        if($db->query($query)) {
            echo "Contact updated successfully"
        } else {
                echo "Error:" . $db->error;
        }
     }
}
$db->close();
?>   