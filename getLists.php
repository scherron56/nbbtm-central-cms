<?php
// getLists.php
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

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

if (!isset($db) || !($db instanceof mysqli)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode([
        'status'   => 'error',
        'message'  => 'Database connection handle $db is unavailable.',
        'contacts' => []
    ]);
    exit;
}

$rawContactTypes = isset($_REQUEST['contact_type_id']) ? $_REQUEST['contact_type_id'] : [];
$ageFilter       = isset($_REQUEST['age_filter']) ? (int)$_REQUEST['age_filter'] : 0;

$contactTypeIds = [];
if (is_array($rawContactTypes)) {
    foreach ($rawContactTypes as $id) {
        if (is_numeric($id) && (int)$id > 0) {
            $contactTypeIds[] = (int)$id;
        }
    }
} elseif (is_numeric($rawContactTypes) && (int)$rawContactTypes > 0) {
    $contactTypeIds[] = (int)$rawContactTypes;
}

try {
    $whereConditions = [];

    if (!empty($contactTypeIds)) {
        $idsCsv = implode(',', $contactTypeIds);
        $whereConditions[] = "contact_type_id IN ({$idsCsv})";
    }

    if ($ageFilter === 1) {
        $whereConditions[] = "(is_child = 0 OR is_child IS NULL)";
    } elseif ($ageFilter === 2) {
        $whereConditions[] = "is_child = 1";
    }

    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    $contacts = [];
    $contactQuery = "SELECT contact_id, 
                            TRIM(CONCAT(
                                COALESCE(last_name, ''), ', ', 
                                COALESCE(first_name, ''), 
                                IF(COALESCE(n_sufix, '') != '', CONCAT(' ', n_sufix), '')
                            )) AS fullname 
                     FROM contacts 
                     {$whereClause} 
                     ORDER BY last_name ASC, first_name ASC";
    
    $cRes = $db->query($contactQuery);
    if (!$cRes) {
        throw new Exception("Contact Query Failed: " . $db->error);
    }
    $contacts = $cRes->fetch_all(MYSQLI_ASSOC);
    $cRes->free();

    $heads = [];
    $headRes = $db->query("SELECT contact_id, 
                                  TRIM(CONCAT(
                                      COALESCE(last_name, ''), ', ', 
                                      COALESCE(first_name, ''), 
                                      IF(COALESCE(n_sufix, '') != '', CONCAT(' ', n_sufix), '')
                                  )) AS fullname 
                           FROM contacts 
                           WHERE is_head = 1 
                           ORDER BY last_name ASC, first_name ASC");
    if ($headRes) {
        $heads = $headRes->fetch_all(MYSQLI_ASSOC);
        $headRes->free();
    }

    $titles = [];
    $tRes = $db->query("SELECT title_id, titleabr FROM title ORDER BY titleabr ASC");
    if ($tRes) { 
        $titles = $tRes->fetch_all(MYSQLI_ASSOC); 
        $tRes->free(); 
    }

    $marital = [];
    $mRes = $db->query("SELECT marital_id, marital_status FROM marital_status ORDER BY marital_status ASC");
    if ($mRes) { 
        $marital = $mRes->fetch_all(MYSQLI_ASSOC); 
        $mRes->free(); 
    }

    $phonetype = [];
    $pRes = $db->query("SELECT phone_type_id, phone_type_desc FROM phone_type ORDER BY phone_type_desc ASC");
    if ($pRes) { 
        $phonetype = $pRes->fetch_all(MYSQLI_ASSOC); 
        $pRes->free(); 
    }

    $contactTypes = [];
    $ctRes = $db->query("SELECT contact_type_id, contact_desc FROM contact_type ORDER BY contact_desc ASC");
    if ($ctRes) {
        $contactTypes = $ctRes->fetch_all(MYSQLI_ASSOC);
        $ctRes->free();
    }

    $ministryList = [];
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
        $ministryList = $minRes->fetch_all(MYSQLI_ASSOC); 
        $minRes->free(); 
    }

    $roleList = [];
    $rRes = $db->query("SELECT role_id, role_desc FROM roles ORDER BY role_desc ASC");
    if ($rRes) { 
        $roleList = $rRes->fetch_all(MYSQLI_ASSOC); 
        $rRes->free(); 
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $response = json_encode([
        'status'       => 'success',
        'contacts'     => $contacts,
        'heads'        => $heads,
        'titles'       => $titles,
        'marital'      => $marital,
        'phonetype'    => $phonetype,
        'contactTypes' => $contactTypes,
        'ministryList' => $ministryList,
        'roleList'     => $roleList
    ], JSON_INVALID_UTF8_SUBSTITUTE);
    if ($response === false) {
        throw new RuntimeException('Unable to encode contact data.');
    }
    echo $response;
    exit;

} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'status'   => 'error',
        'message'  => 'Database Error: ' . $e->getMessage(),
        'contacts' => []
    ], JSON_INVALID_UTF8_SUBSTITUTE);
}

if (isset($db) && $db instanceof mysqli) {
    mysqli_close($db);
}