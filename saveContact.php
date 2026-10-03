<?php
// saveContact.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once 'config/db.php';
require_once 'include/auth.php';
require_once 'include/document_binary.php';

if (!isset($db) && isset($conn)) {
    $db = $conn;
}

requireEditor();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
    exit;
}

// Delete Attachment action using document_lib
if (isset($_POST['action']) && $_POST['action'] === 'delete_attachment') {
    $documentId = (int)($_POST['document_id'] ?? 0);
    $stmt = $db->prepare("DELETE FROM document_lib WHERE document_id = ? AND entity_type = 'contact'");
    $stmt->bind_param("i", $documentId);
    $stmt->execute();
    $stmt->close();
    echo json_encode(["status" => "success", "message" => "Document removed."]);
    exit;
}

function sanitizePhoneNumber($val) {
    if (empty($val)) return null;
    $digits = preg_replace('/\D/', '', (string)$val);
    return !empty($digits) ? $digits : null;
}

function sanitizeDate($val) {
    if (empty($val)) return null;
    $trimmed = trim((string)$val);

    if ($trimmed === '' || $trimmed === '0000-00-00' || $trimmed === '0000') {
        return null;
    }
    
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
        list($y, $m, $d) = explode('-', $trimmed);
        if (checkdate((int)$m, (int)$d, (int)$y)) {
            return $trimmed;
        }
    }
    
    if (preg_match('/^\d{4}$/', $trimmed)) {
        $year = intval($trimmed);
        if ($year >= 1000 && $year <= 9999) {
            return $trimmed . '-01-01';
        }
    }

    $timestamp = strtotime($trimmed);
    if ($timestamp !== false) {
        $formatted = date('Y-m-d', $timestamp);
        if ($formatted !== '1970-01-01' || $trimmed === '1970-01-01') {
            return $formatted;
        }
    }

    return null;
}

// 1. Collect & Sanitize Form Inputs
$contact_id      = !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null;
$title_id        = !empty($_POST['title_id']) ? intval($_POST['title_id']) : null;
$contact_type_id = !empty($_POST['contact_type_id']) ? intval($_POST['contact_type_id']) : null;
$first_name      = trim($_POST['first_name'] ?? '');
$middle_name     = trim($_POST['middle_name'] ?? '');
$n_sufix         = trim($_POST['n_sufix'] ?? '');
$last_name       = trim($_POST['last_name'] ?? '');

$date_of_birth   = sanitizeDate($_POST['date_of_birth'] ?? null);
$is_deceased     = isset($_POST['is_deceased']) ? intval($_POST['is_deceased']) : 0;
$date_of_death   = ($is_deceased === 1) ? sanitizeDate($_POST['date_of_death'] ?? null) : null;

$anniv_date      = sanitizeDate($_POST['anniv_date'] ?? null);
$join_date       = sanitizeDate($_POST['join_date'] ?? null);
$is_dedicated    = isset($_POST['is_dedicated']) ? intval($_POST['is_dedicated']) : 0;
$dedication_date = ($is_dedicated === 1) ? sanitizeDate($_POST['dedication_date'] ?? null) : null;
$is_baptized     = isset($_POST['is_baptized']) ? intval($_POST['is_baptized']) : 0;
$baptized_date   = ($is_baptized === 1) ? sanitizeDate($_POST['baptized_date'] ?? null) : null;

$gender         = !empty($_POST['gender']) ? $_POST['gender'] : null;
$marital_status = !empty($_POST['marital_status']) ? intval($_POST['marital_status']) : null;

