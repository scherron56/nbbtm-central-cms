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

// Safe check if $db was properly initialized
if (!isset($db) || !($db instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'status'   => 'error',
        'message'  => 'Database connection handle $db is unavailable.',
        'contacts' => []
    ]);
    exit;
}

$membersOnly = isset($_REQUEST['members_only']) ? (int)$_REQUEST['members_only'] : 0;
$ageFilter   = isset($_REQUEST['age_filter']) ? (int)$_REQUEST['age_filter'] : 0;

try {
    // 1. Build Filter Conditions
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

    // 2. Fetch Contacts (Primary Query)
    $contactQuery = "SELECT contact_id, 
                            TRIM(CONCAT(COALESCE(last_name, ''), ', ', COALESCE(first_name, ''))) AS fullname 
                     FROM contacts 
                     {$whereClause} 
                     ORDER BY last_name ASC, first_name ASC";

    $contactsResult = $db->query($contactQuery);
    $contacts = [];

    if ($contactsResult) {
        while ($row = $contactsResult->fetch_assoc()) {
            $name = trim($row['fullname']);
            if ($name === ',' || empty($name)) {
                $name = 'Contact #' . $row['contact_id'];
            }
            $contacts[] = [
                'contact_id' => (int)$row['contact_id'],
                'fullname'   => $name
            ];
        }
        $contactsResult->free();
    }

    // 3. Fetch Heads of Household
    $heads = [];
    $headRes = $db->query("SELECT contact_id, TRIM(CONCAT(COALESCE(last_name, ''), ', ', COALESCE(first_name, ''))) AS fullname FROM contacts WHERE is_head = 1 ORDER BY last_name ASC, first_name ASC");
    if ($headRes) {
        while ($row = $headRes->fetch_assoc()) {
            $heads[] = [
                'contact_id' => (int)$row['contact_id'],
                'fullname'   => trim($row['fullname']) ?: 'Contact #' . $row['contact_id']
            ];
        }
        $headRes->free();
    }

    // 4. Safe Optional Metadata Lookups
    $titles = [];
    try {
        $tRes = $db->query("SELECT title_id, titleabr FROM titles ORDER BY titleabr ASC");
        if ($tRes) { $titles = $tRes->fetch_all(MYSQLI_ASSOC); $tRes->free(); }
    } catch (Throwable $e) {
        try {
            $tRes = $db->query("SELECT title_id, titleabr FROM title ORDER BY titleabr ASC");
            if ($tRes) { $titles = $tRes->fetch_all(MYSQLI_ASSOC); $tRes->free(); }
        } catch (Throwable $ignored) {}
    }

    $marital = [];
    try {
        $mRes = $db->query("SELECT marital_id, marital_status FROM marital_status ORDER BY marital_status ASC");
        if ($mRes) { $marital = $mRes->fetch_all(MYSQLI_ASSOC); $mRes->free(); }
    } catch (Throwable $e) {}

    $phonetype = [];
    try {
        $pRes = $db->query("SELECT phone_type_id, phone_type_desc FROM phonetype ORDER BY phone_type_desc ASC");
        if ($pRes) { $phonetype = $pRes->fetch_all(MYSQLI_ASSOC); $pRes->free(); }
    } catch (Throwable $e) {
        try {
            $pRes = $db->query("SELECT phone_type_id, phone_type_desc FROM phone_type ORDER BY phone_type_desc ASC");
            if ($pRes) { $phonetype = $pRes->fetch_all(MYSQLI_ASSOC); $pRes->free(); }
        } catch (Throwable $ignored) {}
    }

    $ministryList = [];
    try {
        $minRes = $db->query("
            SELECT mc.min_comm_id, mc.min_comm_type_id, mc.min_comm_name, 
                   COALESCE(mgt.min_grp_type_desc, 'General') AS min_grp_type_desc 
            FROM ministry_committee mc
            LEFT JOIN ministry_group_types mgt ON mc.min_comm_type_id = mgt.min_grp_type_id
            ORDER BY mc.min_comm_name ASC
        ");
        if ($minRes) { $ministryList = $minRes->fetch_all(MYSQLI_ASSOC); $minRes->free(); }
    } catch (Throwable $e) {
        try {
            $minRes = $db->query("
                SELECT mc.min_comm_id, mc.min_comm_type_id, mc.min_comm_name, 
                       COALESCE(mgt.min_grp_type_desc, 'General') AS min_grp_type_desc 
                FROM ministry_committee mc
                LEFT JOIN min_group_type mgt ON mc.min_comm_type_id = mgt.min_grp_type_id
                ORDER BY mc.min_comm_name ASC
            ");
            if ($minRes) { $ministryList = $minRes->fetch_all(MYSQLI_ASSOC); $minRes->free(); }
        } catch (Throwable $ignored) {}
    }

    $roleList = [];
    try {
        $rRes = $db->query("SELECT role_id, role_desc FROM roles ORDER BY role_desc ASC");
        if ($rRes) { $roleList = $rRes->fetch_all(MYSQLI_ASSOC); $rRes->free(); }
    } catch (Throwable $e) {}

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
        'status'   => 'error',
        'message'  => 'Database Error: ' . $e->getMessage(),
        'contacts' => []
    ]);
}