<<?php
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

$response = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" || $_SERVER["REQUEST_METHOD"] === "GET") {
    
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

        // 1. Fetch Contacts
        $contactQuery = "SELECT contact_id, CONCAT(COALESCE(last_name, ''), ', ', COALESCE(first_name, '')) AS fullname 
                         FROM contacts 
                         {$whereClause} 
                         ORDER BY last_name ASC, first_name ASC";
        $contactsResult = $db->query($contactQuery);
        $contacts = $contactsResult ? $contactsResult->fetch_all(MYSQLI_ASSOC) : [];

        // 2. Fetch Heads of Household
        $headQuery = "SELECT contact_id, CONCAT(COALESCE(last_name, ''), ', ', COALESCE(first_name, '')) AS fullname 
                      FROM contacts 
                      WHERE is_head = 1 
                      ORDER BY last_name ASC, first_name ASC";
        $headResult = $db->query($headQuery);
        $heads = $headResult ? $headResult->fetch_all(MYSQLI_ASSOC) : [];

        // 3. Fetch Titles/Salutations (Safe Table Fallback)
        $titles = [];
        $titleQuery = $db->query("SELECT title_id, titleabr FROM titles ORDER BY titleabr ASC");
        if (!$titleQuery) {
            $titleQuery = $db->query("SELECT title_id, titleabr FROM title ORDER BY titleabr ASC");
        }
        if ($titleQuery) { $titles = $titleQuery->fetch_all(MYSQLI_ASSOC); }

        // 4. Fetch Marital Status Options
        $marital = [];
        $maritalQuery = $db->query("SELECT marital_id, marital_status FROM marital_status ORDER BY marital_status ASC");
        if ($maritalQuery) { $marital = $maritalQuery->fetch_all(MYSQLI_ASSOC); }

        // 5. Fetch Phone Types (Safe Table Fallback)
        $phonetype = [];
        $phoneQuery = $db->query("SELECT phone_type_id, phone_type_desc FROM phonetype ORDER BY phone_type_desc ASC");
        if (!$phoneQuery) {
            $phoneQuery = $db->query("SELECT phone_type_id, phone_type_desc FROM phone_type ORDER BY phone_type_desc ASC");
        }
        if ($phoneQuery) { $phonetype = $phoneQuery->fetch_all(MYSQLI_ASSOC); }

        // 6. Fetch Ministry List (Safe Fallback on Group Types)
        $ministryList = [];
        $minQuery = $db->query("
            SELECT mc.min_comm_id, mc.min_comm_type_id, mc.min_comm_name, COALESCE(mgt.min_grp_type_desc, 'General') AS min_grp_type_desc 
            FROM ministry_committee mc
            LEFT JOIN ministry_group_types mgt ON mc.min_comm_type_id = mgt.min_grp_type_id
            ORDER BY mc.min_comm_name ASC
        ");
        if (!$minQuery) {
            $minQuery = $db->query("
                SELECT mc.min_comm_id, mc.min_comm_type_id, mc.min_comm_name, COALESCE(mgt.min_grp_type_desc, 'General') AS min_grp_type_desc 
                FROM ministry_committee mc
                LEFT JOIN min_group_type mgt ON mc.min_comm_type_id = mgt.min_grp_type_id
                ORDER BY mc.min_comm_name ASC
            ");
        }
        if ($minQuery) { $ministryList = $minQuery->fetch_all(MYSQLI_ASSOC); }

        // 7. Fetch Roles List
        $roleList = [];
        $roleQuery = $db->query("SELECT role_id, role_desc FROM roles ORDER BY role_desc ASC");
        if ($roleQuery) { $roleList = $roleQuery->fetch_all(MYSQLI_ASSOC); }

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
            'message' => 'Database Query Error: ' . $e->getMessage()
        ]);
    }

} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}