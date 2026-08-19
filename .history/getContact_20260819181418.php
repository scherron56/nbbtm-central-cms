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

// Allow any logged-in user to fetch details (do NOT call requireAdmin() here)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$contactId = (int)($_POST['contactid'] ?? 0);
if ($contactId <= 0) {
    echo json_encode(['error' => 'Invalid Contact ID']);
    exit;
}

try {
    // 1. Fetch Contact Record
    $stmt = $db->prepare("
        SELECT c.*, t.titleabr, m.marital_status AS marital_status_desc,
               p1.phone_type_desc AS phone_1_type_desc,
               p2.phone_type_desc AS phone_2_type_desc,
               p3.phone_type_desc AS phone_3_type_desc
        FROM contacts c
        LEFT JOIN titles t ON c.title_id = t.title_id
        LEFT JOIN marital_status m ON c.marital_status = m.marital_id
        LEFT JOIN phonetype p1 ON c.phone_1_type = p1.phone_type_id
        LEFT JOIN phonetype p2 ON c.phone_2_type = p2.phone_type_id
        LEFT JOIN phonetype p3 ON c.phone_3_type = p3.phone_type_id
        WHERE c.contact_id = ?
    ");
    $stmt->bind_param("i", $contactId);
    $stmt->execute();
    $contact = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$contact) {
        echo json_encode(['error' => 'Contact not found']);
        exit;
    }

    // 2. Fetch Family Members
    $familyId = $contact['family_id'] ?? null;
    $familyMembers = [];
    if ($familyId) {
        $fStmt = $db->prepare("SELECT contact_id, first_name, last_name, is_head, is_child, is_member FROM contacts WHERE family_id = ? ORDER BY is_head DESC, first_name ASC");
        $fStmt->bind_param("i", $familyId);
        $fStmt->execute();
        $familyMembers = $fStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $fStmt->close();
    }

    // 3. Fetch Ministry Involvements
    $mStmt = $db->prepare("SELECT min_comm_id, role_id, is_active FROM member_alliance WHERE contact_id = ?");
    $mStmt->bind_param("i", $contactId);
    $mStmt->execute();
    $ministries = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $mStmt->close();

    // 4. Fetch Dropdown / Metadata
    $minList = $db->query("SELECT m.min_comm_id, m.min_comm_name, m.min_comm_type_id, g.min_grp_type_desc FROM ministry_committee m LEFT JOIN ministry_group_types g ON m.min_comm_type_id = g.min_grp_type_id ORDER BY m.min_comm_name ASC")->fetch_all(MYSQLI_ASSOC);
    $roleList = $db->query("SELECT role_id, role_desc FROM roles ORDER BY role_desc ASC")->fetch_all(MYSQLI_ASSOC);

    // 5. Fetch Attachments from app_attachments
    $attStmt = $db->prepare("
        SELECT a.attachment_id, a.doc_category_id, a.document_name, a.document_size, a.uploaded_at, c.category_name
        FROM app_attachments a
        LEFT JOIN doc_categories c ON a.doc_category_id = c.doc_category_id
        WHERE a.entity_type = 'contact' AND a.entity_id = ?
        ORDER BY c.category_name ASC, a.uploaded_at DESC
    ");
    $attStmt->bind_param("i", $contactId);
    $attStmt->execute();
    $attachments = $attStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $attStmt->close();

    echo json_encode([
        'contact'        => $contact,
        'family_id'      => $familyId,
        'familyMembers'  => $familyMembers,
        'ministries'     => $ministries,
        'ministryList'   => $minList,
        'roleList'       => $roleList,
        'attachments'    => $attachments
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}