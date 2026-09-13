<?php
// ministry_api.php
require_once 'config/db.php';
require_once 'include/auth.php'; // Load authentication & session helpers

ob_start();

$action = $_REQUEST['action'] ?? '';

try {
    /* ==========================================================================
       1. GET GROUP TYPES (Read-Only)
       ========================================================================== */
    if ($action === 'get_group_types') {
        $result = $db->query("SELECT min_grp_type_id, min_grp_type_desc FROM min_group_type ORDER BY min_grp_type_desc ASC");
        $types = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'success', 'data' => $types], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /* ==========================================================================
       2. GET ALL COMMITTEES (Read-Only)
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

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'success', 'data' => $committees], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /* ==========================================================================
       3. GET SINGLE COMMITTEE (Read-Only)
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
       4. GET MEMBERS ROSTER (Read-Only)
       ========================================================================== */
    if ($action === 'get_members') {
        $min_comm_id = isset($_GET['min_comm_id']) ? (int)$_GET['min_comm_id'] : 0;
        
        if ($min_comm_id > 0) {
            $stmt = $db->prepare("
                SELECT c.first_name, c.last_name, c.phone_1, c.c_email, r.role_desc 
                FROM member_alliance mm
                JOIN contacts c ON mm.contact_id = c.contact_id
                LEFT JOIN roles r ON mm.role_id = r.role_id
                WHERE mm.min_comm_id = ? AND mm.is_active = 1
                ORDER BY c.last_name ASC, c.first_name ASC
            ");
            $stmt->bind_param("i", $min_comm_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $members = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();

            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'success', 'data' => $members], JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Invalid Ministry / Committee ID provided'], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /* ==========================================================================
       5. CREATE OR UPDATE COMMITTEE (Admin Only)
       ========================================================================== */
    if ($action === 'save_committee') {
        requireAdmin(); // Enforce admin permissions

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
       6. DELETE COMMITTEE (Admin Only)
       ========================================================================== */
    if ($action === 'delete_committee') {
        requireAdmin(); // Enforce admin permissions

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

} catch (Throwable $e) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>