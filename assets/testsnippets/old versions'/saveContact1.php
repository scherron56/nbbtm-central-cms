<?php
//saveContacts5.php
// $db is provided by db.php; do not overwrite it here.
require_once 'config/db.php';
header('Content-Type: application/json; charset=utf-8');

$errors = [];

// Helper function to turn empty strings/zeros into SQL NULL
function nullify($val, $isInt = false) {
    if ($val === '' || $val === null) return null;
    if ($isInt && intval($val) === 0) return null;
    return $isInt ? intval($val) : trim($val);
}

// Sanitize & Normalizing POST Parameters
$contact_id        = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
$title_id          = nullify($_POST['title_id'] ?? null, true);
$first_name        = trim($_POST['first_name'] ?? '');
$middle_name       = nullify($_POST['middle_name'] ?? null);
$last_name         = trim($_POST['last_name'] ?? '');
$date_of_birth     = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
$gender            = nullify($_POST['gender'] ?? null);
$address_1         = nullify($_POST['address_1'] ?? null);
$city              = nullify($_POST['city'] ?? null);
$state             = nullify($_POST['state'] ?? null);
$zipcode           = nullify($_POST['zipcode'] ?? null);
$phone_1           = nullify($_POST['phone_1'] ?? null);
$phone_1_type      = nullify($_POST['phone_1_type'] ?? null, true);
$phone_2           = nullify($_POST['phone_2'] ?? null);
$phone_2_type      = nullify($_POST['phone_2_type'] ?? null, true);
$emergency_contact = nullify($_POST['emergency_contact'] ?? null);
$phone_3           = nullify($_POST['phone_3'] ?? null);
$phone_3_type      = nullify($_POST['phone_3_type'] ?? null, true);
$c_email           = nullify($_POST['c_email'] ?? null);
$anniv_date        = !empty($_POST['anniv_date']) ? $_POST['anniv_date'] : null;
$marital_status    = nullify($_POST['marital_status'] ?? null);

// Determine Member status
$is_member = (!empty($_POST['is_member']) && $_POST['is_member'] != '0') ? 1 : 0;

if ($is_member === 1) {
    $is_baptized   = (!empty($_POST['is_baptized']) && $_POST['is_baptized'] != '0') ? 1 : 0;
    $baptized_date = !empty($_POST['baptized_date']) ? $_POST['baptized_date'] : null;
    $join_date     = !empty($_POST['join_date']) ? $_POST['join_date'] : null;
    $is_active     = (!empty($_POST['is_active']) && $_POST['is_active'] != '0') ? 1 : 0;
} else {
    $is_baptized   = 0;
    $baptized_date = null;
    $join_date     = null;
    $is_active     = 0;
}

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

    // Always clear existing alliances to rebuild cleanly
    $delSql = "DELETE FROM member_alliance WHERE contact_id = ?";
    $delStmt = $db->prepare($delSql);
    $delStmt->bind_param("i", $target_id);
    $delStmt->execute();
    $delStmt->close();

    // Re-link ministry alliances ONLY IF Member is checked
    if ($is_member === 1 && !empty($ministries)) {
        $insAllSql = "INSERT INTO member_alliance (contact_id, min_comm_id, role_id, is_active) VALUES (?, ?, ?, 1)";
        $allStmt = $db->prepare($insAllSql);

        foreach ($ministries as $min_comm_id) {
            $min_comm_id_int = intval($min_comm_id);
            $role_id_raw     = isset($roles[$min_comm_id_int]) ? trim($roles[$min_comm_id_int]) : '';
            $role_id_value   = ($role_id_raw !== '') ? intval($role_id_raw) : null;

            if ($min_comm_id_int > 0) {
                $allStmt->bind_param("iii", $target_id, $min_comm_id_int, $role_id_value);
                $allStmt->execute();
            }
        }
        $allStmt->close();
    }

    $db->commit();

    http_response_code(200);
    echo json_encode([
        "status"  => "success",
        "message" => ($contact_id > 0) ? "Record updated successfully." : "Record saved successfully.",
        "id"      => $target_id
    ]);

} catch (Exception $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database processing failure.", "error" => $e->getMessage()]);
}
exit;