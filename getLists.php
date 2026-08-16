<?php
// Include DB Connection
require_once 'config/db.php';

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');

$response = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Check filter options
    $membersOnly = isset($_POST['members_only']) ? (int)$_POST['members_only'] : 0;
    $ageFilter   = isset($_POST['age_filter']) ? (int)$_POST['age_filter'] : 0; // 0 = All, 1 = Adults Only, 2 = Children Only

    try {
        // Build dynamic WHERE clause
        $whereConditions = [];

        if ($membersOnly === 1) {
            $whereConditions[] = "is_member = 1";
        }

        if ($ageFilter === 1) {
            // Adults Only (is_child is 0 or NULL)
            $whereConditions[] = "(is_child = 0 OR is_child IS NULL)";
        } elseif ($ageFilter === 2) {
            // Children Only (is_child is 1)
            $whereConditions[] = "is_child = 1";
        }

        $whereClause = "";
        if (!empty($whereConditions)) {
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        }

        // 1. Fetch Contacts (Filtered dynamically)
        $contactQuery = "SELECT contact_id, CONCAT(COALESCE(last_name, ''), ', ', COALESCE(first_name, '')) AS fullname 
                         FROM contacts 
                         {$whereClause} 
                         ORDER BY last_name ASC, first_name ASC";

        $contactsResult = $db->query($contactQuery);
        $contacts = $contactsResult ? $contactsResult->fetch_all(MYSQLI_ASSOC) : [];

        // 2. Fetch Heads of Household for the Family Dropdown
        $headQuery = "SELECT contact_id, CONCAT(COALESCE(last_name, ''), ', ', COALESCE(first_name, '')) AS fullname 
                      FROM contacts 
                      WHERE is_head = 1 
                      ORDER BY last_name ASC, first_name ASC";
        $headResult = $db->query($headQuery);
        $heads = $headResult ? $headResult->fetch_all(MYSQLI_ASSOC) : [];

        // 3. Fetch Titles/Salutations
        $titleResult = $db->query("SELECT title_id, titleabr FROM title ORDER BY titleabr ASC");
        $titles = $titleResult ? $titleResult->fetch_all(MYSQLI_ASSOC) : [];

        // 4. Fetch Marital Status Options
        $maritalResult = $db->query("SELECT marital_id, marital_status FROM marital_status ORDER BY marital_status ASC");
        $marital = $maritalResult ? $maritalResult->fetch_all(MYSQLI_ASSOC) : [];

        // 5. Fetch Phone Types
        $phoneTypeResult = $db->query("SELECT phone_type_id, phone_type_desc FROM phone_type ORDER BY phone_type_desc ASC");
        $phonetype = $phoneTypeResult ? $phoneTypeResult->fetch_all(MYSQLI_ASSOC) : [];

        // 6. Fetch Ministry/Committee List joined with min_group_type
        $minQuery = "SELECT mc.min_comm_id, mc.min_comm_type_id, mc.min_comm_name, 
                            mgt.min_grp_type_desc 
                     FROM ministry_committee mc
                     LEFT JOIN min_group_type mgt ON mc.min_comm_type_id = mgt.min_grp_type_id
                     ORDER BY mc.min_comm_type_id ASC, mc.min_comm_name ASC";
        $minStmt = $db->query($minQuery);
        $ministryList = $minStmt ? $minStmt->fetch_all(MYSQLI_ASSOC) : [];

        // 7. Fetch Roles List
        $roleStmt = $db->query("SELECT role_id, role_desc FROM roles ORDER BY role_desc ASC");
        $roleList = $roleStmt ? $roleStmt->fetch_all(MYSQLI_ASSOC) : [];

        $response = [
            'contacts'     => $contacts,
            'heads'        => $heads,
            'titles'       => $titles,
            'marital'      => $marital,
            'phonetype'    => $phonetype,
            'ministryList' => $ministryList,
            'roleList'     => $roleList
        ];

    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        $response = [
            'status'  => 'error',
            'message' => 'Database Query Error: ' . $e->getMessage()
        ];
    }

} else {
    http_response_code(400);
    $response = ['error' => 'Invalid request method'];
}

echo json_encode($response);

if (isset($db) && $db instanceof mysqli) {
    mysqli_close($db);
}
?>