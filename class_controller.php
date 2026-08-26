<?php
// class_controller.php

// 1. Prevent HTML error messages from corrupting JSON output
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// 2. Resolve the config directory properly
require_once __DIR__ . '/config/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        case 'get_dropdowns':
            $response = [
                'success'  => false,
                'sessions' => [],
                'teachers' => []
            ];

            // Fetch VBS Sessions
            $session_query = "SELECT vbs_sessions_id, vbs_year, vbs_theme, COALESCE(vbs_sessions_completed, 0) AS vbs_sessions_completed 
                              FROM vbs_sessions 
                              ORDER BY vbs_year DESC";
            
            if ($session_result = $db->query($session_query)) {
                while ($row = $session_result->fetch_assoc()) {
                    $response['sessions'][] = $row;
                }
                $session_result->free();
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to fetch sessions: ' . $db->error
                ]);
                exit;
            }

            // Fetch Teachers
            $teacher_query = "SELECT contact_id AS vbs_class_teacher_id, CONCAT(first_name, ' ', last_name) AS teacher_name 
                              FROM contacts 
                              ORDER BY last_name ASC, first_name ASC";

            if ($teacher_result = $db->query($teacher_query)) {
                while ($row = $teacher_result->fetch_assoc()) {
                    $response['teachers'][] = $row;
                }
                $teacher_result->free();
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to fetch teachers: ' . $db->error
                ]);
                exit;
            }

            $response['success'] = true;
            echo json_encode($response);
            exit;

        case 'get_session':
            $vbs_sessions_id = intval($_GET['vbs_sessions_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM vbs_sessions WHERE vbs_sessions_id = ?");
            $stmt->bind_param("i", $vbs_sessions_id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            echo json_encode(['success' => true, 'data' => $data]);
            exit;

        case 'add_session':
            $vbs_theme_title = $_POST['vbs_theme_title'] ?? 'New Session';
            $current_year = date('Y');
            
            $stmt = $db->prepare("INSERT INTO vbs_sessions (vbs_year, vbs_theme, vbs_sessions_completed) VALUES (?, ?, 0)");
            $stmt->bind_param("ss", $current_year, $vbs_theme_title);
            $stmt->execute();
            $new_id = $db->insert_id;
            
            echo json_encode([
                'success' => true, 
                'new_id' => $new_id, 
                'combined_name' => $current_year . ' - ' . $vbs_theme_title
            ]);
            exit;

        case 'save_session':
            $vbs_sessions_id = intval($_POST['vbs_sessions_id'] ?? 0);
            $vbs_year = $_POST['vbs_year'] ?? '';
            $start = $_POST['vbs_session_start'] ?? null;
            $end = $_POST['vbs_session_end'] ?? null;
            $theme = $_POST['vbs_theme'] ?? '';
            $scripture = $_POST['vbs_theme_scripture'] ?? '';

            $stmt = $db->prepare("UPDATE vbs_sessions SET vbs_year = ?, vbs_start_date = ?, vbs_end_date = ?, vbs_theme = ?, vbs_theme_scripture = ? WHERE vbs_sessions_id = ?");
            $stmt->bind_param("sssssi", $vbs_year, $start, $end, $theme, $scripture, $vbs_sessions_id);
            $stmt->execute();

            echo json_encode(['success' => true, 'vbs_sessions_id' => $vbs_sessions_id]);
            exit;

        case 'read':
            $vbs_sessions_id = intval($_GET['vbs_sessions_id'] ?? 0);
            $sql = "SELECT vc.*, CONCAT_WS(' ', c_teacher.first_name, c_teacher.last_name) AS teacher_name 
                    FROM vbs_classes vc 
                    LEFT JOIN contacts c_teacher ON vc.vbs_class_teacher_id = c_teacher.contact_id";
            if ($vbs_sessions_id > 0) {
                $sql .= " WHERE vc.vbs_class_session_id = ?";
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
            echo json_encode($classes);
            exit;

        case 'create':
        case 'update':
            $class_id = intval($_POST['vbs_class_id'] ?? 0);
            $session_id = intval($_POST['vbs_class_session_id'] ?? 0);
            $desc = $_POST['vbs_class_desc'] ?? '';
            $start_age = intval($_POST['vbs_class_age_start'] ?? 0);
            $end_age = intval($_POST['vbs_class_age_end'] ?? 0);
            $teacher_id = !empty($_POST['vbs_class_teacher_id']) ? intval($_POST['vbs_class_teacher_id']) : null;

            if ($action === 'update' && $class_id > 0) {
                $stmt = $db->prepare("UPDATE vbs_classes SET vbs_class_session_id = ?, vbs_class_desc = ?, vbs_class_age_start = ?, vbs_class_age_end = ?, vbs_class_teacher_id = ? WHERE vbs_class_id = ?");
                $stmt->bind_param("isiiii", $session_id, $desc, $start_age, $end_age, $teacher_id, $class_id);
            } else {
                $stmt = $db->prepare("INSERT INTO vbs_classes (vbs_class_session_id, vbs_class_desc, vbs_class_age_start, vbs_class_age_end, vbs_class_teacher_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("isiii", $session_id, $desc, $start_age, $end_age, $teacher_id);
            }
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;

        case 'delete':
            $class_id = intval($_POST['vbs_class_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM vbs_classes WHERE vbs_class_id = ?");
            $stmt->bind_param("i", $class_id);
            $stmt->execute();
            echo json_encode(['success' => true]);
            exit;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid or missing action.'
            ]);
            exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}