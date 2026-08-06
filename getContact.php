<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$response = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!empty($_POST["contactid"])) {
        $contactid = (int)$_POST["contactid"];

        // 1. Fetch Contact Details
        $query = "SELECT contact_id, title_id, last_name, first_name, middle_name, 
                         date_of_birth, gender, address_1, city, state, zipcode, 
                         phone_1, phone_1_type, phone_2, phone_2_type, emergency_contact, 
                         phone_3, phone_3_type, c_email, is_member, is_baptized, 
                         anniv_date, marital_status, join_date, baptized_date, 
                         is_child, is_head, is_active 
                  FROM contacts WHERE contact_id = ?";
        
        if ($stmt = mysqli_prepare($db, $query)) {
            mysqli_stmt_bind_param($stmt, "i", $contactid);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($contact = mysqli_fetch_assoc($result)) {
                
                // 2. Fetch Family Link
                $family_id = null;
                $famQuery = "SELECT family_id FROM Families WHERE contact_id = ?";
                if ($famStmt = mysqli_prepare($db, $famQuery)) {
                    mysqli_stmt_bind_param($famStmt, "i", $contactid);
                    mysqli_stmt_execute($famStmt);
                    $famResult = mysqli_stmt_get_result($famStmt);
                    if ($famRow = mysqli_fetch_assoc($famResult)) {
                        $family_id = $famRow['family_id'];
                    }
                    mysqli_stmt_close($famStmt);
                }

                // 3. Fetch Associated Ministry Alliances
                $ministries = [];
                $minquery = "SELECT member_id, contact_id, min_comm_id, role_id, is_active 
                             FROM member_alliance WHERE contact_id = ?";
                             
                if ($minstmt = mysqli_prepare($db, $minquery)) {
                    mysqli_stmt_bind_param($minstmt, "i", $contactid);
                    mysqli_stmt_execute($minstmt);
                    $result2 = mysqli_stmt_get_result($minstmt);

                    while ($row = mysqli_fetch_assoc($result2)) {
                        $ministries[] = $row;
                    }
                    mysqli_stmt_close($minstmt);
                }

                $minQuery = "SELECT mc.min_comm_id, mc.min_comm_type_id, mc.min_comm_name, 
                                    mgt.min_grp_type_desc 
                             FROM ministry_committee mc
                             LEFT JOIN min_group_type mgt ON mc.min_comm_type_id = mgt.min_grp_type_id
                             ORDER BY mc.min_comm_type_id ASC, mc.min_comm_name ASC";
                $minStmt = $db->query($minQuery);
                $ministryList = $minStmt ? $minStmt->fetch_all(MYSQLI_ASSOC) : [];

                $roleStmt = $db->query("SELECT role_id, role_desc FROM roles ORDER BY role_desc");
                $roleList = $roleStmt ? $roleStmt->fetch_all(MYSQLI_ASSOC) : [];

                $response = [ 
                    'contact'      => $contact,
                    'family_id'    => $family_id,
                    'ministries'   => $ministries,
                    'ministryList' => $ministryList,
                    'roleList'     => $roleList 
                ];

            } else {
                $response = ['error' => 'Contact not found'];
            }
            mysqli_stmt_close($stmt);
        } else {
            $response = ['error' => 'Database query failed'];
        }
    } else {    
        $response = ['error' => 'No contact ID provided'];
    }
} else {
    $response = ['error' => 'Invalid request method'];
}

echo json_encode($response);     

if (isset($db) && $db instanceof mysqli) {
    mysqli_close($db);
}
?>