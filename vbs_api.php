<?php
// vbs_api.php
header('Content-Type: application/json; charset=utf-8');
require_once "config/db.php";
require_once "include/auth.php";

function sendVbsJson(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$action = $_REQUEST['action'] ?? '';

// Enforce admin privileges on write API actions
if (in_array($action, ['save_student', 'delete_student', 'toggle_attendance'])) {
    requireAdmin();
}

try {
    switch ($action) {

        // READ: Sessions list
        case 'fetch_sessions':
            $stmt = $db->prepare("SELECT vbs_sessions_id, vbs_year, vbs_theme, vbs_start_date, vbs_end_date, IFNULL(vbs_sessions_completed, 0) AS vbs_sessions_completed FROM vbs_sessions ORDER BY vbs_year DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $sessions = [];
            while ($row = $result->fetch_assoc()) {
                $sessions[] = $row;
            }
            sendVbsJson(['success' => true, 'data' => $sessions]);

        // READ: Classes list
        case 'fetch_classes':
            $vbs_sessions_id = intval($_GET['vbs_sessions_id'] ?? 0);
 
            $sql = "
                SELECT 
                    vc.vbs_class_id, 
                    vc.vbs_class_session_id, 
                    vc.vbs_class_desc,
                    vc.vbs_class_age_start,
                    vc.vbs_class_age_end,
                    vc.vbs_class_teacher_id,
                    CONCAT_WS(' ', c_teacher.first_name, c_teacher.last_name) AS teacher_name
                FROM vbs_classes vc
                LEFT JOIN contacts c_teacher ON vc.vbs_class_teacher_id = c_teacher.contact_id
            ";

            if ($vbs_sessions_id > 0) {
                $sql .= " WHERE vc.vbs_class_session_id = ? ";
            }
            $sql .= " ORDER BY vc.vbs_class_desc ASC";

            $stmt = $db->prepare($sql);
            if ($vbs_sessions_id > 0) {
                $stmt->bind_param("i", $vbs_sessions_id);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $classes = [];
            while ($row = $result->fetch_assoc()) {
                $classes[] = $row;
            }
            sendVbsJson(['success' => true, 'data' => $classes]);

        // READ: Roster with Unique Students & Days Attended Count
        case 'fetch_roster':
            $session_filter = !empty($_GET['vbs_sessions_id']) ? intval($_GET['vbs_sessions_id']) : 0;
            $target_date    = !empty($_GET['checkin_date']) ? trim($_GET['checkin_date']) : null;

            if (!empty($target_date)) {
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
                        vses.vbs_start_date,
                        vses.vbs_end_date,
                        IFNULL(vses.vbs_sessions_completed, 0) AS vbs_sessions_completed,
                        vc.vbs_class_id,
                        vc.vbs_class_desc,
                        vc.vbs_class_teacher_id,
                        CONCAT_WS(' ', c_teacher.first_name, c_teacher.last_name) AS teacher_name,
                        c_child.last_name,
                        c_child.first_name,
                        CONCAT_WS(', ', c_child.last_name, c_child.first_name) AS student_full_name_formatted,
                        CONCAT_WS(' ', c_child.first_name, c_child.last_name) AS student_display_name,
                        MAX(IF(va.attendance_id IS NOT NULL AND va.status = 'present' AND va.checkout_time IS NULL, 1, 0)) AS is_checked_in,
                        (
                            SELECT COUNT(DISTINCT att.checkin_date)
                            FROM vbs_attendance att
                            WHERE att.student_id = vs.contact_id 
                              AND att.vbs_session_id = vs.vbs_sessions_id
                              AND att.status = 'present'
                        ) AS days_attended
                    FROM vbs_students vs
                    JOIN contacts c_child ON vs.contact_id = c_child.contact_id
                    LEFT JOIN vbs_classes vc ON vs.class_id = vc.vbs_class_id
                    LEFT JOIN contacts c_teacher ON vc.vbs_class_teacher_id = c_teacher.contact_id
                    LEFT JOIN vbs_sessions vses ON vs.vbs_sessions_id = vses.vbs_sessions_id
                    LEFT JOIN vbs_attendance va 
                        ON vs.contact_id = va.student_id 
                        AND vs.vbs_sessions_id = va.vbs_session_id
                        AND (va.checkin_date = ? OR DATE(va.checkin_time) = ?)
                ";

                if ($session_filter > 0) {
                    $sql .= " WHERE vs.vbs_sessions_id = ? ";
                }

                $sql .= " GROUP BY vs.vbs_id ORDER BY vc.vbs_class_desc ASC, c_child.last_name ASC, c_child.first_name ASC";

                $stmt = $db->prepare($sql);
                if ($session_filter > 0) {
                    $stmt->bind_param("ssi", $target_date, $target_date, $session_filter);
                } else {
                    $stmt->bind_param("ss", $target_date, $target_date);
                }
            } else {
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
                        vses.vbs_start_date,
                        vses.vbs_end_date,
                        IFNULL(vses.vbs_sessions_completed, 0) AS vbs_sessions_completed,
                        vc.vbs_class_id,
                        vc.vbs_class_desc,
                        vc.vbs_class_teacher_id,
                        CONCAT_WS(' ', c_teacher.first_name, c_teacher.last_name) AS teacher_name,
                        c_child.last_name,
                        c_child.first_name,
                        CONCAT_WS(', ', c_child.last_name, c_child.first_name) AS student_full_name_formatted,
                        CONCAT_WS(' ', c_child.first_name, c_child.last_name) AS student_display_name,
                        MAX(IF(va.attendance_id IS NOT NULL AND va.status = 'present' AND va.checkout_time IS NULL, 1, 0)) AS is_checked_in,
                        (
                            SELECT COUNT(DISTINCT att.checkin_date)
                            FROM vbs_attendance att
                            WHERE att.student_id = vs.contact_id 
                              AND att.vbs_session_id = vs.vbs_sessions_id
                              AND att.status = 'present'
                        ) AS days_attended
                    FROM vbs_students vs
                    JOIN contacts c_child ON vs.contact_id = c_child.contact_id
                    LEFT JOIN vbs_classes vc ON vs.class_id = vc.vbs_class_id
                    LEFT JOIN contacts c_teacher ON vc.vbs_class_teacher_id = c_teacher.contact_id
                    LEFT JOIN vbs_sessions vses ON vs.vbs_sessions_id = vses.vbs_sessions_id
                    LEFT JOIN vbs_attendance va 
                        ON vs.contact_id = va.student_id 
                        AND vs.vbs_sessions_id = va.vbs_session_id
                        AND (
                            (vses.vbs_start_date IS NOT NULL AND vses.vbs_end_date IS NOT NULL AND va.checkin_date BETWEEN vses.vbs_start_date AND vses.vbs_end_date)
                            OR (vses.vbs_start_date IS NULL AND va.checkin_date = CURDATE())
                        )
                ";

                if ($session_filter > 0) {
                    $sql .= " WHERE vs.vbs_sessions_id = ? ";
                }

                $sql .= " GROUP BY vs.vbs_id ORDER BY vc.vbs_class_desc ASC, c_child.last_name ASC, c_child.first_name ASC";

                $stmt = $db->prepare($sql);
                if ($session_filter > 0) {
                    $stmt->bind_param("i", $session_filter);
                }
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            sendVbsJson(['success' => true, 'data' => $data]);

        // READ SINGLE STUDENT
        case 'get_student':
            $vbs_id = intval($_GET['vbs_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM vbs_students WHERE vbs_id = ?");
            $stmt->bind_param("i", $vbs_id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        // SAVE STUDENT
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

            $chkSes = $db->prepare("SELECT vbs_sessions_completed FROM vbs_sessions WHERE vbs_sessions_id = ?");
            $chkSes->bind_param("i", $vbs_sessions_id);
            $chkSes->execute();
            $sesRes = $chkSes->get_result()->fetch_assoc();
            if ($sesRes && intval($sesRes['vbs_sessions_completed']) === 1) {
                echo json_encode(['success' => false, 'message' => 'This VBS session is completed and read-only.']);
                exit;
            }

            if ($vbs_id) {
                $stmt = $db->prepare("
                    UPDATE vbs_students 
                    SET vbs_sessions_id = ?, contact_id = ?, class_id = ?, allergies = ?, food_restrictions = ?, medical_notes = ? 
                    WHERE vbs_id = ?
                ");
                $stmt->bind_param("iiisssi", $vbs_sessions_id, $contact_id, $class_id, $allergies, $food, $medical, $vbs_id);
            } else {
                $checkStmt = $db->prepare("SELECT vbs_id FROM vbs_students WHERE vbs_sessions_id = ? AND contact_id = ?");
                $checkStmt->bind_param("ii", $vbs_sessions_id, $contact_id);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
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

        // DELETE STUDENT
        case 'delete_student':
            $vbs_id = intval($_POST['vbs_id'] ?? 0);
            
            $stmtChk = $db->prepare("SELECT vs.vbs_sessions_id, s.vbs_sessions_completed FROM vbs_students vs JOIN vbs_sessions s ON vs.vbs_sessions_id = s.vbs_sessions_id WHERE vs.vbs_id = ?");
            $stmtChk->bind_param("i", $vbs_id);
            $stmtChk->execute();
            $chkRow = $stmtChk->get_result()->fetch_assoc();
            if ($chkRow && intval($chkRow['vbs_sessions_completed']) === 1) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete record: session is completed and read-only.']);
                exit;
            }

            $stmt = $db->prepare("DELETE FROM vbs_students WHERE vbs_id = ?");
            $stmt->bind_param("i", $vbs_id);
            $stmt->execute();
            echo json_encode(['success' => true]);
            break;

        // TOGGLE ATTENDANCE
        case 'toggle_attendance':
            $student_id     = intval($_POST['student_id'] ?? 0);
            $vbs_session_id = intval($_POST['vbs_session_id'] ?? 0);
            $att_action     = $_POST['attendance_action'] ?? '';
            $checkin_date   = !empty($_POST['checkin_date']) ? $_POST['checkin_date'] : null;

            if (!$student_id || !$vbs_session_id) {
                echo json_encode(['success' => false, 'message' => 'Invalid Parameters']);
                exit;
            }

            $chkSes = $db->prepare("SELECT vbs_start_date, vbs_end_date, vbs_sessions_completed FROM vbs_sessions WHERE vbs_sessions_id = ?");
            $chkSes->bind_param("i", $vbs_session_id);
            $chkSes->execute();
            $sesRes = $chkSes->get_result()->fetch_assoc();

            if ($sesRes && intval($sesRes['vbs_sessions_completed']) === 1) {
                echo json_encode(['success' => false, 'message' => 'Cannot update attendance: session is completed and read-only.']);
                exit;
            }

            if (empty($checkin_date)) {
                $todayStr = date('Y-m-d');
                $startDate = $sesRes['vbs_start_date'] ?? null;
                $endDate   = $sesRes['vbs_end_date'] ?? null;

                if ($startDate && $endDate && ($todayStr >= $startDate && $todayStr <= $endDate)) {
                    $checkin_date = $todayStr;
                } else {
                    $checkin_date = $startDate ? $startDate : $todayStr;
                }
            }

            if ($att_action === 'checkin') {
                $stmt = $db->prepare("
                    INSERT INTO vbs_attendance (vbs_session_id, student_id, checkin_date, checkin_time, status, checkout_time) 
                    VALUES (?, ?, ?, NOW(), 'present', NULL)
                    ON DUPLICATE KEY UPDATE status = 'present', checkin_time = NOW(), checkout_time = NULL
                ");
                $stmt->bind_param("iis", $vbs_session_id, $student_id, $checkin_date);
            } else {
                $stmt = $db->prepare("
                    UPDATE vbs_attendance 
                    SET checkout_time = NOW() 
                    WHERE student_id = ? AND vbs_session_id = ? AND (checkin_date = ? OR DATE(checkin_time) = ?)
                ");
                $stmt->bind_param("iiss", $student_id, $vbs_session_id, $checkin_date, $checkin_date);
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
