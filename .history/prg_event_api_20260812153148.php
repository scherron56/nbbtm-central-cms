<?php
// prg_event_api.php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/db.php';
require_once 'include/auth.php'; // Include authentication middleware

$action = $_REQUEST['action'] ?? '';
try {
    switch ($action) {

        // --- FETCH MINISTRIES LOOKUP (Read-Only) ---
        case 'get_ministries_list':
            $result = $db->query("SELECT min_comm_id, min_comm_name FROM ministry_committee ORDER BY min_comm_name ASC");
            if (!$result) {
                throw new Exception($db->error);
            }
            echo json_encode(['success' => true, 'ministries' => $result->fetch_all(MYSQLI_ASSOC)]);
            break;

        // --- FETCH SINGLE OR ALL EVENTS (Read-Only) ---
        case 'get_events':
            $prgevntId = $_GET['prg_evnt_id'] ?? null;
            if ($prgevntId) {
                $stmt = $db->prepare("SELECT e.*, m.min_comm_name as ministry_name FROM programs_events e LEFT JOIN ministry_committee m ON e.min_comm_id = m.min_comm_id WHERE e.prg_evnt_id = ?");
                $stmt->bind_param("i", $prgevntId);
                $stmt->execute();
                $prgevnt = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $schStmt = $db->prepare("SELECT start_datetime, end_datetime FROM prg_evnt_schedules WHERE prg_evnt_id = ? ORDER BY start_datetime ASC");
                $schStmt->bind_param("i", $prgevntId);
                $schStmt->execute();
                $schedules = $schStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $schStmt->close();

                $budStmt = $db->prepare("SELECT item_description, item_type, amount FROM prg_evnt_budget_items WHERE prg_evnt_id = ? ORDER BY item_type ASC, budget_item_id ASC");
                $budStmt->bind_param("i", $prgevntId);
                $budStmt->execute();
                $budgets = $budStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $budStmt->close();

                echo json_encode(['success' => true, 'prgevnt' => $prgevnt, 'schedules' => $schedules, 'budgets' => $budgets]);
            } else {
                $sql = "SELECT e.*, m.min_comm_name AS ministry_name, s.primary_start 
                        FROM programs_events e 
                        LEFT JOIN ministry_committee m ON e.min_comm_id = m.min_comm_id 
                        LEFT JOIN (
                            SELECT prg_evnt_id, MIN(start_datetime) AS primary_start 
                            FROM prg_evnt_schedules 
                            GROUP BY prg_evnt_id
                        ) s ON e.prg_evnt_id = s.prg_evnt_id 
                        ORDER BY s.primary_start DESC";
                $result = $db->query($sql);
                if (!$result) {
                    throw new Exception($db->error);
                }
                echo json_encode(['success' => true, 'programs_events' => $result->fetch_all(MYSQLI_ASSOC)]);
            }
            break;

        // --- CREATE OR UPDATE EVENT (Admin Only) ---
        case 'save_event':
            requireAdmin();

            $id = !empty($_POST['prg_evnt_id']) ? intval($_POST['prg_evnt_id']) : null;
            $prgevntname = trim($_POST['prg_evnt_name'] ?? '');
            $min_comm_id = intval($_POST['min_comm_id'] ?? 0);
            $location = trim($_POST['location'] ?? '');
            $goal = trim($_POST['goal'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $reqReg = isset($_POST['requires_registration']) ? 1 : 0;
            $reqFee = isset($_POST['requires_fee']) ? 1 : 0;
            $fee = $reqFee ? floatval($_POST['registration_fee'] ?? 0) : 0.00;
            
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

            if (empty($id)) {
                $stmt = $db->prepare("INSERT INTO programs_events (prg_evnt_name, min_comm_id, location, goal, requires_registration, requires_fee, registration_fee, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sisiiids", $prgevntname, $min_comm_id, $location, $goal, $reqReg, $reqFee, $fee, $notes);
                $stmt->execute();
                $id = $db->insert_id;
                $stmt->close();
            } else {
                $stmt = $db->prepare("UPDATE programs_events SET prg_evnt_name=?, min_comm_id=?, location=?, goal=?, requires_registration=?, requires_fee=?, registration_fee=?, notes=? WHERE prg_evnt_id=?");
                $stmt->bind_param("sisiiidsi", $prgevntname, $min_comm_id, $location, $goal, $reqReg, $reqFee, $fee, $notes, $id);
                $stmt->execute();
                $stmt->close();

                $delSch = $db->prepare("DELETE FROM prg_evnt_schedules WHERE prg_evnt_id = ?");
                $delSch->bind_param("i", $id);
                $delSch->execute();
                $delSch->close();

                $delBud = $db->prepare("DELETE FROM prg_evnt_budget_items WHERE prg_evnt_id = ?");
                $delBud->bind_param("i", $id);
                $delBud->execute();
                $delBud->close();
            }

            $insSch = $db->prepare("INSERT INTO prg_evnt_schedules (prg_evnt_id, start_datetime, end_datetime) VALUES (?, ?, ?)");
            foreach ($starts as $idx => $startVal) {
                if (!empty($startVal)) {
                    $formattedStart = date('Y-m-d H:i:s', strtotime($startVal));
                    
                    // Allow NULL if end_datetime is empty
                    $formattedEnd = !empty($ends[$idx]) ? date('Y-m-d H:i:s', strtotime($ends[$idx])) : null;

                    $insSch->bind_param("iss", $id, $formattedStart, $formattedEnd);
                    $insSch->execute();
                }
            }
            $insSch->close();

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
            echo json_encode(['success' => true, 'message' => 'Event details saved successfully!', 'prg_evnt_id' => $id]);
            break;

        // --- DELETE EVENT (Admin Only) ---
        case 'delete_event':
            requireAdmin();

            $id = intval($_POST['prg_evnt_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM programs_events WHERE prg_evnt_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Event removed successfully.']);
            break;

        // --- LIVE CHECK-IN ROSTER (Read-Only) ---
        case 'get_event_roster':
            $prgevntId = intval($_GET['prg_evnt_id'] ?? 0);

            $ev = $db->prepare("SELECT * FROM programs_events WHERE prg_evnt_id = ?");
            $ev->bind_param("i", $prgevntId);
            $ev->execute();
            $eventDetails = $ev->get_result()->fetch_assoc();
            $ev->close();

            $reg = $db->prepare("SELECT r.*, COALESCE(CONCAT(c.first_name, ' ', c.last_name), 'Unknown') AS full_name FROM prg_evnt_registrations r LEFT JOIN contacts c ON r.contact_id = c.contact_id WHERE r.prg_evnt_id = ? ORDER BY r.registered_at DESC");
            $reg->bind_param("i", $prgevntId);
            $reg->execute();
            $registrations = $reg->get_result()->fetch_all(MYSQLI_ASSOC);
            $reg->close();

            $att = $db->prepare("SELECT a.*, COALESCE(CONCAT(c.first_name, ' ', c.last_name), 'Unknown') AS full_name FROM prg_evnt_attendance a LEFT JOIN contacts c ON a.contact_id = c.contact_id WHERE a.prg_evnt_id = ?");
            $att->bind_param("i", $prgevntId);
            $att->execute();
            $attendance = $att->get_result()->fetch_all(MYSQLI_ASSOC);
            $att->close();

            echo json_encode(['success' => true, 'event' => $eventDetails, 'registrations' => $registrations, 'attendance' => $attendance]);
            break;

        // --- TOGGLE ATTENDANCE (Admin Only) ---
        case 'toggle_attendance':
            requireAdmin();

            $prgevntId = intval($_POST['prg_evnt_id'] ?? 0);
            $contactId = intval($_POST['contact_id'] ?? 0);

            $evCheck = $db->prepare("SELECT requires_fee FROM programs_events WHERE prg_evnt_id = ?");
            $evCheck->bind_param("i", $prgevntId);
            $evCheck->execute();
            $evRes = $evCheck->get_result()->fetch_assoc();
            $evCheck->close();

            $reqFee = intval($evRes['requires_fee'] ?? 0) === 1;

            $checkReg = $db->prepare("SELECT payment_status, is_completed FROM prg_evnt_registrations WHERE prg_evnt_id = ? AND contact_id = ?");
            $checkReg->bind_param("ii", $prgevntId, $contactId);
            $checkReg->execute();
            $regRes = $checkReg->get_result()->fetch_assoc();
            $checkReg->close();

            $isDone = false;
            if (!$reqFee) {
                $isDone = true; 
            } else if ($regRes) {
                $statusLower = strtolower($regRes['payment_status'] ?? '');
                if (intval($regRes['is_completed']) === 1 || in_array($statusLower, ['paid', 'completed', 'waived', 'n/a'])) {
                    $isDone = true;
                }
            }

            $check = $db->prepare("SELECT attendance_id FROM prg_evnt_attendance WHERE prg_evnt_id = ? AND contact_id = ?");
            $check->bind_param("ii", $prgevntId, $contactId);
            $check->execute();
            $res = $check->get_result();

            if ($row = $res->fetch_assoc()) {
                $check->close();
                $del = $db->prepare("DELETE FROM prg_evnt_attendance WHERE attendance_id = ?");
                $del->bind_param("i", $row['attendance_id']);
                $del->execute();
                $del->close();
                echo json_encode(['success' => true, 'status' => 'absent']);
            } else {
                $check->close();
                if (!$isDone) {
                    echo json_encode(['success' => false, 'error' => 'Cannot check in: Registration is incomplete/unpaid.']);
                    break;
                }
                $ins = $db->prepare("INSERT INTO prg_evnt_attendance (prg_evnt_id, contact_id) VALUES (?, ?)");
                $ins->bind_param("ii", $prgevntId, $contactId);
                $ins->execute();
                $ins->close();
                echo json_encode(['success' => true, 'status' => 'present']);
            }
            break;

        // --- UPDATE PAYMENT AT CHECK-IN (Admin Only) ---
        case 'update_checkin_payment':
            requireAdmin();

            $regId = intval($_POST['registration_id'] ?? 0);
            $method = trim($_POST['payment_method'] ?? 'None');
            $amount = floatval($_POST['amount_paid'] ?? 0);
            $status = $_POST['payment_status'] ?? 'Paid';

            $isCompleted = in_array(strtolower($status), ['paid', 'completed', 'waived', 'n/a']) ? 1 : 0;

            $stmt = $db->prepare("UPDATE prg_evnt_registrations SET payment_method = ?, amount_paid = ?, payment_status = ?, is_completed = ? WHERE registration_id = ?");
            $stmt->bind_param("sdsii", $method, $amount, $status, $isCompleted, $regId);
            $isSuccess = $stmt->execute();
            $stmt->close();

            echo json_encode(['success' => $isSuccess, 'message' => 'Payment status updated successfully.']);
            break;

        // --- LOOKUPS & REGISTRATIONS (Read-Only) ---
        case 'get_contacts_list':
            $result = $db->query("SELECT contact_id, COALESCE(CONCAT(first_name, ' ', last_name), 'Unknown') AS full_name FROM contacts ORDER BY last_name ASC");
            if (!$result) {
                throw new Exception($db->error);
            }
            echo json_encode(['success' => true, 'contacts' => $result->fetch_all(MYSQLI_ASSOC)]);
            break;

        case 'get_event_registrations':
            $prgevntId = intval($_GET['prg_evnt_id'] ?? 0);
            $stmt = $db->prepare("SELECT r.*, COALESCE(CONCAT(c.first_name, ' ', c.last_name), 'Unknown') AS full_name FROM prg_evnt_registrations r LEFT JOIN contacts c ON r.contact_id = c.contact_id WHERE r.prg_evnt_id = ? ORDER BY r.registered_at DESC");
            $stmt->bind_param("i", $prgevntId);
            $stmt->execute();
            echo json_encode(['success' => true, 'registrations' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
            $stmt->close();
            break;

        // --- CREATE OR UPDATE REGISTRATION (Admin Only) ---
        case 'register_contact':
        case 'save_registration':
            requireAdmin();

            $regId     = intval($_POST['registration_id'] ?? 0);
            $prgevntId = intval($_POST['prg_evnt_id'] ?? 0);
            $contactId = intval($_POST['contact_id'] ?? 0);

            // Step 1: Immediate validation for missing parameters
            if ($prgevntId <= 0 || $contactId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please select both an event and a contact.']);
                break;
            }

            // Step 2: Immediate check for duplicate registrations
            if ($regId > 0) {
                $check = $db->prepare("SELECT registration_id FROM prg_evnt_registrations WHERE prg_evnt_id = ? AND contact_id = ? AND registration_id != ?");
                $check->bind_param("iii", $prgevntId, $contactId, $regId);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $check->close();
                    echo json_encode(['success' => false, 'message' => 'Attendee is already registered for this event.']);
                    break;
                }
                $check->close();
            } else {
                $check = $db->prepare("SELECT registration_id FROM prg_evnt_registrations WHERE prg_evnt_id = ? AND contact_id = ?");
                $check->bind_param("ii", $prgevntId, $contactId);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $check->close();
                    echo json_encode(['success' => false, 'message' => 'Attendee is already registered for this event.']);
                    break;
                }
                $check->close();
            }

            // Step 3: Event Fee verification
            $evCheck = $db->prepare("SELECT requires_fee FROM programs_events WHERE prg_evnt_id = ?");
            $evCheck->bind_param("i", $prgevntId);
            $evCheck->execute();
            $evRes = $evCheck->get_result()->fetch_assoc();
            $evCheck->close();

            $reqFee = intval($evRes['requires_fee'] ?? 0) === 1;

            if ($reqFee) {
                $status = $_POST['payment_status'] ?? 'Pending';
                $amount = floatval($_POST['amount_paid'] ?? 0);
                $method = trim($_POST['payment_method'] ?? 'None');
            } else {
                $status = 'Paid';
                $amount = 0.00;
                $method = 'None';
            }

            $isCompleted = (!$reqFee || in_array(strtolower($status), ['paid', 'completed', 'waived', 'n/a'])) ? 1 : 0;

            // Step 4: Execute Update or Insert
            if ($regId > 0) {
                $stmt = $db->prepare("UPDATE prg_evnt_registrations SET contact_id = ?, payment_status = ?, amount_paid = ?, payment_method = ?, is_completed = ? WHERE registration_id = ?");
                $stmt->bind_param("isdsii", $contactId, $status, $amount, $method, $isCompleted, $regId);
                $isSuccess = $stmt->execute();
                $stmt->close();

                echo json_encode(['success' => $isSuccess, 'message' => 'Registration updated successfully!']);
            } else {
                $stmt = $db->prepare("INSERT INTO prg_evnt_registrations (prg_evnt_id, contact_id, payment_status, amount_paid, payment_method, is_completed) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisdsi", $prgevntId, $contactId, $status, $amount, $method, $isCompleted);
                $isSuccess = $stmt->execute();
                $stmt->close();

                $msg = $isCompleted ? 'Registration confirmed!' : 'Registration submitted! Marked as NOT COMPLETED pending payment/waiver.';
                echo json_encode(['success' => $isSuccess, 'message' => $msg]);
            }
            break;

        // --- CANCEL REGISTRATION (Admin Only) ---
        case 'cancel_registration':
            requireAdmin();

            $regId = intval($_POST['registration_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM prg_evnt_registrations WHERE registration_id = ?");
            $stmt->bind_param("i", $regId);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Registration canceled and removed.']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid Action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>