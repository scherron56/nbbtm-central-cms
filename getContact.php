<?php
// getContact.php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once 'config/db.php';
require_once 'include/auth.php';

if (!isset($db) || !($db instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection unavailable.']);
    exit;
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

    // Fetch is_spouse from Families table
    $contact['is_spouse'] = 0;
    try {
        $famStmt = $db->prepare("SELECT is_spouse FROM Families WHERE contact_id = ? LIMIT 1");
        $famStmt->bind_param("i", $contactId);
        $famStmt->execute();
        $famRes = $famStmt->get_result()->fetch_assoc();
        if ($famRes && isset($famRes['is_spouse'])) {
            $contact['is_spouse'] = $famRes['is_spouse'];
        }
        $famStmt->close();
    } catch (Throwable $e) {}

    // 2. Fetch Marital Description Safely
    $contact['marital_status_desc'] = '';
    if (!empty($contact['marital_status'])) {
        try {
            $mStmt = $db->prepare("SELECT marital_status FROM marital_status WHERE marital_id = ?");
            $mStmt->bind_param("i", $contact['marital_status']);
            $mStmt->execute();
            $mRow = $mStmt->get_result()->fetch_assoc();
            $contact['marital_status_desc'] = $mRow['marital_status'] ?? '';
            $mStmt->close();
        } catch (Throwable $e) {}
    }

    // 2.1 Fetch Contact Type Description as contact_desc
    $contact['contact_desc'] = '';
    $contactTypeId = $contact['contact_type'] ?? ($contact['contact_type_id'] ?? null);
    if (!empty($contactTypeId)) {
        try {
            $ctStmt = $db->prepare("SELECT contact_desc FROM contact_type WHERE contact_type_id = ?");
            $ctStmt->bind_param("i", $contactTypeId);
            $ctStmt->execute();
            $ctRow = $ctStmt->get_result()->fetch_assoc();
            $contact['contact_desc'] = $ctRow['contact_desc'] ?? '';
            $ctStmt->close();
        } catch (Throwable $e) {}
    }

    // 3. Phone Types Helper
    function getPhoneDesc($db, $typeId) {
        if (empty($typeId)) return '';
        try {
            $q = $db->prepare("SELECT phone_type_desc FROM phone_type WHERE phone_type_id = ?");
            $q->bind_param("i", $typeId);
            $q->execute();
            $row = $q->get_result()->fetch_assoc();
            $q->close();
            return $row['phone_type_desc'] ?? '';
        } catch (Throwable $e) {
            return '';
        }
    }

    $contact['phone_1_type_desc'] = getPhoneDesc($db, $contact['phone_1_type'] ?? null);
    $contact['phone_2_type_desc'] = getPhoneDesc($db, $contact['phone_2_type'] ?? null);
    $contact['phone_3_type_desc'] = getPhoneDesc($db, $contact['phone_3_type'] ?? null);

    // 4. Fetch Family Members
    $familyId = null;
    $familyMembers = [];
    try {
        $famCheck = $db->prepare("SELECT family_id FROM Families WHERE contact_id = ? LIMIT 1");
        $famCheck->bind_param("i", $contactId);
        $famCheck->execute();
        $famResData = $famCheck->get_result()->fetch_assoc();
        $famCheck->close();

        $familyId = $famResData['family_id'] ?? null;

        if (!empty($familyId)) {
            $fStmt = $db->prepare("
                SELECT 
                    c.contact_id, 
                    c.first_name, 
                    c.last_name, 
                    c.is_member,
                    c.is_head, 
                    famRes.is_spouse, 
                    c.is_child 
                FROM Families famRes
                JOIN contacts c ON famRes.contact_id = c.contact_id
                WHERE famRes.family_id = ? 
                ORDER BY c.is_head DESC, famRes.is_spouse DESC, c.first_name ASC
            ");
            $fStmt->bind_param("i", $familyId);
            $fStmt->execute();
            $familyMembers = $fStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $fStmt->close();
        }
    } catch (Throwable $e) {}

    // 5. Fetch Ministry Involvements
    $ministries = [];
    try {
        $mStmt = $db->prepare("SELECT min_comm_id, role_id, is_active FROM member_alliance WHERE contact_id = ?");
        $mStmt->bind_param("i", $contactId);
        $mStmt->execute();
        $ministries = $mStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $mStmt->close();
    } catch (Throwable $e) {}

    // 6. Lookups
    $minList = [];
    try {
        $minRes = $db->query("
            SELECT 
                mc.min_comm_id, 
                mc.min_comm_name, 
                mc.min_comm_type_id,
                COALESCE(NULLIF(gt.min_grp_type_desc, ''), 'General Associations') AS min_grp_type_desc
            FROM ministry_committee mc
            LEFT JOIN min_group_type gt ON mc.min_comm_type_id = gt.min_grp_type_id
            ORDER BY min_grp_type_desc ASC, mc.min_comm_name ASC
        ");
        if ($minRes) { 
            $minList = $minRes->fetch_all(MYSQLI_ASSOC); 
            $minRes->free(); 
        }
    } catch (Throwable $e) {}

    $roleList = [];
    try {
        $roleRes = $db->query("SELECT role_id, role_desc FROM roles ORDER BY role_desc ASC");
        if ($roleRes) { 
            $roleList = $roleRes->fetch_all(MYSQLI_ASSOC); 
            $roleRes->free(); 
        }
    } catch (Throwable $e) {}

    // 7. Fetch Attachments from document_lib
    $attachments = [];
    try {
        $attStmt = $db->prepare("
            SELECT document_id, document_short_name, document_name, document_size, document_mime 
            FROM document_lib 
            WHERE entity_type = 'contact' AND entity_id = ?
        ");
        if ($attStmt) {
            $attStmt->bind_param("i", $contactId);
            $attStmt->execute();
            $attRes = $attStmt->get_result();
            while ($row = $attRes->fetch_assoc()) {
                $attachments[] = $row;
            }
            $attStmt->close();
        }
    } catch (Throwable $e) {}

    $response = json_encode([
        'contact'        => $contact,
        'family_id'      => $familyId,
        'familyMembers'  => $familyMembers,
        'ministries'     => $ministries,
        'ministryList'   => $minList,
        'roleList'       => $roleList,
        'attachments'    => $attachments
    ], JSON_INVALID_UTF8_SUBSTITUTE);
    if ($response === false) {
        throw new RuntimeException('Unable to encode contact data.');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo $response;
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(['error' => 'Query Error: ' . $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
}