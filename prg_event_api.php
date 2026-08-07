<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/db.php'; // Expects $db = new mysqli(...)

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        // --- FETCH MINISTRIES LOOKUP ---
        case 'get_ministries_list':
            $result = $db->query("SELECT min_comm_id, min_comm_name FROM ministry_committee ORDER BY min_comm_name ASC");
            echo json_encode(['success' => true, 'ministries' => $result ? $result->fetch_all(MYSQLI_ASSOC) : []]);
            break;

        // --- FETCH SINGLE OR ALL EVENTS ---
        case 'get_events':
            $prgevntId = $_GET['prg_evnt_id'] ?? null;
            if ($prgevntId) {
                // 1. Core Info
                $stmt = $db->prepare("SELECT e.*, m.min_comm_name as ministry_name FROM programs_events e LEFT JOIN ministry_committee m ON e.min_comm_id = m.min_comm_id WHERE e.prg_evnt_id = ?");
                $stmt->bind_param("i", $prgevntId);
                $stmt->execute();
                $prgevnt = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                // 2. Timelines
                $schStmt = $db->prepare("SELECT start_datetime, end_datetime FROM prg_evnt_schedules WHERE prg_evnt_id = ? ORDER BY start_datetime ASC");
                $schStmt->bind_param("i", $prgevntId);
                $schStmt->execute();
                $schedules = $schStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $schStmt->close();

                // 3. Budgets
                $budStmt = $db->prepare("SELECT item_description, item_type, amount FROM prg_evnt_budget_items WHERE prg_evnt_id = ? ORDER BY item_type ASC, budget_item_id ASC");
                $budStmt->bind_param("i", $prgevntId);
                $budStmt->execute();
                $budgets = $budStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $budStmt->close();

                echo json_encode(['success' => true, 'prgevnt' => $prgevnt, 'schedules' => $schedules, 'budgets' => $budgets]);
            } else {
                $sql = "SELECT e.*, m.min_comm_name as ministry_name, (SELECT MIN(start_datetime) FROM prg_evnt_schedules WHERE prg_evnt_id = e.prg_evnt_id) as primary_start FROM programs_events e LEFT JOIN ministry_committee m ON e.min_comm_id = m.min_comm_id ORDER BY primary_start DESC";
                $result = $db->query($sql);
                echo json_encode(['success' => true, 'programs_events' => $result ? $result->fetch_all(MYSQLI_ASSOC) : []]);
            }
            break;

        // --- CREATE OR UPDATE EVENT ---
        case 'save_event':
            $id = !empty($_POST['prg_evnt_id']) ? intval($_POST['prg_evnt_id']) : null;
            $prgevntname = trim($_POST['prg_evnt_name'] ?? '');
            $min_comm_id = intval($_POST['min_comm_id'] ?? 0);
            $location = trim($_POST['location'] ?? '');
            $goal = trim($_POST['goal'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $reqReg = isset($_POST['requires_registration']) ? 1 : 0;
            $fee = $reqReg ? floatval($_POST['registration_fee'] ?? 0) : 0.00;
            
            // Collect Schedules & Budgets
            $starts = $_POST['start_datetimes'] ?? [];
            $ends = $_POST['end_datetimes'] ?? [];
            $budDesc = $_POST['budget_desc'] ?? [];
            $budType = $_POST['budget_type'] ?? [];
            $budAmt = $_POST['budget_amount'] ?? [];

            if (empty($starts) || empty($starts[0])) {
                echo json_encode(['success' => false, 'message' => 'At least one date/time slot is required.']);
                break;
            }

            $db->begin_transaction();

            // Insert / Update Base Event
            if (empty($id)) {
                $stmt = $db->prepare("INSERT INTO programs_events (prg_evnt_name, min_comm_id, location, goal, requires_registration, registration_fee, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sisdids", $prgevntname, $min_comm_id, $location, $goal, $reqReg, $fee, $notes);
                $stmt->execute();
                $id = $db->insert_id;
                $stmt->close();
            } else {
                $stmt = $db->prepare("UPDATE programs_events SET prg_evnt_name=?, min_comm_id=?, location=?, goal=?, requires_registration=?, registration_fee=?, notes=? WHERE prg_evnt_id=?");
                $stmt->bind_param("sisdidsi", $prgevntname, $min_comm_id, $location, $goal, $reqReg, $fee, $notes, $id);
                $stmt->execute();
                $stmt->close();

                // Clear downstream children cleanly to rewrite fresh arrays
                $delSch = $db->prepare("DELETE FROM prg_evnt_schedules WHERE prg_evnt_id = ?");
                $delSch->bind_param("i", $id);
                $delSch->execute();
                $delSch->close();

                $delBud = $db->prepare("DELETE FROM prg_evnt_budget_items WHERE prg_evnt_id = ?");
                $delBud->bind_param("i", $id);
                $delBud->execute();
                $delBud->close();
            }

            // Write Dynamic Timelines
            $insSch = $db->prepare("INSERT INTO prg_evnt_schedules (prg_evnt_id, start_datetime, end_datetime) VALUES (?, ?, ?)");
            foreach ($starts as $idx => $startVal) {
                if (!empty($startVal)) {
                    $endVal = !empty($ends[$idx]) ? $ends[$idx] : $startVal;
                    // Format HTML5 datetime-local string (YYYY-MM-DDTHH:MM) to MySQL DATETIME (YYYY-MM-DD HH:MM:SS)
                    $formattedStart = date('Y-m-d H:i:s', strtotime($startVal));
                    $formattedEnd = date('Y-m-d H:i:s', strtotime($endVal));

                    $insSch->bind_param("iss", $id, $formattedStart, $formattedEnd);
                    $insSch->execute();
                }
            }
            $insSch->close();

            // Write Dynamic Budgets
            $insBud = $db->prepare("INSERT INTO prg_evnt_budget_items (prg_evnt_id, item_description, item_type, amount) VALUES (?, ?, ?, ?)");
            foreach ($budDesc as $idx => $desc) {
                $desc = trim($desc);
                $type = $budType[$idx] ?? 'Expense';
                $amt = floatval($budAmt[$idx] ?? 0);
                if (!empty($desc)) {
                    $insBud->bind_param("issd", $id, $desc, $type, $amt);
                    $insBud->execute();
                }
            }
            $insBud->close();

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Event, schedules, and split-budgets stored perfectly!', 'prg_evnt_id' => $id]);
            break;

        // --- DELETE EVENT ---
        case 'delete_event':
            $id = intval($_POST['prg_evnt_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM programs_events WHERE prg_evnt_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Event removed successfully.']);
            break;

        // --- LIVE CHECK-IN OPERATIONS ---
        case 'get_event_roster':
            $prgevntId = intval($_GET['prg_evnt_id'] ?? 0);
            $reg = $db->prepare("SELECT r.*, COALESCE(CONCAT(c.first_name, ' ', c.last_name), 'Unknown') AS full_name FROM event_registrations r LEFT JOIN contacts c ON r.contact_id = c.contact_id WHERE r.prg_evnt_id = ?");
            $reg->bind_param("i", $prgevntId);
            $reg->execute();
            $registrations = $reg->get_result()->fetch_all(MYSQLI_ASSOC);
            $reg->close();

            $att = $db->prepare("SELECT a.*, COALESCE(CONCAT(c.first_name, ' ', c.last_name), 'Unknown') AS full_name FROM event_attendance a LEFT JOIN contacts c ON a.contact_id = c.contact_id WHERE a.prg_evnt_id = ?");
            $att->bind_param("i", $prgevntId);
            $att->execute();
            $attendance = $att->get_result()->fetch_all(MYSQLI_ASSOC);
            $att->close();

            echo json_encode(['success' => true, 'registrations' => $registrations, 'attendance' => $attendance]);
            break;

        case 'toggle_attendance':
            $prgevntId = intval($_POST['prg_evnt_id'] ?? 0);
            $contactId = intval($_POST['contact_id'] ?? 0);
            $check = $db->prepare("SELECT attendance_id FROM event_attendance WHERE prg_evnt_id = ? AND contact_id = ?");
            $check->bind_param("ii", $prgevntId, $contactId);
            $check->execute();
            $res = $check->get_result();

            if ($row = $res->fetch_assoc()) {
                $check->close();
                $del = $db->prepare("DELETE FROM event_attendance WHERE attendance_id = ?");
                $del->bind_param("i", $row['attendance_id']);
                $del->execute();
                $del->close();
                echo json_encode(['success' => true, 'status' => 'absent']);
            } else {
                $check->close();
                $ins = $db->prepare("INSERT INTO event_attendance (prg_evnt_id, contact_id) VALUES (?, ?)");
                $ins->bind_param("ii", $prgevntId, $contactId);
                $ins->execute();
                $ins->close();
                echo json_encode(['success' => true, 'status' => 'present']);
            }
            break;

        // --- REGISTRATION PASS-THROUGHS ---
        case 'get_contacts_list':
            $result = $db->query("SELECT contact_id, COALESCE(CONCAT(first_name, ' ', last_name), 'Unknown') AS full_name FROM contacts ORDER BY last_name ASC");
            echo json_encode(['success' => true, 'contacts' => $result ? $result->fetch_all(MYSQLI_ASSOC) : []]);
            break;

        case 'get_event_registrations':
            $prgevntId = intval($_GET['prg_evnt_id'] ?? 0);
            $stmt = $db->prepare("SELECT r.*, COALESCE(CONCAT(c.first_name, ' ', c.last_name), 'Unknown') AS full_name FROM event_registrations r LEFT JOIN contacts c ON r.contact_id = c.contact_id WHERE r.prg_evnt_id = ? ORDER BY r.registered_at DESC");
            $stmt->bind_param("i", $prgevntId);
            $stmt->execute();
            echo json_encode(['success' => true, 'registrations' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
            $stmt->close();
            break;

        case 'register_contact':
            $prgevntId = intval($_POST['prg_evnt_id'] ?? 0);
            $contactId = intval($_POST['contact_id'] ?? 0);
            $status = $_POST['payment_status'] ?? 'Pending';
            $amount = floatval($_POST['amount_paid'] ?? 0);
            $method = trim($_POST['payment_method'] ?? 'None');

            $check = $db->prepare("SELECT registration_id FROM event_registrations WHERE prg_evnt_id = ? AND contact_id = ?");
            $check->bind_param("ii", $prgevntId, $contactId);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $check->close();
                echo json_encode(['success' => false, 'message' => 'Attendee already exists.']);
                break;
            }
            $check->close();

            $stmt = $db->prepare("INSERT INTO event_registrations (prg_evnt_id, contact_id, payment_status, amount_paid, payment_method) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisds", $prgevntId, $contactId, $status, $amount, $method);
            echo json_encode(['success' => $stmt->execute(), 'message' => 'Registration confirmed!']);
            $stmt->close();
            break;

        case 'cancel_registration':
            $regId = intval($_POST['registration_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM event_registrations WHERE registration_id = ?");
            $stmt->bind_param("i", $regId);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid Action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}