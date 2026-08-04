<?php
header('Content-Type: application/json');
require_once "config/db.php";

$action = $_REQUEST['action'] ?? '';
$today  = date('Y-m-d');

try {
    switch ($action) {

        // READ: Roster + Daily Attendance Status
        case 'fetch_roster':
            $sql = "
                SELECT 
                    vs.vbs_id,
                    vs.vbs_sessions_id,
                    vs.contact_id,
                    vs.parent_id,
                    vs.class_id,
                    vs.allergies,
                    vs.food_restrictions,
                    vs.medical_notes,
                    CONCAT(c_child.first_name, ' ', c_child.last_name) AS child_name,
                    CONCAT(c_parent.first_name, ' ', c_parent.last_name) AS parent_name,
                    c_parent.phone_1 AS parent_phone,
                    IF(va.attendance_id IS NOT NULL AND va.status = 'present' AND va.checkout_time IS NULL, 1, 0) AS is_checked_in
                FROM vbs_students vs
                JOIN contacts c_child ON vs.contact_id = c_child.contact_id
                LEFT JOIN contacts c_parent ON vs.parent_id = c_parent.contact_id
                LEFT JOIN vbs_attendance va 
                    ON vs.contact_id = va.student_id 
                    AND vs.vbs_sessions_id = va.vbs_session_id
                    AND va.checkin_date = ?
                ORDER BY c_child.last_name, c_child.first_name
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        // READ SINGLE STUDENT
        case 'get_student':
            $vbs_id = intval($_GET['vbs_id'] ?? 0);
            $stmt = $conn->prepare("SELECT * FROM vbs_students WHERE vbs_id = ?");
            $stmt->bind_param("i", $vbs_id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        // CREATE & UPDATE
        case 'save_student':
            $vbs_id          = !empty($_POST['vbs_id']) ? intval($_POST['vbs_id']) : null;
            $vbs_sessions_id = intval($_POST['vbs_sessions_id'] ?? 0);
            $contact_id      = intval($_POST['contact_id'] ?? 0);
            $parent_id       = intval($_POST['parent_id'] ?? 0);
            $class_id        = intval($_POST['class_id'] ?? 0);
            $allergies       = trim($_POST['allergies'] ?? '');
            $food            = trim($_POST['food_restrictions'] ?? '');
            $medical         = trim($_POST['medical_notes'] ?? '');

            if (!$vbs_sessions_id || !$contact_id || !$parent_id || !$class_id) {
                echo json_encode(['success' => false, 'message' => 'Required IDs are missing.']);
                exit;
            }

            if ($vbs_id) {
                // UPDATE
                $stmt = $conn->prepare("
                    UPDATE vbs_students 
                    SET vbs_sessions_id = ?, contact_id = ?, parent_id = ?, class_id = ?, allergies = ?, food_restrictions = ?, medical_notes = ? 
                    WHERE vbs_id = ?
                ");
                $stmt->bind_param("iiiisssi", $vbs_sessions_id, $contact_id, $parent_id, $class_id, $allergies, $food, $medical, $vbs_id);
            } else {
                // INSERT
                $stmt = $conn->prepare("
                    INSERT INTO vbs_students (vbs_sessions_id, contact_id, parent_id, class_id, allergies, food_restrictions, medical_notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iiiisss", $vbs_sessions_id, $contact_id, $parent_id, $class_id, $allergies, $food, $medical);
            }
            
            $stmt->execute();
            echo json_encode(['success' => true]);
            break;

        // DELETE
        case 'delete_student':
            $vbs_id = intval($_POST['vbs_id'] ?? 0);
            $stmt = $conn->prepare("DELETE FROM vbs_students WHERE vbs_id = ?");
            $stmt->bind_param("i", $vbs_id);
            $stmt->execute();
            echo json_encode(['success' => true]);
            break;

        // CHECK-IN / CHECK-OUT TOGGLE
        case 'toggle_attendance':
            $student_id     = intval($_POST['student_id'] ?? 0);
            $vbs_session_id = intval($_POST['vbs_session_id'] ?? 0);
            $att_action     = $_POST['attendance_action'] ?? '';

            if (!$student_id || !$vbs_session_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid Parameters']);
                exit;
            }

            if ($att_action === 'checkin') {
                $stmt = $conn->prepare("
                    INSERT INTO vbs_attendance (vbs_session_id, student_id, checkin_date, checkin_time, status, checkout_time) 
                    VALUES (?, ?, ?, CURRENT_TIMESTAMP, 'present', NULL)
                    ON DUPLICATE KEY UPDATE status = 'present', checkin_time = CURRENT_TIMESTAMP, checkout_time = NULL
                ");
                $stmt->bind_param("iis", $vbs_session_id, $student_id, $today);
            } else {
                $stmt = $conn->prepare("
                    UPDATE vbs_attendance 
                    SET checkout_time = CURRENT_TIMESTAMP 
                    WHERE student_id = ? AND vbs_session_id = ? AND checkin_date = ?
                ");
                $stmt->bind_param("iis", $student_id, $vbs_session_id, $today);
            }

            $stmt->execute();
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action endpoint.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>