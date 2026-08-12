<?php
// saveContact.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once 'config/db.php';
require_once 'include/auth.php';

// Fallback if db.php defines $conn instead of $db
if (!isset($db) && isset($conn)) {
    $db = $conn;
}

// Enforce admin privileges: Non-admin users are blocked from saving changes
requireAdmin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
    exit;
}

// Helper to sanitize phone input numbers down to raw digits
function sanitizePhoneNumber($val) {
    if (empty($val)) return null;
    $digits = preg_replace('/\D/', '', (string)$val);
    return !empty($digits) ? $digits : null;
}

// Helper to strictly format/validate date values to YYYY-MM-DD or NULL
function sanitizeDate($val) {
    if (empty($val)) return null;
    $trimmed = trim((string)$val);

    if ($trimmed === '' || $trimmed === '0000-00-00' || $trimmed === '0000') {
        return null;
    }
    
    // Valid YYYY-MM-DD format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
        list($y, $m, $d) = explode('-', $trimmed);
        if (checkdate((int)$m, (int)$d, (int)$y)) {
            return $trimmed;
        }
    }
    
    // If only a 4-digit year was supplied (e.g. "1969"), default to "1969-01-01"
    if (preg_match('/^\d{4}$/', $trimmed)) {
        $year = intval($trimmed);
        if ($year >= 1000 && $year <= 9999) {
            return $trimmed . '-01-01';
        }
    }

    // Attempt to convert non-standard date strings
    $timestamp = strtotime($trimmed);
    if ($timestamp !== false) {
        $formatted = date('Y-m-d', $timestamp);
        if ($formatted !== '1970-01-01' || $trimmed === '1970-01-01') {
            return $formatted;
        }
    }

    return null;
}

// 1. Collect & Sanitize Form Input Data
$contact_id     = !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null;
$title_id       = !empty($_POST['title_id']) ? intval($_POST['title_id']) : null;
$first_name     = trim($_POST['first_name'] ?? '');
$middle_name    = trim($_POST['middle_name'] ?? '');
$last_name      = trim($_POST['last_name'] ?? '');

// Sanitize all Date Inputs strictly to YYYY-MM-DD or NULL
$date_of_birth  = sanitizeDate($_POST['date_of_birth'] ?? null);
$anniv_date     = sanitizeDate($_POST['anniv_date'] ?? null);
$join_date      = sanitizeDate($_POST['join_date'] ?? null);
$baptized_date  = sanitizeDate($_POST['baptized_date'] ?? null);

$gender         = !empty($_POST['gender']) ? $_POST['gender'] : null;
$marital_status = !empty($_POST['marital_status']) ? intval($_POST['marital_status']) : null;

$is_member   = isset($_POST['is_member']) ? intval($_POST['is_member']) : 0;
$is_head     = isset($_POST['is_head']) ? intval($_POST['is_head']) : 0;
$is_child    = isset($_POST['is_child']) ? intval($_POST['is_child']) : 0;
$is_baptized = isset($_POST['is_baptized']) ? intval($_POST['is_baptized']) : 0;
$is_active   = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;

$family_id  = !empty($_POST['family_id']) ? intval($_POST['family_id']) : null;

$address_1  = trim($_POST['address_1'] ?? '');
$city       = trim($_POST['city'] ?? '');
$state      = trim($_POST['state'] ?? '');
$zipcode    = trim($_POST['zipcode'] ?? '');

$phone_1      = sanitizePhoneNumber($_POST['phone_1'] ?? '');
$phone_1_type = !empty($_POST['phone_1_type']) ? intval($_POST['phone_1_type']) : null;
$phone_2      = sanitizePhoneNumber($_POST['phone_2'] ?? '');
$phone_2_type = !empty($_POST['phone_2_type']) ? intval($_POST['phone_2_type']) : null;

$emergency_contact = trim($_POST['emergency_contact'] ?? '');
$phone_3      = sanitizePhoneNumber($_POST['phone_3'] ?? '');
$phone_3_type = !empty($_POST['phone_3_type']) ? intval($_POST['phone_3_type']) : null;

$c_email       = trim($_POST['c_email'] ?? '');

// Validation
$errors = [];
if (empty($first_name)) { $errors['first_name'] = "First name is required."; }
if (empty($last_name))  { $errors['last_name']  = "Last name is required."; }

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(["status" => "error", "errors" => $errors]);
    exit;
}

