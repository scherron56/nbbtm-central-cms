<?php
// $db is provided by db.php; do not overwrite it here.
require_once 'config/db.php';
header('Content-Type: application/json; charset=utf-8');

$errors = [];

// Sanitize incoming POST parameters safely
$contact_id        = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
$title_id          = isset($_POST['title_id']) ? intval($_POST['title_id']) : 0;
$first_name        = trim($_POST['first_name'] ?? '');
$middle_name       = trim($_POST['middle_name'] ?? '');
$last_name         = trim($_POST['last_name'] ?? '');
$date_of_birth     = $_POST['date_of_birth'] ?? null;
$gender            = $_POST['gender'] ?? '';
$address_1         = $_POST['address_1'] ?? '';
$city              = $_POST['city'] ?? '';
$state             = $_POST['state'] ?? '';
$zipcode           = $_POST['zipcode'] ?? '';
$phone_1           = $_POST['phone_1'] ?? '';
$phone_1_type      = intval($_POST['phone_1_type'] ?? 0);
$phone_2           = $_POST['phone_2'] ?? '';
$phone_2_type      = intval($_POST['phone_2_type'] ?? 0);
$emergency_contact = $_POST['emergency_contact'] ?? '';
$phone_3           = $_POST['phone_3'] ?? '';
$phone_3_type      = intval($_POST['phone_3_type'] ?? 0);
$c_email           = trim($_POST['c_email'] ?? '');
$is_member         = (!empty($_POST['is_member']) && $_POST['is_member'] != '0') ? 1 : 0;
$is_baptized       = (!empty($_POST['is_baptized']) && $_POST['is_baptized'] != '0') ? 1 : 0;
$anniv_date        = !empty($_POST['anniv_date']) ? $_POST['anniv_date'] : null;
$marital_status    = $_POST['marital_status'] ?? '';
$join_date         = !empty($_POST['join_date']) ? $_POST['join_date'] : null;
$baptized_date     = !empty($_POST['baptized_date']) ? $_POST['baptized_date'] : null;
$is_active         = (!empty($_POST['is_active']) && $_POST['is_active'] != '0') ? 1 : 0;

// Sub-arrays mapped directly from serialize() payloads
$ministries  = $_POST['ministries'] ?? []; 
$roles       = $_POST['roles'] ?? [];      

// Data Validation Routines
if (empty($first_name)) { $errors['first_name'] = 'First Name is required.'; }
if (empty($last_name))  { $errors['last_name']  = 'Last Name is required.'; }
if (!empty($c_email) && !filter_var($c_email, FILTER_VALIDATE_EMAIL)) { 
    $errors['c_email'] = 'Please enter a valid email address.'; 
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Validation tracking failure.", "errors" => $errors]);
    exit;
}

// Open Database Isolation Boundary safely to run structural updates concurrently
$db->begin_transaction();

try {
    if ($contact_id > 0) {
        // UPDATE MODE
        $sql = "UPDATE contacts SET 
                    title_id = ?, first_name = ?, middle_name = ?, last_name = ?, 
                    date_of_birth = ?, gender = ?, address_1 = ?, city = ?, state = ?, zipcode = ?, 
                    phone_1 = ?, phone_1_type = ?, phone_2 = ?, phone_2_type = ?, 
                    emergency_contact = ?, phone_3 = ?, phone_3_type = ?, c_email = ?, 
                    is_member = ?, is_baptized = ?, anniv_date = ?, marital_status = ?, 
                    join_date = ?, baptized_date = ?, is_active = ? 
                WHERE contact_id = ?";
                
        $stmt = $db->prepare($sql);
        
        // 26 parameters bound (25 columns + contact_id)
        $stmt->bind_param(
            "issssssssssisissisiissssii", 
            $title_id, $first_name, $middle_name, $last_name, 
            $date_of_birth, $gender, $address_1, $city, $state, $zipcode, 
            $phone_1, $phone_1_type, $phone_2, $phone_2_type, 
            $emergency_contact, $phone_3, $phone_3_type, $c_email, 
            $is_member, $is_baptized, $anniv_date, $marital_status, 
            $join_date, $baptized_date, $is_active, $contact_id
        );
        $stmt->execute();
        $stmt->close();
        $target_id = $contact_id;
    } else {
        // INSERT MODE
        $sql = "INSERT INTO contacts (
                    title_id, first_name, middle_name, last_name, 
                    date_of_birth, gender, address_1, city, state, zipcode, 
                    phone_1, phone_1_type, phone_2, phone_2_type, 
                    emergency_contact, phone_3, phone_3_type, c_email, 
                    is_member, is_baptized, anniv_date, marital_status, 
                    join_date, baptized_date, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
        $stmt = $db->prepare($sql);
        
        // 25 parameters bound
        $stmt->bind_param(
            "issssssssssisissisiissssi", 
            $title_id, $first_name, $middle_name, $last_name, 
            $date_of_birth, $gender, $address_1, $city, $state, $zipcode, 
            $phone_1, $phone_1_type, $phone_2, $phone_2_type, 
            $emergency_contact, $phone_3, $phone_3_type, $c_email, 
            $is_member, $is_baptized, $anniv_date, $marital_status, 
            $join_date, $baptized_date, $is_active
        );
        $stmt->execute();
        $target_id = $stmt->insert_id;
        $stmt->close();
    }

    // Synchronize the Junction Table (`member_alliance`) safely
    $delSql = "DELETE FROM member_alliance WHERE contact_id = ?";
    $delStmt = $db->prepare($delSql);
    $delStmt->bind_param("i", $target_id);
    $delStmt->execute();
    $delStmt->close();

    // Re-link selection data if member is checked
    if ($is_member === 1 && !empty($ministries)) {
        $insAllSql = "INSERT INTO member_alliance (contact_id, min_comm_id, role_id, is_active) VALUES (?, ?, ?, 1)";
        $allStmt = $db->prepare($insAllSql);

        foreach ($ministries as $min_comm_id) {
            $min_comm_id_int = intval($min_comm_id);
            
            $role_id_raw = isset($roles[$min_comm_id_int]) ? trim($roles[$min_comm_id_int]) : '';
            $role_id_value = ($role_id_raw !== '') ? intval($role_id_raw) : null;

            if ($min_comm_id_int > 0) {
                // Pass parameter using "i" for null values in integer columns
                $allStmt->bind_param("iii", $target_id, $min_comm_id_int, $role_id_value);
                $allStmt->execute();
            }
        }
        $allStmt->close();
    }

    // Persist all data changes securely
    $db->commit();

    http_response_code(200);
    echo json_encode([
        "status"  => "success",
        "message" => ($contact_id > 0) ? "Record updated successfully." : "Record saved successfully.",
        "id"      => $target_id
    ]);

} catch (Exception $e) {
    $db->rollback(); // Revert on failure to maintain database integrity
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database processing failure.", "error" => $e->getMessage()]);
}
exit;