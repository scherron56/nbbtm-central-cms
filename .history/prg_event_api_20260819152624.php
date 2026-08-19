<?php
// prg_event_api.php
header('Content-Type: application/json; charset=utf-8');
require_once 'config/db.php';
require_once 'include/auth.php';

$action = $_REQUEST['action'] ?? '';
try {
    switch ($action) {

        // --- FETCH CONTACTS LOOKUP ---
        case 'get_contacts_list':
            $sql = "SELECT contact_id, 
                           CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) AS full_name,
                           COALESCE(phone_1, '') AS phone,
                           COALESCE(c_email, '') AS email 
                    FROM contacts 
                    ORDER BY first_name ASC, last_name ASC";
            $result = $db->query($sql);
            if (!$result) {
                throw new Exception($db->error);
            }
            echo json_encode(['success' => true, 'contacts' => $result->fetch_all(MYSQLI_ASSOC)]);
            break;

        // --- FETCH ALL MINISTRIES LOOKUP ---
        case 'get_ministries_list':
            $result = $db->query("SELECT min_comm_id, min_comm_name FROM ministry_committee ORDER BY min_comm_name ASC");
            if (!$result) {
                throw new Exception($db->error);
            }
            echo json_encode(['success' => true, 'ministries' => $result->fetch_all(MYSQLI_ASSOC)]);
            break;

        // --- FETCH SINGLE OR ALL EVENTS ---
        case 'get_events':
            $prgevntId = $_GET['prg_evnt_id'] ?? null;
            if ($prgevntId) {
                $stmt = $db->prepare("
                    SELECT prg_evnt_id, prg_evnt_name, min_comm_id, contact_id, contact_phone, contact_email,
                           location, goal, prg_evnt_purpose, notes, requires_registration, requires_fee, registration_fee,
                           document_name, document_mime, document_size,
                           (document_data IS NOT NULL) AS has_document
                    FROM programs_events 
                    WHERE prg_evnt_id = ?
                ");
                $stmt->bind_param("i", $prgevntId);
                $stmt->execute();
                $prgevnt = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                // Schedules
                $schStmt = $db->prepare("SELECT start_datetime, end_datetime FROM prg_evnt_schedules WHERE prg_evnt_id = ? ORDER BY start_datetime ASC");
                $schStmt->bind_param("i", $prgevntId);
                $schStmt->execute();
                $schedules = $schStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $schStmt->close();

                // Budgets
                $budStmt = $db->prepare("SELECT item_description, item_type, amount FROM prg_evnt_budget_items WHERE prg_evnt_id = ? ORDER BY item_type ASC, budget_item_id ASC");
                $budStmt->bind_param("i", $prgevntId);
                $budStmt->execute();
                $budgets = $budStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $budStmt->close();

                // Ministry Support Allocations
                $supStmt = $db->prepare("
                    SELECT s.min_comm_id, s.option, m.min_comm_name 
                    FROM prg_evnt_min_support s
                    JOIN ministry_committee m ON s.min_comm_id = m.min_comm_id
                    WHERE s.prg_evnt_id = ?
                ");
                $supStmt->bind_param("i", $prgevntId);
                $supStmt->execute();
                $support_ministries = $supStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $supStmt->close();

                echo json_encode([
                    'success' => true, 
                    'prgevnt' => $prgevnt, 
                    'schedules' => $schedules, 
                    'budgets' => $budgets, 
                    'support_ministries' => $support_ministries
                ]);
            } else {
                $sql = "SELECT e.prg_evnt_id, e.prg_evnt_name, e.min_comm_id, e.location, e.goal, e.prg_evnt_purpose, e.notes,
                               e.requires_registration, e.requires_fee, e.registration_fee,
                               e.document_name, (e.document_data IS NOT NULL) AS has_document,
                               m.min_comm_name AS ministry_name, s.primary_start 
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

        // --- CREATE OR UPDATE EVENT ---
        case 'save_event':
            requireAdmin();

            $id = !empty($_POST['prg_evnt_id']) ? intval($_POST['prg_evnt_id']) : null;
            $prgevntname = trim($_POST['prg_evnt_name'] ?? '');
            $min_comm_id = intval($_POST['min_comm_id'] ?? 0);
            $contact_id = !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null;
            $contact_phone = trim($_POST['contact_phone'] ?? '');
            $contact_email = trim($_POST['contact_email'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $purpose = trim($_POST['prg_evnt_purpose'] ?? '');
            $goal = trim($_POST['goal'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $reqReg = isset($_POST['requires_registration']) ? 1 : 0;
            $reqFee = isset($_POST['requires_fee']) ? 1 : 0;
            $fee = $reqFee ? floatval($_POST['registration_fee'] ?? 0) : 0.00;
            $removeDoc = isset($_POST['remove_document']) && intval($_POST['remove_document']) === 1;

            $starts = $_POST['start_datetimes'] ?? [];
            $ends = $_POST['end_datetimes'] ?? [];
            $budDesc = $_POST['budget_desc'] ?? [];
            $budType = $_POST['budget_type'] ?? [];
            $budAmt = $_POST['budget_amount'] ?? [];
            $supportMinIds = $_POST['support_min_ids'] ?? [];

            if (empty($starts) || empty($starts[0])) {
                echo json_encode(['success' => false, 'message' => 'At least one date/time slot is required.']);
                break;
            }

            $hasNewFile = false;
            $docData = null;
            $docName = null;
            $docMime = null;
            $docSize = 0;

            if (!empty($_FILES['event_document']['name']) && $_FILES['event_document']['error'] === UPLOAD_ERR_OK) {
                $tmpPath = $_FILES['event_document']['tmp_name'];
                $docName = basename($_FILES['event_document']['name']);
                $docSize = (int)$_FILES['event_document']['size'];
                $docMime = mime_content_type($tmpPath) ?: $_FILES['event_document']['type'];
                $docData = file_get_contents($tmpPath);
                $hasNewFile = true;
            }

            $db->begin_transaction();

            if (empty($id)) {
                $stmt = $db->prepare("
                    INSERT INTO programs_events 
                    (prg_evnt_name, min_comm_id, contact_id, contact_phone, contact_email, location, prg_evnt_purpose, goal, requires_registration, requires_fee, registration_fee, notes, document_name, document_mime, document_size, document_data) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $nullBlob = NULL;
                $stmt->bind_param(
                    "siisssssiidsssib",
                    $prgevntname, $min_comm_id, $contact_id, $contact_phone, $contact_email, $location, $purpose, $goal, $reqReg, $reqFee, $fee, $notes,
                    $docName, $docMime, $docSize, $nullBlob
                );

                if ($hasNewFile && $docData !== null) {
                    $stmt->send_long_data(15, $docData);
                }

                $stmt->execute();
                $id = $db->insert_id;
                $stmt->close();
            } else {
                if ($hasNewFile) {
                    $stmt = $db->prepare("
                        UPDATE programs_events 
                        SET prg_evnt_name=?, min_comm_id=?, contact_id=?, contact_phone=?, contact_email=?, location=?, prg_evnt_purpose=?, goal=?, requires_registration=?, requires_fee=?, registration_fee=?, notes=?,
                            document_name=?, document_mime=?, document_size=?, document_data=?
                        WHERE prg_evnt_id=?
                    ");
                    $nullBlob = NULL;
                    $stmt->bind_param(
                        "siisssssiidsssibi",
                        $prgevntname, $min_comm_id, $contact_id, $contact_phone, $contact_email, $location, $purpose, $goal, $reqReg, $reqFee, $fee, $notes,
                        $docName, $docMime, $docSize, $nullBlob, $id
                    );
                    $stmt->send_long_data(15, $docData);
                    $stmt->execute();
                    $stmt->close();
                } elseif ($removeDoc) {
                    $stmt = $db->prepare("
                        UPDATE programs_events 
                        SET prg_evnt_name=?, min_comm_id=?, contact_id=?, contact_phone=?, contact_email=?, location=?, prg_evnt_purpose=?, goal=?, requires_registration=?, requires_fee=?, registration_fee=?, notes=?,
                            document_name=NULL, document_mime=NULL, document_size=NULL, document_data=NULL
                        WHERE prg_evnt_id=?
                    ");
                    $stmt->bind_param("siisssssiidsi", $prgevntname, $min_comm_id, $contact_id, $contact_phone, $contact_email, $location, $purpose, $goal, $reqReg, $reqFee, $fee, $notes, $id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $db->prepare("
                        UPDATE programs_events 
                        SET prg_evnt_name=?, min_comm_id=?, contact_id=?, contact_phone=?, contact_email=?, location=?, prg_evnt_purpose=?, goal=?, requires_registration=?, requires_fee=?, registration_fee=?, notes=? 
                        WHERE prg_evnt_id=?
                    ");
                    $stmt->bind_param("siisssssiidsi", $prgevntname, $min_comm_id, $contact_id, $contact_phone, $contact_email, $location, $purpose, $goal, $reqReg, $reqFee, $fee, $notes, $id);
                    $stmt->execute();
                    $stmt->close();
                }

                // Delete existing schedules and budgets before re-inserting
                $delSch = $db->prepare("DELETE FROM prg_evnt_schedules WHERE prg_evnt_id = ?");
                $delSch->bind_param("i", $id);
                $delSch->execute();
                $delSch->close();

                $delBud = $db->prepare("DELETE FROM prg_evnt_budget_items WHERE prg_evnt_id = ?");
                $delBud->bind_param("i", $id);
                $delBud->execute();
                $delBud->close();

                // Clear previous ministry support records for this event
                $delSup = $db->prepare("DELETE FROM prg_evnt_min_support WHERE prg_evnt_id = ?");
                $delSup->bind_param("i", $id);
                $delSup->execute();
                $delSup->close();
            }

            // Sync Schedules
            $insSch = $db->prepare("INSERT INTO prg_evnt_schedules (prg_evnt_id, start_datetime, end_datetime) VALUES (?, ?, ?)");
            foreach ($starts as $idx => $startVal) {
                if (!empty($startVal)) {
                    $formattedStart = date('Y-m-d H:i:s', strtotime($startVal));
                    $formattedEnd = !empty($ends[$idx]) ? date('Y-m-d H:i:s', strtotime($ends[$idx])) : null;

                    $insSch->bind_param("iss", $id, $formattedStart, $formattedEnd);
                    $insSch->execute();
                }
            }
            $insSch->close();

            // Sync Budget Items
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

            // Insert checked ministries into prg_evnt_min_support
            if (!empty($supportMinIds)) {
                $supportMinIds = array_unique(array_filter(array_map('intval', (array)$supportMinIds)));
                $insSup = $db->prepare("INSERT INTO prg_evnt_min_support (prg_evnt_id, min_comm_id, `option`) VALUES (?, ?, ?)");
                
                foreach ($supportMinIds as $mId) {
                    // Check if a radio option was sent for this specific ministry (e.g. Building Maintenance)
                    $opt = $_POST['support_option_' . $mId] ?? null;
                    if (!in_array($opt, ['Option A', 'Option B'])) {
                        $opt = null;
                    }
                    $insSup->bind_param("iis", $id, $mId, $opt);
                    $insSup->execute();
                }
                $insSup->close();
            }

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Event and ministry support allocations saved successfully!', 'prg_evnt_id' => $id]);
            break;

        // --- DELETE EVENT ---
        case 'delete_event':
            requireAdmin();

            $id = intval($_POST['prg_evnt_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM programs_events WHERE prg_evnt_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Event removed successfully.']);
            break;

        // --- LIVE CHECK-IN ROSTER ---
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

        // --- TOGGLE ATTENDANCE ---
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

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid Action']);
            break;
    }
} catch (Exception $e) {
    if (isset($db) && $db instanceof mysqli) {
        $db->rollback();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}