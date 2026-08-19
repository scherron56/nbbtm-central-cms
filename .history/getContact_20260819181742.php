<?php
// getContact.php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/auth.php';

// Fallback if db.php defines $conn instead of $db
if (!isset($db) && isset($conn)) { 
    $db = $conn; 
}

$contactId = (int)($_POST['contactid'] ?? ($_GET['contactid'] ?? 0));
if ($contactId <= 0) {
    echo json_encode(['error' => 'Invalid Contact ID provided.']);
    exit;
}

try {
    // 1. Fetch Contact Details
    $stmt = $db->prepare("SELECT * FROM contacts WHERE contact_id = ?");
    $stmt->bind_param("i", $contactId);
    $stmt->execute();
    $contact = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$contact) {
        echo json_encode(['error' => 'Contact record not found.']);
        exit;
    }

    // Resolve Text Descriptions Safely
    $maritalDesc = '';
    if (!empty($contact['marital_status'])) {
        $mStmt = $db->prepare("SELECT marital_status FROM marital_status WHERE marital_id = ?");
        $mStmt->bind_param("i", $contact['marital_status']);
        $mStmt->execute();
        $mRes = $mStmt->get_result()->fetch_assoc();
        $maritalDesc = $mRes['marital_status'] ?? '';
        $mStmt->close();
    }
    $contact['marital_status_desc'] = $maritalDesc;

    // Helper for Phone Type resolution (checking phonetype vs phone_type)
    function resolvePhoneType($db, $typeId) {
        if (empty($typeId)) return '';
        $q = $db->prepare("SELECT phone_type_desc FROM phonetype WHERE phone_type_id = ?");
        if (!$q) {
            $q = $db->prepare("SELECT phone_type_desc FROM phone_type WHERE phone_type_id = ?");
        }
        if ($q) {
            $q->bind_param("i", $typeId);
            $q->execute();
            $row = $q->get_result()->fetch_assoc();
            $q->close();
            return $row['phone_type_desc'] ?? '';
        }
        return '';
    }

    $contact['phone_1_type_desc'] = resolvePhoneType($db, $contact['phone_1_type'] ?? null);
    $contact['phone_2_type_desc'] = resolvePhoneType($db, $contact['phone_2_type'] ?? null);
    $contact['phone_3_type_desc'] = resolvePhoneType($db, $contact['phone_3_type'] ?? null);

    // 2. Fetch Family Members
    $familyId = $contact['family_id'] ?? null;
    $familyMembers = [];
    if (!empty($familyId)) {
        $fStmt = $db->prepare("SELECT contact_id, first_name, last_name, is_head, is_child, is_member FROM contacts WHERE family_id = ? ORDER BY is_head DESC, first_name ASC");
        if ($fStmt) {
            $fStmt->bind_param("i", $familyId);
            $fStmt->execute();
            $familyMembers = $fStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $fStmt->close();
        }
    }

    // 3. Fetch Ministry Involvements
    $ministries = [];
    $mStmt = $db->prepare("SELECT min_comm_id, role_id, is_active FROM member_alliance WHERE contact_id = ?");
    if ($mStmt) {
        $mStmt->bind_param("i", $contactId);
        $mStmt->execute();
        $ministries = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $mStmt->close();
    }

    // 4. Fetch Dropdown Lookups
    $minList = [];
    $minRes = $db->query("SELECT min_comm_id, min_comm_name, min_comm_type_id FROM ministry_committee ORDER BY min_comm_name ASC");
    if ($minRes) { $minList = $minRes->fetch_all(MYSQLI_ASSOC); }

    $roleList = [];
    $roleRes = $db->query("SELECT role_id, role_desc FROM roles ORDER BY role_desc ASC");
    if ($roleRes) { $roleList = $roleRes->fetch_all(MYSQLI_ASSOC); }

    // 5. Fetch Attachments from app_attachments
    $attachments = [];
    $attStmt = $db->prepare("
        SELECT a.attachment_id, a.doc_category_id, a.document_name, a.document_size, a.uploaded_at, c.category_name
        FROM app_attachments a
        LEFT JOIN doc_categories c ON a.doc_category_id = c.doc_category_id
        WHERE a.entity_type = 'contact' AND a.entity_id = ?
        ORDER BY c.category_name ASC, a.uploaded_at DESC
    ");
    if ($attStmt) {
        $attStmt->bind_param("i", $contactId);
        $attStmt->execute();
        $attachments = $attStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $attStmt->close();
    }

    echo json_encode([
        'contact'        => $contact,
        'family_id'      => $familyId,
        'familyMembers'  => $familyMembers,
        'ministries'     => $ministries,
        'ministryList'   => $minList,
        'roleList'       => $roleList,
        'attachments'    => $attachments
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}