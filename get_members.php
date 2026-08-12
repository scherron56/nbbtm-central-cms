<?php
header('Content-Type: application/json; charset=utf-8');

// Buffer output to catch unexpected whitespace or warnings
ob_start();

// Include database connection (Uses $db instantiated in config/db.php)
require_once 'config/db.php'; 

$action = $_GET['action'] ?? '';

try {
    /* ==========================================================================
       1. GET GROUP TYPES (Populates group type filter dropdown)
       ========================================================================== */
    if ($action === 'get_group_types') {
        $query = "SELECT min_grp_type_id, min_grp_type_desc 
                  FROM min_group_type 
                  ORDER BY min_grp_type_desc ASC";
        
        $result = $db->query($query);
        $types = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $types[] = $row;
            }
        }

        ob_clean();
        echo json_encode(['status' => 'success', 'data' => $types]);
        exit;
    }

    /* ==========================================================================
       2. GET COMMITTEES / MINISTRIES (Filtered by Group Type if provided)
       ========================================================================== */
    if ($action === 'get_committees') {
        $filter_type = isset($_GET['filter_type']) && $_GET['filter_type'] !== '' ? (int)$_GET['filter_type'] : 0;
        
        if ($filter_type > 0) {
            $stmt = $db->prepare("SELECT min_comm_id, min_comm_name 
                                  FROM ministry_committee 
                                  WHERE min_comm_type_id = ? 
                                  ORDER BY min_comm_name ASC");
            $stmt->bind_param("i", $filter_type);
            $stmt->execute();
            $result = $stmt->get_result();
            $committees = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        } else {
            $result = $db->query("SELECT min_comm_id, min_comm_name 
                                  FROM ministry_committee 
                                  ORDER BY min_comm_name ASC");
            $committees = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }

        ob_clean();
        echo json_encode(['status' => 'success', 'data' => $committees]);
        exit;
    }

    /* ==========================================================================
       3. GET MEMBERS FOR A SPECIFIC COMMITTEE (With Role Description)
       ========================================================================== */
    if ($action === 'get_members') {
        $min_comm_id = isset($_GET['min_comm_id']) ? (int)$_GET['min_comm_id'] : 0;

        if ($min_comm_id <= 0) {
            ob_clean();
            echo json_encode(['status' => 'error', 'message' => 'Invalid Ministry ID specified']);
            exit;
        }

        // Retrieve members joined with contacts and roles tables
        $query = "SELECT 
                    c.first_name, 
                    c.last_name, 
                    c.phone_1, 
                    c.c_email, 
                    r.role_desc
                  FROM member_alliance ma
                  JOIN contacts c ON ma.contact_id = c.contact_id
                  LEFT JOIN roles r ON ma.role_id = r.role_id
                  WHERE ma.min_comm_id = ?
                  ORDER BY c.last_name ASC, c.first_name ASC";

        $stmt = $db->prepare($query);
        $stmt->bind_param("i", $min_comm_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $members = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        ob_clean();
        echo json_encode(['status' => 'success', 'data' => $members]);
        exit;
    }

    // Fallback for invalid action
    ob_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid action requested']);
    exit;

} catch (Throwable $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}