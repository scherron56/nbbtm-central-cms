<?php
// getLists.php
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

// Fallback if db.php defines $conn instead of $db
if (!isset($db) && isset($conn)) { 
    $db = $conn; 
}

if (!$db) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection unavailable.']);
    exit;
}

$membersOnly = isset($_REQUEST['members_only']) ? (int)$_REQUEST['members_only'] : 0;
$ageFilter   = isset($_REQUEST['age_filter']) ? (int)$_REQUEST['age_filter'] : 0;

try {
    $whereConditions = [];

    if ($membersOnly === 1) {
        $whereConditions[] = "is_member = 1";
    } elseif ($membersOnly === 2) {
        $whereConditions[] = "(is_member = 0 OR is_member IS NULL)";
    }

    if ($ageFilter === 1) {
        $whereConditions[] = "(is_child = 0 OR is_child IS NULL)";
    } elseif ($ageFilter === 2) {
        $whereConditions[] = "is_child = 1";
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    // 1. Fetch Contacts (Primary Query)
    $contactQuery = "SELECT contact_id, 
                            TRIM(CONCAT(COALESCE(last_name, ''), ', ', COALESCE(first_name, ''))) AS fullname 
                     FROM contacts 
                     {$whereClause} 
                     ORDER BY last_name ASC, first_name ASC";

    $contactsResult = $db->query($contactQuery);
    $contacts = $contactsResult ? $contactsResult->fetch_all(MYSQLI_ASSOC) : [];

    // Clean display fallback for contacts with empty names
    foreach ($contacts as &$c) {
        if (trim($c['fullname']) === ',' || empty(trim($c['fullname']))) {
            $c['fullname'] = 'Contact #' . $c['contact_id'];
        }
    }
    unset($c);

    // 2. Fetch Heads of Household
    $heads = [];
    $headResult = $db->query("SELECT contact_id, TRIM(CONCAT(COALESCE(last_name, ''), ', ', COALESCE(first_name, ''))) AS fullname FROM contacts WHERE is_head = 1 ORDER BY last_name ASC, first_name ASC");
    if ($headResult) { $heads = $headResult->fetch_all(MYSQLI_ASSOC); }

    // 3. Optional Lookup Tables (Protected with safe fallbacks)
    $titles = [];
    $tRes = $db->query("SELECT title_id, titleabr FROM titles ORDER BY titleabr ASC");
    if (!$tRes) { $tRes = $db->query("SELECT title_id, titleabr FROM title ORDER BY titleabr ASC"); }
    if ($tRes) { $titles = $tRes->fetch_all(MYSQLI_ASSOC); }

    $marital = [];
    $mRes = $db->query("SELECT marital_id, marital_status FROM marital_status ORDER BY marital_status ASC");
    if ($mRes) { $marital = $mRes->fetch_all(MYSQLI_ASSOC); }

    $phonetype = [];
    $pRes = $db->query("SELECT phone_type_id, phone_type_desc FROM phonetype ORDER BY phone_type_desc ASC");
    if (!$pRes) { $pRes = $db->query("SELECT phone_type_id, phone_type_desc FROM phone_type ORDER BY phone_type_desc ASC"); }
    if ($pRes) { $phonetype = $pRes->fetch_all(MYSQLI_ASSOC); }

    $ministryList = [];
    $minRes = $db->query("SELECT min_comm_id, min_comm_name, min_comm_type_id FROM ministry_committee ORDER BY min_comm_name ASC");
    if ($minRes) { $ministryList = $minRes->fetch_all(MYSQLI_ASSOC); }

    $roleList = [];
    $rRes = $db->query("SELECT role_id, role_desc FROM roles ORDER BY role_desc ASC");
    if ($rRes) { $roleList = $rRes->fetch_all(MYSQLI_ASSOC); }

    echo json_encode([
        'status'       => 'success',
        'contacts'     => $contacts,
        'heads'        => $heads,
        'titles'       => $titles,
        'marital'      => $marital,
        'phonetype'    => $phonetype,
        'ministryList' => $ministryList,
        'roleList'     => $roleList
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database Query Error: ' . $e->getMessage(),
        'contacts' => []
    ]);
}