$is_member   = isset($_POST['is_member']) ? intval($_POST['is_member']) : 0;
$is_head     = isset($_POST['is_head']) ? intval($_POST['is_head']) : 0;
$is_spouse   = isset($_POST['is_spouse']) ? intval($_POST['is_spouse']) : 0;
$is_child    = isset($_POST['is_child']) ? intval($_POST['is_child']) : 0;
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
        $assigned_family = ($is_head === 1) ? $contact_id : $family_id;

        $stmt = $db->prepare("UPDATE contacts SET 
            family_id = ?, title_id = ?, contact_type_id = ?, first_name = ?, middle_name = ?, n_sufix = ?, last_name = ?, date_of_birth = ?, 
            date_of_death = ?, is_deceased = ?, gender = ?, address_1 = ?, city = ?, state = ?, zipcode = ?, 
            phone_1 = ?, phone_1_type = ?, phone_2 = ?, phone_2_type = ?, 
            emergency_contact = ?, phone_3 = ?, phone_3_type = ?, c_email = ?, 
            is_member = ?, is_baptized = ?, is_dedicated = ?, dedication_date = ?, anniv_date = ?, marital_status = ?, 
            join_date = ?, baptized_date = ?, is_child = ?, is_head = ?, is_active = ?
            WHERE contact_id = ?");

        $stmt->bind_param("iiissssssissssssisissisiiississiiii", 
            $assigned_family, $title_id, $contact_type_id, $first_name, $middle_name, $n_sufix, $last_name, $date_of_birth, 
            $date_of_death, $is_deceased, $gender, $address_1, $city, $state, $zipcode, 
            $phone_1, $phone_1_type, $phone_2, $phone_2_type, $emergency_contact, $phone_3, $phone_3_type, $c_email, 
            $is_member, $is_baptized, $is_dedicated, $dedication_date, $anniv_date, $marital_status, 
            $join_date, $baptized_date, $is_child, $is_head, $is_active, $contact_id
        );

        $stmt->execute();
        $stmt->close();
    } else {
        $assigned_family = ($is_head === 1) ? null : $family_id;

        $stmt = $db->prepare("INSERT INTO contacts (
            family_id, title_id, contact_type_id, first_name, middle_name, n_sufix, last_name, date_of_birth, date_of_death, is_deceased,
            gender, address_1, city, state, zipcode, 
            phone_1, phone_1_type, phone_2, phone_2_type, 
            emergency_contact, phone_3, phone_3_type, c_email, 
            is_member, is_baptized, is_dedicated, dedication_date, anniv_date, marital_status, 
            join_date, baptized_date, is_child, is_head, is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("iiissssssissssssisissisiiississiii", 
            $assigned_family, $title_id, $contact_type_id, $first_name, $middle_name, $n_sufix, $last_name, $date_of_birth, 
            $date_of_death, $is_deceased, $gender, $address_1, $city, $state, $zipcode, 
            $phone_1, $phone_1_type, $phone_2, $phone_2_type, $emergency_contact, $phone_3, $phone_3_type, $c_email, 
            $is_member, $is_baptized, $is_dedicated, $dedication_date, $anniv_date, $marital_status, 
            $join_date, $baptized_date, $is_child, $is_head, $is_active
        );

        $stmt->execute();
        $contact_id = $stmt->insert_id;
        $stmt->close();

        if ($is_head === 1 && $contact_id > 0) {
            $assigned_family = $contact_id;
            $updFamCol = $db->prepare("UPDATE contacts SET family_id = ? WHERE contact_id = ?");
            $updFamCol->bind_param("ii", $assigned_family, $contact_id);
            $updFamCol->execute();
            $updFamCol->close();
        }
    }

    // Keep the family relationship table in sync with the contact form.
    $delFamily = $db->prepare("DELETE FROM Families WHERE contact_id = ?");
    $delFamily->bind_param("i", $contact_id);
    $delFamily->execute();
    $delFamily->close();

    if ($assigned_family !== null && $assigned_family > 0) {
        $insFamily = $db->prepare("
            INSERT INTO Families (contact_id, family_id, is_spouse)
            VALUES (?, ?, ?)
        ");
        $insFamily->bind_param("iii", $contact_id, $assigned_family, $is_spouse);
        $insFamily->execute();
        $insFamily->close();
    }

    $ministries = $_POST['ministries'] ?? [];
    $roles = $_POST['roles'] ?? [];
    if (!is_array($ministries) || !is_array($roles)) {
        throw new InvalidArgumentException('Invalid ministry assignments.');
    }

    $delMinistries = $db->prepare("DELETE FROM member_alliance WHERE contact_id = ?");
    $delMinistries->bind_param("i", $contact_id);
    $delMinistries->execute();
    $delMinistries->close();

    if ($ministries) {
        $insMinistry = $db->prepare("INSERT INTO member_alliance (contact_id, min_comm_id, role_id, is_active) VALUES (?, ?, ?, 1)");
        foreach (array_unique($ministries) as $ministryId) {
            if (!ctype_digit((string)$ministryId) || (int)$ministryId <= 0) {
                throw new InvalidArgumentException('Invalid ministry assignment.');
            }
            $ministryId = (int)$ministryId;
            $roleValue = $roles[$ministryId] ?? '';
            if ($roleValue !== '' && (!ctype_digit((string)$roleValue) || (int)$roleValue <= 0)) {
                throw new InvalidArgumentException('Invalid ministry role.');
            }
            $roleId = $roleValue === '' ? null : (int)$roleValue;
            $insMinistry->bind_param("iii", $contact_id, $ministryId, $roleId);
            $insMinistry->execute();
        }
        $insMinistry->close();
    }

    // 3. Save Attachments into document_lib
    $fileKey = !empty($_FILES['attach_files']['name']) ? 'attach_files' : (!empty($_FILES['document_name']['name']) ? 'document_name' : null);

    if ($fileKey && !empty($_FILES[$fileKey]['name'])) {
        $files      = $_FILES[$fileKey];
        $shortNames = $_POST['document_short_name'] ?? [];
        $entityType = $_POST['entity_type'] ?? 'contact'; // Default to contact

        $insAtt = $db->prepare("
            INSERT INTO document_lib (entity_type, entity_id, document_short_name, document_name, document_mime, document_size, document_data) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($files['name'] as $fIdx => $fileName) {
            if (!empty($fileName) && $files['error'][$fIdx] === UPLOAD_ERR_OK) {
                $docShortName = !empty($shortNames[$fIdx]) ? trim($shortNames[$fIdx]) : 'General';
                $tmpPath      = $files['tmp_name'][$fIdx];
                $docFullName  = basename($fileName);
                $docSize      = (int)$files['size'][$fIdx];
                $docMime      = mime_content_type($tmpPath) ?: $files['type'][$fIdx];
                $docData      = file_get_contents($tmpPath);
                if ($docData === false) {
                    throw new RuntimeException('The attachment could not be read.');
                }
                if (isDocxDocument($docFullName, $docMime)) {
                    $docData = normalizeDocxData($docData);
                    $docSize = strlen($docData);
                }

                $nullBlob = NULL;
                $insAtt->bind_param("sisssib", $entityType, $contact_id, $docShortName, $docFullName, $docMime, $docSize, $nullBlob);
                $insAtt->send_long_data(6, $docData);
                $insAtt->execute();
            }
        }
        $insAtt->close();
    }

    $db->commit();

    echo json_encode([
        "status" => "success", 
        "message" => "Contact saved successfully.",
        "contact_id" => $contact_id
    ]);

} catch (Exception $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Save error: " . $e->getMessage(),
        "file" => $e->getFile(),
        "line" => $e->getLine()
    ]);
}

if (isset($db) && $db instanceof mysqli) {
    mysqli_close($db);
}