<?php
// $db is provided by db.php; do not overwrite it here.
require_once 'config/db.php';
header('Content-Type: application/json; charset=utf-8');

// CRITICAL FIX: Always initialize the errors array, even if validation is commented out
$errors = [];

// 1. Map and clean inputs
$title_id       = isset($_POST['title_id']) ? intval($_POST['title_id']) : 0;
$first_name     = $_POST['first_name'] ?? '';
$last_name      = $_POST['last_name'] ?? '';
$middle_name    = $_POST['middle_name'] ?? ''; 
$date_of_birth  = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
$gender         = $_POST['gender'] ?? '';      
$address_1      = $_POST['address_1'] ?? '';
$city           = $_POST['city'] ?? '';
$state          = $_POST['state'] ?? '';
$zipcode        = $_POST['zipcode'] ?? '';
$phone_1        = $_POST['phone_1'] ?? '';
$phone_1_type   = isset($_POST['phone_1_type']) ? intval($_POST['phone_1_type']) : 0;
$phone_2        = $_POST['phone_2'] ?? '';
$phone_2_type   = isset($_POST['phone_2_type']) ? intval($_POST['phone_2_type']) : 0;
$phone_3        = $_POST['phone_3'] ?? '';
$phone_3_type   = isset($_POST['phone_3_type']) ? intval($_POST['phone_3_type']) : 0;
$anniv_date     = !empty($_POST['anniv_date']) ? $_POST['anniv_date'] : null;
$marital_status = isset($_POST['marital_status']) ? intval($_POST['marital_status']) : 0;
$join_date      = !empty($_POST['join_date']) ? $_POST['join_date'] : null;
$baptized_date  = !empty($_POST['baptized_date']) ? $_POST['baptized_date'] : null;

$c_email        = $_POST['c_email'] ?? '';
$is_member      = isset($_POST['is_member']) ? 1 : 0;
$is_baptized    = isset($_POST['is_baptized']) ? 1 : 0;
$is_child       = isset($_POST['is_child']) ? 1 : 0;
$is_head        = isset($_POST['is_head']) ? 1 : 0;
$is_active      = isset($_POST['is_active']) ? 1 : 0;
$contact_id     = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;

error_log(print_r($first_name, true));
// Helper function to robustly sanitize ambiguous date strings
function sanitizeToSqlDate($dateStr) {
    if (!$dateStr) return null;
    // Replace slashes with dashes to ensure standard interpretation, or use specialized parser
    $timestamp = strtotime(str_replace('/', '-', $dateStr));
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

$date_of_birth = sanitizeToSqlDate($date_of_birth);
$anniv_date    = sanitizeToSqlDate($anniv_date);
$join_date     = sanitizeToSqlDate($join_date);
$baptized_date = sanitizeToSqlDate($baptized_date);

// Ensure we have a valid contact ID to update
if ($contact_id <= 0) {
    $errors['contact_id'] = 'Valid Contact ID is required for updates.';
}

if (empty($errors)) {
    $sql = "UPDATE contacts SET 
                title_id = ?, first_name = ?, last_name = ?, middle_name = ?, 
                date_of_birth = ?, gender = ?, address_1 = ?, city = ?, state = ?, 
                zipcode = ?, phone_1 = ?, phone_1_type = ?, phone_2 = ?, phone_2_type = ?, 
                phone_3 = ?, phone_3_type = ?, c_email = ?, is_member = ?, is_baptized = ?, 
                marital_status = ?, anniv_date = ?, join_date = ?, baptized_date = ?, is_child = ?, is_head = ?, is_active = ?
            WHERE contact_id = ?";

    if ($stmt = $db->prepare($sql)) {            
        
        // CRITICAL FIX: Type mapping string precisely fixed to 27 parameters:
        // title_id (i), names/address variables (ssssssssss), phone_1 (s), phone_1_type (i), 
        // phone_2 (s), phone_2_type (i), phone_3 (s), phone_3_type (i), email (s), 
        // member/baptized/marital integers (iii), dates (sss), child/head/active flags (iii), contact_id (i)
        $types = "issssssssssisisisisisssiiii";
        
        $stmt->bind_param(
            $types, 
            $title_id, $first_name, $last_name, $middle_name,
            $date_of_birth, $gender, $address_1, $city, $state,
            $zipcode, $phone_1, $phone_1_type, $phone_2, $phone_2_type,
            $phone_3, $phone_3_type, $c_email, $is_member, $is_baptized,
            $marital_status, $anniv_date, $join_date, $baptized_date, $is_child, $is_head, $is_active, 
            $contact_id
        );
       
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode([
                "status"  => "success",
                "message" => "Contact updated successfully.",
                "id"      => $contact_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                "status"  => "error",
                "message" => "Database execution failed.",
                "error"   => $stmt->error
            ]);
        }
        $stmt->close();
        
    } else {
        http_response_code(500);
        echo json_encode([
            "status"  => "error",
            "message" => "SQL statement preparation failed.",
            "error"   => $db->error
        ]);
    }
   
} else {    
    http_response_code(400);
    echo json_encode([
        "status" => "validation_error",
        "errors" => $errors
    ]);
}

$db->close();
exit; // Terminate script to ensure clean AJAX transmission
?>
