<?php
// $db is provided by db.php; do not overwrite it here.
require_once 'config/db.php';

if (isset($_POST['cont-form'])) {
    $contact_id = intval($_POST['contact_id']);
    $title_id = $db->real_escape_string($_POST['title_id']);
    $first_name = $db->real_escape_string($_POST['first_name']);
    $last_name = $db->real_escape_string($_POST['last_name']);
    $middle_name = $db->real_escape_string($_POST['middle_name ']);
    $date_of_birth = $db->real_escape_string($_POST['date_of_birth']);
    $gender = $db->real_escape_string($_POST['gender ']);
    $address_1 = $db->real_escape_string($_POST['address_1']);
    $city = $db->real_escape_string($_POST['city']);
    $state = $db->real_escape_string($_POST['state']);
    $zipcode = $db->real_escape_string($_POST['zipcode']);
    $phone_1 = $db->real_escape_string($_POST['phone_1']);
    $phone_1_type = $db->real_escape_string($_POST['phone_1_type']);
    $phone_2 = $db->real_escape_string($_POST['phone_2']);
    $phone_2_type = $db->real_escape_string($_POST['phone_2_type']);
    $phone_3 = $db->real_escape_string($_POST['phone_3']);
    $phone_3_type = $db->real_escape_string($_POST['phone_3_type']);
    $c_email = $db->real_escape_string($_POST['c_email']);
    $is_member = $db->real_escape_string($_POST['is_member']);
    $is_baptized = $db->real_escape_string($_POST['is_baptized']);
    $marital_status = $db->real_escape_string($_POST['marital_status']);
    $join_date = $db->real_escape_string($_POST['join_date']);
    $baptized_date = $db->real_escape_string($_POST['baptized_date']);
    $is_child = $db->real_escape_string($_POST['is_child']);
    $is_active = $db->real_escape_string($_POST['is_active']);
    if ($contact_id > 0) {
        $query = "INSERT INTO contacts (contact_id, title_id, last_name, first_name, middle_name, date_of_birth, gender, address_1, city, state, zipcode, phone_1, phone_1_type, phone_2, phone_2_type, phone_3, phone_3_type, c_email, is_member, is_baptized, ,marital_status, join_date, baptized_date, is_child, is_actve) VALUES ('$contact_id', '$title_id', '$last_name', '$first_name', '$middle_name', '$date_of_birth', '$gender', '$address_1', '$city', '$state', '$zipcode', '$phone_1', '$phone_1_type', '$phone_2', '$phone_2_type', '$phone_3', '$phone_3_type', '$c_email', '$is_member', '$is_baptized', ,'$marital_status', '$join_date', '$baptized_date', '$is_child', '$is_actve')";
        if($db->query($query)) {
                echo "Contact added successfully";
        } else {
            echo "Error:" . $db->error;
        }
     } else{
        // updating existing contact
        $query = "UPDATE contacts SET title_id='$title_id', last_name='$last_name', first_name='$first_name', middle_name='$middle_name', date_of_birth='$date_of_birth', gender='$gender', address_1='$address_1', city='$city', state='$state', zipcode='$zipcode', phone_1='$phone_1', phone_1_type='$phone_1_type', phone_2='$phone_2', phone_2_type='$phone_2_type', phone_3='$phone_3', phone_3_type='$phone_3_type', c_email='$c_email', is_member='$is_member', is_baptized='$is_baptized', ,marital_status='$marital_status', join_date='$join_date', baptized_date='$baptized_date', is_child='$is_child' is_actve='$is_actve' WHERE contact_id=$contact_id";
        if($db->query($query)) {
            echo "Contact updated successfully";
        } else {
                echo "Error:" . $db->error;
        }
     }
}
$db->close();
?>   