$db->begin_transaction();

try {
    // 2. Insert or Update Contacts Table
    if ($contact_id && $contact_id > 0) {
        $stmt = $db->prepare("UPDATE contacts SET 
            title_id = ?, first_name = ?, middle_name = ?, last_name = ?, date_of_birth = ?, 
            gender = ?, address_1 = ?, city = ?, state = ?, zipcode = ?, 
            phone_1 = ?, phone_1_type = ?, phone_2 = ?, phone_2_type = ?, 
            emergency_contact = ?, phone_3 = ?, phone_3_type = ?, c_email = ?, 
            is_member = ?, is_baptized = ?, anniv_date = ?, marital_status = ?, 
            join_date = ?, baptized_date = ?, is_child = ?, is_head = ?, is_active = ?
            WHERE contact_id = ?");

        // 28 parameters -> 28 types: issssssssssisisisssssssiiiii
        $stmt->bind_param("issssssssssisisisssssssiiiii", 
            $title_id, $first_name, $middle_name, $last_name, $date_of_birth,
            $gender, $address_1, $city, $state, $zipcode,
            $phone_1, $phone_1_type, $phone_2, $phone_2_type,
            $emergency_contact, $phone_3, $phone_3_type, $c_email,
            $is_member, $is_baptized, $anniv_date, $marital_status,
            $join_date, $baptized_date, $is_child, $is_head, $is_active,
            $contact_id
        );
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $db->prepare("INSERT INTO contacts (
            title_id, first_name, middle_name, last_name, date_of_birth, 
            gender, address_1, city, state, zipcode, 
            phone_1, phone_1_type, phone_2, phone_2_type, 
            emergency_contact, phone_3, phone_3_type, c_email, 
            is_member, is_baptized, anniv_date, marital_status, 
            join_date, baptized_date, is_child, is_head, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // 27 parameters -> 27 types: issssssssssisisisssssssiiii
        $stmt->bind_param("issssssssssisisisssssssiiii", 
            $title_id, $first_name, $middle_name, $last_name, $date_of_birth,
            $gender, $address_1, $city, $state, $zipcode,
            $phone_1, $phone_1_type, $phone_2, $phone_2_type,
            $emergency_contact, $phone_3, $phone_3_type, $c_email,
            $is_member, $is_baptized, $anniv_date, $marital_status,
            $join_date, $baptized_date, $is_child, $is_head, $is_active
        );
        $stmt->execute();
        $contact_id = $stmt->insert_id;
        $stmt->close();
    }

    // 3. Update Families Mapping
    $delFam = $db->prepare("DELETE FROM Families WHERE contact_id = ?");
    $delFam->bind_param("i", $contact_id);
    $delFam->execute();
    $delFam->close();

    $assigned_family = ($is_head === 1) ? $contact_id : $family_id;
    if (!empty($assigned_family)) {
        $insFam = $db->prepare("INSERT INTO Families (contact_id, family_id) VALUES (?, ?)");
        $insFam->bind_param("ii", $contact_id, $assigned_family);
        $insFam->execute();
        $insFam->close();
    }

    // 4. Update Ministry Alliances (`member_alliance`)
    $delMin = $db->prepare("DELETE FROM member_alliance WHERE contact_id = ?");
    $delMin->bind_param("i", $contact_id);
    $delMin->execute();
    $delMin->close();

    if ($is_member === 1 && !empty($_POST['ministries']) && is_array($_POST['ministries'])) {
        $insMin = $db->prepare("INSERT INTO member_alliance (contact_id, min_comm_id, role_id, is_active) VALUES (?, ?, ?, 1)");
        
        foreach ($_POST['ministries'] as $min_comm_id) {
            $min_comm_id = intval($min_comm_id);
            $role_id = !empty($_POST['roles'][$min_comm_id]) ? intval($_POST['roles'][$min_comm_id]) : null;

            $insMin->bind_param("iii", $contact_id, $min_comm_id, $role_id);
            $insMin->execute();
        }
        $insMin->close();
    }

    $db->commit();

    echo json_encode([
        "status" => "success", 
        "message" => "Contact saved successfully.",
        "id" => $contact_id
    ]);

} catch (Exception $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Save error: " . $e->getMessage()]);
}

if (isset($db) && $db instanceof mysqli) {
    mysqli_close($db);
}
?>