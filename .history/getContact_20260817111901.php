<?php
require_once 'config/db.php';

header('Content-Type: application/json');

$response = [];

// Helper function to format integer DB values into (XXX) XXX-XXXX for the frontend
function formatPhoneDisplay($val) {
    if (empty($val)) return null;
    $digits = preg_replace('/\D/', '', (string)$val);
    if (strlen($digits) === 10) {
        return sprintf("(%s) %s-%s", 
            substr($digits, 0, 3), 
            substr($digits, 3, 3), 
            substr($digits, 6, 4)
        );
    }
    return $digits;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!empty($_POST["contactid"])) {
        $contactid = (int)$_POST["contactid"];

        // 1. Fetch Contact Details JOINED with marital_status and phone_type tables
        $query = "SELECT c.contact_id, c.family_id, c.title_id, c.last_name, c.first_name, c.middle_name, 
                         c.date_of_birth, c.date_of_death, c.is_deceased, c.gender, c.address_1, c.city, c.state, c.zipcode, 
                         c.phone_1, c.phone_1_type, pt1.phone_type_desc AS phone_1_type_desc,
                         c.phone_2, c.phone_2_type, pt2.phone_type_desc AS phone_2_type_desc,
                         c.emergency_contact, 
                         c.phone_3, c.phone_3_type, pt3.phone_type_desc AS phone_3_type_desc,
                         c.c_email, c.is_member, c.is_baptized, 
                         c.anniv_date, c.marital_status, ms.marital_status AS marital_status_desc, 
                         c.join_date, c.baptized_date, 
                         c.is_child, c.is_head, c.is_active 
                  FROM contacts c
                  LEFT JOIN marital_status ms ON c.marital_status = ms.marital_id
                  LEFT JOIN phone_type pt1 ON c.phone_1_type = pt1.phone_type_id
                  LEFT JOIN phone_type pt2 ON c.phone_2_type = pt2.phone_type_id
                  LEFT JOIN phone_type pt3 ON c.phone_3_type = pt3.phone_type_id
                  WHERE c.contact_id = ?";
        
        if ($stmt = mysqli_prepare($db, $query)) {
            mysqli_stmt_bind_param($stmt, "i", $contactid);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($contact = mysqli_fetch_assoc($result)) {

                // Convert MySQL INT values to formatted phone string
                $contact['phone_1'] = formatPhoneDisplay($contact['phone_1']);
                $contact['phone_2'] = formatPhoneDisplay($contact['phone_2']);
                $contact['phone_3'] = formatPhoneDisplay($contact['phone_3']);

                // 2. Fetch Household Members
                $family_id = $contact['family_id'];

                if (empty($family_id)) {
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
                }

                $familyMembers = [];
                if (!empty($family_id)) {
                    $famMemQuery = "SELECT contact_id, first_name, last_name, 
                                           is_head, is_child, is_member, date_of_birth 
                                    FROM contacts 
                                    WHERE family_id = ? AND contact_id != ? 
                                    ORDER BY is_head DESC, is_child ASC, date_of_birth ASC";

                    if ($famMemStmt = mysqli_prepare($db, $famMemQuery)) {
                        mysqli_stmt_bind_param($famMemStmt, "ii", $family_id, $contactid);
                        mysqli_stmt_execute($famMemStmt);
                        $famMemResult = mysqli_stmt_get_result($famMemStmt);
                        while ($memberRow = mysqli_fetch_assoc($famMemResult)) {
                            $familyMembers[] = $memberRow;
                        }
                        mysqli_stmt_close($famMemStmt);
                    }
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
                    'contact'       => $contact,
                    'family_id'     => $family_id,
                    'familyMembers' => $familyMembers,
                    'ministries'    => $ministries,
                    'ministryList'  => $ministryList,
                    'roleList'      => $roleList 
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