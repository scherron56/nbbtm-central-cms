<?php
// $db is provided by db.php; do not overwrite it here.
require_once 'config/db.php';
header('Content-Type: application/json; charset=utf-8');

$errors = [];

// Sanitize incoming baseline parameter metrics safely
$contact_id  = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
$first_name  = $_POST['first_name'] ?? '';
$last_name   = $_POST['last_name'] ?? '';
$c_email     = $_POST['c_email'] ?? '';
$is_member   = (!empty($_POST['is_member']) && $_POST['is_member'] != '0') ? 1 : 0;

// Sub-arrays mapped directly from serialize() payloads
$ministries  = $_POST['ministries'] ?? []; 
$roles       = $_POST['roles'] ?? [];      

// Data Validation Routines
if (empty(trim($first_name))) { $errors['firstname'] = 'First Name is required.'; }
if (empty(trim($last_name))) { $errors['lastname'] = 'Last Name is required.'; }
if (!empty($c_email) && !filter_var($c_email, FILTER_VALIDATE_EMAIL)) { $errors['c_email'] = 'Please enter a valid email address.'; }

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
        $sql = "UPDATE contacts SET first_name = ?, last_name = ?, c_email = ?, is_member = ? WHERE contact_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sssii", $first_name, $last_name, $c_email, $is_member, $contact_id);
        $stmt->execute();
        $stmt->close();
        $target_id = $contact_id;
    } else {
        // INSERT MODE
        $sql = "INSERT INTO contacts (first_name, last_name, c_email, is_member, is_active) VALUES (?, ?, ?, ?, 1)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("sssi", $first_name, $last_name, $c_email, $is_member);
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

    // Re-link selection data if active church credentials match explicitly
    if ($is_member === 1 && !empty($ministries)) {
        $insAllSql = "INSERT INTO member_alliance (contact_id, group_id, role_id, is_active) VALUES (?, ?, ?, 1)";
        $allStmt = $db->prepare($insAllSql);

        foreach ($ministries as $group_id) {
            $group_id_int = intval($group_id);
            $role_id_int  = isset($roles[$group_id_int]) ? intval($roles[$group_id_int]) : 0;

            if ($group_id_int > 0) {
                $allStmt->bind_param("iii", $target_id, $group_id_int, $role_id_int);
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
