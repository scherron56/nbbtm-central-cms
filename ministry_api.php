<?php
// ministry_api.php
require_once 'config/db.php'; // Includes database connection setup

// Buffer output to catch unexpected whitespace or warnings
ob_start();

$action = $_REQUEST['action'] ?? '';

try {
    /* ==========================================================================
       1. GET GROUP TYPES (For populating initial dropdown filters)
       ========================================================================== */
    if ($action === 'get_group_types') {
        $result = $db->query("SELECT min_grp_type_id, min_grp_type_desc FROM min_group_type ORDER BY min_grp_type_desc ASC");
        $types = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];

        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'success', 'data' => $types]);
        exit;
    }

    /* ==========================================================================
       2. GET ALL COMMITTEES (Optionally Filtered by min_comm_type_id)
       ========================================================================== */
    if ($action === 'get_committees') {
        $filter_type = isset($_GET['filter_type']) && $_GET['filter_type'] !== '' ? (int)$_GET['filter_type'] : '';
        
        if ($filter_type !== '') {
            $stmt = $db->prepare("SELECT c.*, g.min_grp_type_desc 
                                  FROM ministry_committee c 
                                  LEFT JOIN min_group_type g ON c.min_comm_type_id = g.min_grp_type_id 
                                  WHERE c.min_comm_type_id = ? 
                                  ORDER BY c.min_comm_name ASC");
            $stmt->bind_param("i", $filter_type);
            $stmt->execute();
            $result = $stmt->get_result();
            $committees = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        } else {
            $result = $db->query("SELECT c.*, g.min_grp_type_desc 
                                  FROM ministry_committee c 
                                  LEFT JOIN min_group_type g ON c.min_comm_type_id = g.min_grp_type_id 
                                  ORDER BY c.min_comm_name ASC");
            $committees = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }

        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'success', 'data' => $committees]);
        exit;
    }

    /* ==========================================================================
       3. GET SINGLE COMMITTEE (For Edit Mode)
       ========================================================================== */
    if ($action === 'get_committee') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM ministry_committee WHERE min_comm_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result ? $result->fetch_assoc() : null;
            $stmt->close();
            
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID specified']);
        }
        exit;
    }

    /* ==========================================================================
       4. CREATE OR UPDATE COMMITTEE
       ========================================================================== */
    if ($action === 'save_committee') {
        $comm_id = !empty($_POST['min_comm_id']) ? (int)$_POST['min_comm_id'] : null;
        $type_id = !empty($_POST['min_comm_type_id']) ? (int)$_POST['min_comm_type_id'] : null;
        $name    = trim($_POST['min_comm_name'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $mission = trim($_POST['mission_purpose'] ?? '');
        $active  = isset($_POST['is_active']) ? 1 : 0;

        if (empty($name) || empty($type_id)) {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Committee Name and Group Type are required']);
            exit;
        }

        if ($comm_id) {
            $stmt = $db->prepare("UPDATE ministry_committee 
                                  SET min_comm_type_id = ?, min_comm_name = ?, description = ?, mission_purpose = ?, is_active = ? 
                                  WHERE min_comm_id = ?");
            $stmt->bind_param("isssii", $type_id, $name, $desc, $mission, $active, $comm_id);
            $stmt->execute();
            $stmt->close();
            
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'success', 'message' => 'Committee updated successfully']);
        } else {
            $stmt = $db->prepare("INSERT INTO ministry_committee (min_comm_type_id, min_comm_name, description, mission_purpose, is_active) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isssi", $type_id, $name, $desc, $mission, $active);
            $stmt->execute();
            $stmt->close();
            
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'success', 'message' => 'Committee added successfully']);
        }
        exit;
    }

    /* ==========================================================================
       5. DELETE COMMITTEE
       ========================================================================== */
    if ($action === 'delete_committee') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM ministry_committee WHERE min_comm_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'success', 'message' => 'Committee deleted successfully']);
        } else {
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Invalid ID provided for deletion']);
        }
        exit;
    }

    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Invalid action requested']);
    exit;

} catch (Throwable $e) { // Catches all PHP 7/8 exceptions and engine errors
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}