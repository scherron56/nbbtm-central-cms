<?php
// PLACE THIS AT THE VERY TOP OF class_controller.php FOR DEBUGGING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure MySQLi throws catchable errors instead of silently failing
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json; charset=utf-8');

// Database Setup Configurations
require_once 'config/db.php';
// Route structural parsing targeting action operations map
// //testing class
// const newClass = new VBSClass(
//   vbs_class_id: 1,
//   vbs_sessions_id: 3,
//   vbs_class_desc: "Kindergarten Explorers",
//   vbs_class_age_start: 5,
//   vbs_class_age_end: 6,
//   vbs_class_teacher_id: 12,
//   teacher_name: "John Doe"
// );


$action = $_REQUEST['action'] ?? '';

// $action = 'create'; // Default action for testing purposes; replace with dynamic routing in production

switch ($action) {
    case 'add_session':
        $theme = !empty($_POST['vbs_theme_title']) ? trim($_POST['vbs_theme_title']) : 'New Session';
        $current_year = date('Y');

        // Insert a new session row into vbs_sessions
        $stmt = $db->prepare("INSERT INTO vbs_sessions (vbs_year, vbs_theme) VALUES (?, ?)");
        $stmt->bind_param("ss", $current_year, $theme);

        if ($stmt->execute()) {
            $new_id = $stmt->insert_id;
            echo json_encode([
                "success" => true,
                "new_id" => $new_id,
                "combined_name" => $current_year . " - " . $theme
            ]);
        } else {
            echo json_encode(["success" => false, "message" => $stmt->error]);
        }
        $stmt->close();
        break;
    // Fetch individual Session metadata
    case 'get_session':
        $session_id = !empty($_REQUEST['vbs_sessions_id']) ? intval($_REQUEST['vbs_sessions_id']) : 0;

        if ($session_id > 0) {
            $stmt = $db->prepare("SELECT vbs_sessions_id, vbs_year, DATE_FORMAT(vbs_start_date, '%Y-%m-%d') as vbs_start_date, DATE_FORMAT(vbs_end_date, '%Y-%m-%d') as vbs_end_date, vbs_theme, vbs_theme_scripture FROM vbs_sessions WHERE vbs_sessions_id = ?");
            $stmt->bind_param("i", $session_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                echo json_encode(["success" => true, "data" => $row]);
            } else {
                echo json_encode(["success" => false, "message" => "Session record not found."]);
            }
            $stmt->close();
        } else {
            echo json_encode(["success" => false, "message" => "Invalid Session ID requested."]);
        }
        break;

    // Create or Update Session metadata
    case 'save_session':
        // Check all potential input names
        $session_id = 0;
        if (!empty($_POST['vbs_sessions_id'])) {
            $session_id = intval($_POST['vbs_sessions_id']);
        } elseif (!empty($_POST['vbs_class_session_id'])) {
            $session_id = intval($_POST['vbs_class_session_id']);
        } elseif (!empty($_POST['vbs_sessions_id'])) {
            $session_id = intval($_POST['vbs_sessions_id']);
        }

        $vbs_year = trim($_POST['vbs_year']);
        $start_date = !empty($_POST['vbs_session_start']) ? $_POST['vbs_session_start'] : null;
        $end_date = !empty($_POST['vbs_session_end']) ? $_POST['vbs_session_end'] : null;
        $theme = trim($_POST['vbs_theme']);
        $scripture = trim($_POST['vbs_theme_scripture']);

        if ($session_id > 0) {
            $stmt = $db->prepare("UPDATE vbs_sessions SET vbs_year = ?, vbs_session_start = ?, vbs_session_end = ?, vbs_theme = ?, vbs_theme_scripture = ? WHERE vbs_sessions_id = ?");
            $stmt->bind_param("sssssi", $vbs_year, $start_date, $end_date, $theme, $scripture, $session_id);
        } else {
            $stmt = $db->prepare("INSERT INTO vbs_sessions (vbs_year, vbs_session_start, vbs_session_end, vbs_theme, vbs_theme_scripture) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $vbs_year, $start_date, $end_date, $theme, $scripture);
        }

        if ($stmt->execute()) {
            $new_id = $session_id > 0 ? $session_id : $stmt->insert_id;
            echo json_encode(["success" => true, "vbs_sessions_id" => $new_id]);
        } else {
            echo json_encode(["success" => false, "message" => $stmt->error]);
        }
        $stmt->close();
        break;
    case 'get_dropdowns':
        $dropdowns = [
            'sessions' => [],
            'teachers' => []
        ];

        // Fetch Sessions
        $session_query = "SELECT vbs_sessions_id, vbs_year FROM vbs_sessions ORDER BY vbs_year ASC";
        if ($session_result = $db->query($session_query)) {
            while ($row = $session_result->fetch_assoc()) {
                $dropdowns['sessions'][] = $row;
            }
        }

        // Fetch Teachers from Contacts Table where contact_id is the primary key
        $teacher_query = "SELECT contact_id AS vbs_class_teacher_id, CONCAT(first_name, ' ', last_name) AS teacher_name 
                          FROM contacts 
                          WHERE Year(date_of_birth) < 2008
                          ORDER BY last_name ASC, first_name ASC";

        if ($teacher_result = $db->query($teacher_query)) {
            while ($row = $teacher_result->fetch_assoc()) {
                $dropdowns['teachers'][] = $row;
            }
        }

        echo json_encode($dropdowns);
        break;

    case 'read':
        $session_id = !empty($_REQUEST['vbs_sessions_id']) ? intval($_REQUEST['vbs_sessions_id']) : 0;

        $query = "SELECT c.*, CONCAT(con.first_name, ' ', con.last_name) AS teacher_name 
            FROM vbs_classes c
            LEFT JOIN contacts con ON c.vbs_class_teacher_id = con.contact_id";

        if ($session_id > 0) {
            $query .= " WHERE c.vbs_class_session_id = ? ORDER BY c.vbs_class_id DESC";
            $stmt = $db->prepare($query);
            $stmt->bind_param("i", $session_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $query .= " ORDER BY c.vbs_class_id DESC";
            $result = $db->query($query);
        }

        $classes = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $classes[] = $row;
            }
        }

        if (isset($stmt)) {
            $stmt->close();
        }

        // Always output a simple array [] or [{...}, {...}]
        header('Content-Type: application/json');
        echo json_encode($classes);
        break;
    case 'create':
        $session_id = 0;
        if (!empty($_POST['vbs_class_session_id'])) {
            $session_id = intval($_POST['vbs_class_session_id']);
        } elseif (!empty($_POST['vbs_sessions_id'])) {
            $session_id = intval($_POST['vbs_sessions_id']);
        }
        // Validation check
        if ($session_id <= 0) {
            header('Content-Type: application/json');
            echo json_encode([
                "success" => false,
                "message" => "Please select a valid Session Year before adding a class."
            ]);
            exit;
        }


        $description = trim($_POST['vbs_class_desc']);
        $age_start = intval($_POST['vbs_class_age_start']);
        $age_end = intval($_POST['vbs_class_age_end']);
        $teacher_id = !empty($_POST['vbs_class_teacher_id']) ? intval($_POST['vbs_class_teacher_id']) : null;

        if (empty($description)) {
            echo json_encode(["success" => false, "message" => "Missing description value"]);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO vbs_classes (vbs_class_session_id, vbs_class_desc, vbs_class_age_start, vbs_class_age_end, vbs_class_teacher_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isiii", $session_id, $description, $age_start, $age_end, $teacher_id);

        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(["success" => true, "vbs_class_id" => $stmt->insert_id]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(["success" => false, "message" => $stmt->error]);
        }
        $stmt->close();
        break;

case 'update':
    $class_id = intval($_POST['vbs_class_id']);

    $session_id = 0;
    if (!empty($_POST['vbs_class_session_id'])) {
        $session_id = intval($_POST['vbs_class_session_id']);
    } elseif (!empty($_POST['vbs_sessions_id'])) {
        $session_id = intval($_POST['vbs_sessions_id']);
    }

    $description = trim($_POST['vbs_class_desc']);
    $age_start = intval($_POST['vbs_class_age_start']);
    $age_end = intval($_POST['vbs_class_age_end']);
    $teacher_id = !empty($_POST['vbs_class_teacher_id']) ? intval($_POST['vbs_class_teacher_id']) : null;

    $stmt = $db->prepare("UPDATE vbs_classes SET vbs_class_session_id = ?, vbs_class_desc = ?, vbs_class_age_start = ?, vbs_class_age_end = ?, vbs_class_teacher_id = ? WHERE vbs_class_id = ?");
    $stmt->bind_param("isiiii", $session_id, $description, $age_start, $age_end, $teacher_id, $class_id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "message" => $stmt->error]);
    }
    $stmt->close();
    break;
    case 'delete':
        $class_id = intval($_POST['vbs_class_id']);
        $stmt = $db->prepare("DELETE FROM vbs_classes WHERE vbs_class_id = ?");
        $stmt->bind_param("i", $class_id);

        if ($stmt->execute()) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => $stmt->error]);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(["success" => false, "message" => "Invalid endpoint routing action"]);
        break;
}

$db->close();