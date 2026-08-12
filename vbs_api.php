<?php
header('Content-Type: application/json; charset=utf-8');
require_once "config/db.php";
require_once "include/auth.php";

$action = $_REQUEST['action'] ?? '';
$today  = date('Y-m-d');

// Enforce admin privileges on create/update/delete API actions
if (in_array($action, ['save_student', 'delete_student', 'toggle_attendance'])) {
    requireAdmin();
}

try {
    switch ($action) {

        // READ: Sessions list for dropdown filter
        case 'fetch_sessions':
            $stmt = $db->prepare("SELECT vbs_sessions_id, vbs_year, vbs_theme FROM vbs_sessions ORDER BY vbs_year DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $sessions = [];
            while ($row = $result->fetch_assoc()) {
                $sessions[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $sessions]);
            break;

        // READ: Classes list for a given vbs_sessions_id
        case 'fetch_classes':
            $vbs_sessions_id = intval($_GET['vbs_sessions_id'] ?? 0);
 
            if ($vbs_sessions_id > 0) {
                $stmt = $db->prepare("SELECT vbs_class_id, vbs_class_session_id, vbs_class_desc FROM vbs_classes WHERE vbs_class_session_id = ? ORDER BY vbs_class_desc ASC");
                $stmt->bind_param("i", $vbs_sessions_id);
            } else {
                $stmt = $db->prepare("SELECT vbs_class_id, vbs_class_session_id, vbs_class_desc FROM vbs_classes ORDER BY vbs_class_desc ASC");
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $classes = [];
            while ($row = $result->fetch_assoc()) {
                $classes[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $classes]);
            break;

        // READ: Roster + Daily Attendance Status
        case 'fetch_roster':
            $session_filter = !empty($_GET['vbs_sessions_id']) ? intval($_GET['vbs_sessions_id']) : null;
            $target_date    = !empty($_GET['checkin_date']) ? $_GET['checkin_date'] : $today;

            $sql = "
                SELECT 
                    vs.vbs_id,
                    vs.vbs_sessions_id,
                    vs.contact_id,
                    vs.class_id,
                    vs.allergies,
                    vs.food_restrictions,
                    vs.medical_notes,
                    vses.vbs_year,
                    vc.vbs_class_id,
                    vc.vbs_class_desc,
                    c_child.last_name,
                    c_child.first_name,
                    CONCAT(c_child.last_name, ', ', c_child.first_name) AS student_full_name_formatted,
                    CONCAT(c_child.first_name, ' ', c_child.last_name) AS student_display_name,
                    IF(va.attendance_id IS NOT NULL AND va.status = 'present' AND va.checkout_time IS NULL, 1, 0) AS is_checked_in,
                    va.checkin_time,
                    va.checkout_time
                FROM vbs_students vs
                JOIN contacts c_child ON vs.contact_id = c_child.contact_id
                LEFT JOIN vbs_classes vc ON vs.class_id = vc.vbs_class_id
                LEFT JOIN vbs_sessions vses ON vs.vbs_sessions_id = vses.vbs_sessions_id
                LEFT JOIN vbs_attendance va 
                    ON vs.contact_id = va.student_id 
                    AND vs.vbs_sessions_id = va.vbs_session_id
                    AND va.checkin_date = ?
            ";

            if ($session_filter) {
                $sql .= " WHERE vs.vbs_sessions_id = ? ";
            }

            $sql .= " ORDER BY c_child.last_name ASC, c_child.first_name ASC";

            $stmt = $db->prepare($sql);
            if ($session_filter) {
                $stmt->bind_param("si", $target_date, $session_filter);
            } else {
                $stmt->bind_param("s", $target_date);
            }
            
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
            $stmt = $db->prepare("SELECT * FROM vbs_students WHERE vbs_id = ?");
            $stmt->bind_param("i", $vbs_id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        // CREATE & UPDATE STUDENT REGISTRATION (Admin Only)
        case 'save_student':
            $vbs_id          = !empty($_POST['vbs_id']) ? intval($_POST['vbs_id']) : null;
            $vbs_sessions_id = intval($_POST['vbs_sessions_id'] ?? 0);
            $contact_id      = intval($_POST['contact_id'] ?? 0);
            $class_id        = intval($_POST['vbs_class_id'] ?? $_POST['class_id'] ?? 0);
            $allergies       = trim($_POST['allergies'] ?? '');
            $food            = trim($_POST['food_restrictions'] ?? '');
            $medical         = trim($_POST['medical_notes'] ?? '');

            if (!$vbs_sessions_id || !$contact_id || !$class_id) {
                echo json_encode(['success' => false, 'message' => 'Required fields (Session, Student, Class) are missing.']);
                exit;
            }

            if ($vbs_id) {
                // UPDATE RECORD
                $stmt = $db->prepare("
                    UPDATE vbs_students 
                    SET vbs_sessions_id = ?, contact_id = ?, class_id = ?, allergies = ?, food_restrictions = ?, medical_notes = ? 
                    WHERE vbs_id = ?
                ");
                $stmt->bind_param("iiisssi", $vbs_sessions_id, $contact_id, $class_id, $allergies, $food, $medical, $vbs_id);
            } else {
                // CREATE NEW RECORD: Prevent Duplicate Student Registration per Session
                $checkStmt = $db->prepare("SELECT vbs_id FROM vbs_students WHERE vbs_sessions_id = ? AND contact_id = ?");
                $checkStmt->bind_param("ii", $vbs_sessions_id, $contact_id);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult->num_rows > 0) {
                    $checkStmt->close();
                    echo json_encode(['success' => false, 'message' => 'This student is already registered for the selected VBS session.']);
                    exit;
                }
                $checkStmt->close();

                $stmt = $db->prepare("
                    INSERT INTO vbs_students (vbs_sessions_id, contact_id, class_id, allergies, food_restrictions, medical_notes) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("iiisss", $vbs_sessions_id, $contact_id, $class_id, $allergies, $food, $medical);
            }
            
            $stmt->execute();
            echo json_encode(['success' => true]);
            break;

        // DELETE STUDENT REGISTRATION (Admin Only)
        case 'delete_student':
            $vbs_id = intval($_POST['vbs_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM vbs_students WHERE vbs_id = ?");
            $stmt->bind_param("i", $vbs_id);
            $stmt->execute();
            echo json_encode(['success' => true]);
            break;

        // CHECK-IN / CHECK-OUT TOGGLE (Admin Only)
        case 'toggle_attendance':
            $student_id     = intval($_POST['student_id'] ?? 0);
            $vbs_session_id = intval($_POST['vbs_session_id'] ?? 0);
            $att_action     = $_POST['attendance_action'] ?? '';
            $checkin_date   = !empty($_POST['checkin_date']) ? $_POST['checkin_date'] : $today;

            if (!$student_id || !$vbs_session_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid Parameters']);
                exit;
            }

            if ($att_action === 'checkin') {
                $stmt = $db->prepare("
                    INSERT INTO vbs_attendance (vbs_session_id, student_id, checkin_date, checkin_time, status, checkout_time) 
                    VALUES (?, ?, ?, CURRENT_TIMESTAMP, 'present', NULL)
                    ON DUPLICATE KEY UPDATE status = 'present', checkin_time = CURRENT_TIMESTAMP, checkout_time = NULL
                ");
                $stmt->bind_param("iis", $vbs_session_id, $student_id, $checkin_date);
            } else {
                $stmt = $db->prepare("
                    UPDATE vbs_attendance 
                    SET checkout_time = CURRENT_TIMESTAMP 
                    WHERE student_id = ? AND vbs_session_id = ? AND checkin_date = ?
                ");
                $stmt->bind_param("iis", $student_id, $vbs_session_id, $checkin_date);
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