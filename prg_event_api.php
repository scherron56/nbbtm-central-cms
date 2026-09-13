<?php
/**
 * prg_event_api.php
 * Centralized API endpoint for Program/Event operations including schedules,
 * budget planning, actual expense/income logging, document attachments,
 * and attendee registrations.
 */

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/registration_fee.php';

function sendEventJson(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// requireAdmin() is provided by include/auth.php (declared there) — do not redeclare it here.
// logRegistrationFeeActual() is provided by include/registration_fee.php (shared with self_register.php).

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    // -------------------------------------------------------------------------
    // FETCH ALL EVENTS OR A SINGLE EVENT WITH ALL ASSOCIATED DATA
    // -------------------------------------------------------------------------
    case 'get_events':
    case 'get_event':
        $prgevntId = (int)($_GET['prg_evnt_id'] ?? 0);

        if ($prgevntId > 0) {
            // Fetch Main Event Record
            $stmt = $db->prepare("
                SELECT e.*, m.min_comm_name AS ministry_name, CONCAT(c.first_name, ' ', c.last_name) AS contact_name
                FROM programs_events e
                LEFT JOIN ministry_committee m ON e.min_comm_id = m.min_comm_id
                LEFT JOIN contacts c ON e.contact_id = c.contact_id
                WHERE e.prg_evnt_id = ?
            ");
            $stmt->bind_param("i", $prgevntId);
            $stmt->execute();
            $prgevnt = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$prgevnt) {
                sendEventJson(['success' => false, 'error' => 'Event not found.'], 404);
            }

            // Fetch Schedules
            $schStmt = $db->prepare("SELECT * FROM prg_evnt_schedules WHERE prg_evnt_id = ? ORDER BY start_datetime ASC");
            $schStmt->bind_param("i", $prgevntId);
            $schStmt->execute();
            $schedules = $schStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $schStmt->close();

            // Fetch Planned Budgets
            $budStmt = $db->prepare("SELECT * FROM prg_evnt_budget_items WHERE prg_evnt_id = ? ORDER BY budget_item_id ASC");
            $budStmt->bind_param("i", $prgevntId);
            $budStmt->execute();
            $budgets = $budStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $budStmt->close();

            // Fetch Support Ministries
            $supStmt = $db->prepare("
                SELECT sm.min_comm_id, m.min_comm_name
                FROM prg_evnt_min_support sm
                JOIN ministry_committee m ON sm.min_comm_id = m.min_comm_id
                WHERE sm.prg_evnt_id = ?
            ");
            $supStmt->bind_param("i", $prgevntId);
            $supStmt->execute();
            $support_ministries = $supStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $supStmt->close();

            // Fetch Attachments (stored in document_lib as binary blobs)
            $attStmt = $db->prepare("
                SELECT a.document_id AS attachment_id, a.document_name, a.document_size, c.category_name
                FROM document_lib a
                LEFT JOIN doc_categories c ON a.doc_category_id = c.doc_category_id
                WHERE a.entity_type = 'event' AND a.entity_id = ?
            ");
            $attStmt->bind_param("i", $prgevntId);
            $attStmt->execute();
            $attachments = $attStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $attStmt->close();

            // Fetch Actual Transactions
            $actStmt = $db->prepare("
                SELECT a.actual_id, a.budget_item_id, a.entry_type, a.description, a.amount, a.created_at, b.item_description AS budget_item_name
                FROM prg_evnt_budget_actuals a
                LEFT JOIN prg_evnt_budget_items b ON a.budget_item_id = b.budget_item_id
                WHERE a.prg_evnt_id = ?
                ORDER BY a.created_at DESC
            ");
            $actStmt->bind_param("i", $prgevntId);
            $actStmt->execute();
            $actuals = $actStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $actStmt->close();

            sendEventJson([
                'success'            => true,
                'prgevnt'            => $prgevnt,
                'schedules'          => $schedules,
                'budgets'            => $budgets,
                'support_ministries' => $support_ministries,
                'attachments'        => $attachments,
                'actuals'            => $actuals
            ]);
        } else {
            // Fetch List of Events
            $stmt = $db->prepare("
                SELECT e.prg_evnt_id, e.prg_evnt_name, e.requires_registration, e.requires_fee, e.registration_fee,
                       MIN(s.start_datetime) AS primary_start
                FROM programs_events e
                LEFT JOIN prg_evnt_schedules s ON e.prg_evnt_id = s.prg_evnt_id
                GROUP BY e.prg_evnt_id
                ORDER BY primary_start DESC, e.prg_evnt_id DESC
            ");
            $stmt->execute();
            $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            sendEventJson(['success' => true, 'programs_events' => $events]);
        }
        break;

    // -------------------------------------------------------------------------
    // FETCH CONTACTS REFERENCE LIST
    // -------------------------------------------------------------------------
    case 'get_contacts_list':
        $stmt = $db->prepare("SELECT contact_id, CONCAT(first_name, ' ', last_name) AS full_name, phone_1 AS phone, c_email AS email FROM contacts ORDER BY last_name ASC, first_name ASC");
        $stmt->execute();
        $contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        sendEventJson(['success' => true, 'contacts' => $contacts]);
        break;

    // -------------------------------------------------------------------------
    // FETCH MINISTRIES REFERENCE LIST
    // -------------------------------------------------------------------------
    case 'get_ministries_list':
        $stmt = $db->prepare("SELECT min_comm_id, min_comm_name FROM ministry_committee ORDER BY min_comm_name ASC");
        $stmt->execute();
        $ministries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        sendEventJson(['success' => true, 'ministries' => $ministries]);
        break;

    // -------------------------------------------------------------------------
    // SAVE OR UPDATE EVENT WITH SCHEDULES, BUDGETS, AND FILES
    // -------------------------------------------------------------------------
    case 'save_event':
        requireAdmin();

        $prgevntId = (int)($_POST['prg_evnt_id'] ?? 0);
        $eventName = trim($_POST['prg_evnt_name'] ?? '');
        $minCommId = (int)($_POST['min_comm_id'] ?? 0);
        $contactId = !empty($_POST['contact_id']) ? (int)$_POST['contact_id'] : null;
        $contactPhone = trim($_POST['contact_phone'] ?? '');
        $contactEmail = trim($_POST['contact_email'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $purpose = trim($_POST['prg_evnt_purpose'] ?? '');
        $goal = trim($_POST['goal'] ?? '');
        $audience = trim($_POST['audience_target'] ?? '');
        $attendEst = !empty($_POST['attend_estimate']) ? (int)$_POST['attend_estimate'] : null;
        $notes = trim($_POST['notes'] ?? '');
        $reqReg = !empty($_POST['requires_registration']) ? 1 : 0;
        $reqFee = !empty($_POST['requires_fee']) ? 1 : 0;
        $regFee = $reqFee ? floatval($_POST['registration_fee'] ?? 0) : 0.00;

        if (empty($eventName) || $minCommId <= 0 || empty($location)) {
            sendEventJson(['success' => false, 'error' => 'Please complete all required fields.'], 400);
        }

        $db->begin_transaction();

        try {
            if ($prgevntId > 0) {
                $stmt = $db->prepare("
                    UPDATE programs_events
                    SET prg_evnt_name = ?, min_comm_id = ?, contact_id = ?, contact_phone = ?, contact_email = ?,
                        location = ?, prg_evnt_purpose = ?, goal = ?, audience_target = ?, attend_estimate = ?,
                        notes = ?, requires_registration = ?, requires_fee = ?, registration_fee = ?
                    WHERE prg_evnt_id = ?
                ");
                $stmt->bind_param("siissssssiiiidi", $eventName, $minCommId, $contactId, $contactPhone, $contactEmail, $location, $purpose, $goal, $audience, $attendEst, $notes, $reqReg, $reqFee, $regFee, $prgevntId);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $db->prepare("
                    INSERT INTO programs_events
                    (prg_evnt_name, min_comm_id, contact_id, contact_phone, contact_email, location, prg_evnt_purpose, goal, audience_target, attend_estimate, notes, requires_registration, requires_fee, registration_fee)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("siissssssiiiid", $eventName, $minCommId, $contactId, $contactPhone, $contactEmail, $location, $purpose, $goal, $audience, $attendEst, $notes, $reqReg, $reqFee, $regFee);
                $stmt->execute();
                $prgevntId = $stmt->insert_id;
                $stmt->close();
            }

            // Sync Schedules
            $db->query("DELETE FROM prg_evnt_schedules WHERE prg_evnt_id = $prgevntId");
            if (!empty($_POST['start_datetimes']) && is_array($_POST['start_datetimes'])) {
                $schStmt = $db->prepare("INSERT INTO prg_evnt_schedules (prg_evnt_id, activity_scheduled, start_datetime, end_datetime) VALUES (?, ?, ?, ?)");
                foreach ($_POST['start_datetimes'] as $idx => $startDt) {
                    if (empty($startDt)) continue;
                    $actScheduled = $_POST['activity_scheduled'][$idx] ?? '';
                    $endDt = !empty($_POST['end_datetimes'][$idx]) ? $_POST['end_datetimes'][$idx] : null;
                    $schStmt->bind_param("isss", $prgevntId, $actScheduled, $startDt, $endDt);
                    $schStmt->execute();
                }
                $schStmt->close();
            }

            // Sync Budgets
            $db->query("DELETE FROM prg_evnt_budget_items WHERE prg_evnt_id = $prgevntId");
            if (!empty($_POST['budget_desc']) && is_array($_POST['budget_desc'])) {
                $budStmt = $db->prepare("INSERT INTO prg_evnt_budget_items (prg_evnt_id, item_description, item_type, amount) VALUES (?, ?, ?, ?)");
                foreach ($_POST['budget_desc'] as $idx => $bDesc) {
                    $bDesc = trim($bDesc);
                    if (empty($bDesc)) continue;
                    $bType = $_POST['budget_type'][$idx] ?? 'Expense';
                    $bAmt = floatval($_POST['budget_amount'][$idx] ?? 0);
                    $budStmt->bind_param("issd", $prgevntId, $bDesc, $bType, $bAmt);
                    $budStmt->execute();
                }
                $budStmt->close();
            }

            // Sync Support Ministries
            $db->query("DELETE FROM prg_evnt_min_support WHERE prg_evnt_id = $prgevntId");
            if (!empty($_POST['support_min_ids']) && is_array($_POST['support_min_ids'])) {
                $supStmt = $db->prepare("INSERT INTO prg_evnt_min_support (prg_evnt_id, min_comm_id) VALUES (?, ?)");
                foreach (array_unique($_POST['support_min_ids']) as $sMinId) {
                    $sMinId = (int)$sMinId;
                    if ($sMinId > 0) {
                        $supStmt->bind_param("ii", $prgevntId, $sMinId);
                        $supStmt->execute();
                    }
                }
                $supStmt->close();
            }

            // Process File Uploads (stored as blobs in document_lib)
            if (!empty($_FILES['attach_files']['name']) && is_array($_FILES['attach_files']['name'])) {
                $attStmt = $db->prepare("INSERT INTO document_lib (entity_type, entity_id, doc_category_id, document_name, document_mime, document_size, document_data, uploaded_at) VALUES ('event', ?, ?, ?, ?, ?, ?, NOW())");

                foreach ($_FILES['attach_files']['name'] as $idx => $fileName) {
                    if ($_FILES['attach_files']['error'][$idx] === UPLOAD_ERR_OK) {
                        $catId = !empty($_POST['attach_cat_ids'][$idx]) ? (int)$_POST['attach_cat_ids'][$idx] : null;
                        $tmpName = $_FILES['attach_files']['tmp_name'][$idx];
                        $fileSize = (int)$_FILES['attach_files']['size'][$idx];

                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime = $finfo->file($tmpName) ?: 'application/octet-stream';
                        $blob = file_get_contents($tmpName);

                        // Columns: entity_id, doc_category_id, document_name, document_mime, document_size, document_data(blob)
                        $catId = $catId !== null ? $catId : null;
                        $attStmt->bind_param("iissib", $prgevntId, $catId, $fileName, $mime, $fileSize, $blob);
                        $attStmt->send_long_data(4, $blob);
                        $attStmt->execute();
                    }
                }
                $attStmt->close();
            }

            $db->commit();
            sendEventJson(['success' => true, 'message' => 'Event saved successfully.', 'prg_evnt_id' => $prgevntId]);

        } catch (Exception $e) {
            $db->rollback();
            sendEventJson(['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()], 500);
        }
        break;

    // -------------------------------------------------------------------------
    // DELETE EVENT AND ASSOCIATED DATA
    // -------------------------------------------------------------------------
    case 'delete_event':
        requireAdmin();
        $prgevntId = (int)($_POST['prg_evnt_id'] ?? 0);
        if ($prgevntId <= 0) {
            sendEventJson(['success' => false, 'error' => 'Invalid Event ID.'], 400);
        }

        $db->begin_transaction();
        try {
            $db->query("DELETE FROM prg_evnt_schedules WHERE prg_evnt_id = $prgevntId");
            $db->query("DELETE FROM prg_evnt_budget_items WHERE prg_evnt_id = $prgevntId");
            $db->query("DELETE FROM prg_evnt_budget_actuals WHERE prg_evnt_id = $prgevntId");
            $db->query("DELETE FROM prg_evnt_min_support WHERE prg_evnt_id = $prgevntId");
            $db->query("DELETE FROM prg_evnt_registrations WHERE prg_evnt_id = $prgevntId");
            $db->query("DELETE FROM prg_evnt_attendance WHERE prg_evnt_id = $prgevntId");
            $db->query("DELETE FROM document_lib WHERE entity_type = 'event' AND entity_id = $prgevntId");

            $stmt = $db->prepare("DELETE FROM programs_events WHERE prg_evnt_id = ?");
            $stmt->bind_param("i", $prgevntId);
            $stmt->execute();
            $stmt->close();

            $db->commit();
            sendEventJson(['success' => true, 'message' => 'Event deleted successfully.']);
        } catch (Exception $e) {
            $db->rollback();
            sendEventJson(['success' => false, 'error' => 'Failed to delete event.'], 500);
        }
        break;

    // -------------------------------------------------------------------------
    // DELETE ATTACHMENT
    // -------------------------------------------------------------------------
    case 'delete_attachment':
        requireAdmin();
        $attId = (int)($_POST['attachment_id'] ?? 0);
        if ($attId <= 0) {
            sendEventJson(['success' => false, 'error' => 'Invalid attachment ID.'], 400);
        }

        $stmt = $db->prepare("SELECT document_id FROM document_lib WHERE document_id = ? AND entity_type = 'event'");
        $stmt->bind_param("i", $attId);
        $stmt->execute();
        $att = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($att) {
            $delStmt = $db->prepare("DELETE FROM document_lib WHERE document_id = ?");
            $delStmt->bind_param("i", $attId);
            $delStmt->execute();
            $delStmt->close();
            sendEventJson(['success' => true, 'message' => 'Attachment deleted successfully.']);
        } else {
            sendEventJson(['success' => false, 'error' => 'Attachment not found.'], 404);
        }
        break;

    // -------------------------------------------------------------------------
    // FETCH BUDGET LINE ITEMS FOR SELECTION DROPDOWNS
    // -------------------------------------------------------------------------
    case 'get_event_budget_items':
        $prgevntId = (int)($_GET['prg_evnt_id'] ?? 0);
        if ($prgevntId <= 0) {
            sendEventJson(['success' => false, 'error' => 'Invalid Event ID.'], 400);
        }

        $stmt = $db->prepare("SELECT budget_item_id, item_description, item_type FROM prg_evnt_budget_items WHERE prg_evnt_id = ? ORDER BY item_description ASC");
        $stmt->bind_param("i", $prgevntId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        sendEventJson(['success' => true, 'budget_items' => $items]);
        break;

    // -------------------------------------------------------------------------
    // SAVE MANUAL ACTUAL AMOUNT TRANSACTION
    // -------------------------------------------------------------------------
    case 'save_actual':
        requireAdmin();
        $prgevntId    = (int)($_POST['prg_evnt_id'] ?? 0);
        $budgetItemId = !empty($_POST['budget_item_id']) ? (int)$_POST['budget_item_id'] : null;
        $entryType    = $_POST['entry_type'] ?? 'Expense';
        $desc         = trim($_POST['description'] ?? '');
        $amount       = floatval($_POST['amount'] ?? 0);

        if ($prgevntId <= 0 || empty($desc) || $amount <= 0) {
            sendEventJson(['success' => false, 'error' => 'Please provide a valid description and positive amount.'], 400);
        }

        $stmt = $db->prepare("INSERT INTO prg_evnt_budget_actuals (prg_evnt_id, budget_item_id, entry_type, description, amount) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iissd", $prgevntId, $budgetItemId, $entryType, $desc, $amount);

        if ($stmt->execute()) {
            $stmt->close();
            sendEventJson(['success' => true, 'message' => 'Actual transaction recorded successfully.']);
        } else {
            $stmt->close();
            sendEventJson(['success' => false, 'error' => 'Database error recording actual transaction.'], 500);
        }
        break;

    // -------------------------------------------------------------------------
    // ROSTER & ATTENDANCE OPERATIONS
    // -------------------------------------------------------------------------
    case 'get_event_roster':
        $prgevntId = (int)($_GET['prg_evnt_id'] ?? 0);
        if ($prgevntId <= 0) {
            sendEventJson(['success' => false, 'error' => 'Invalid Event ID.'], 400);
        }

        $regStmt = $db->prepare("
            SELECT r.registration_id, r.contact_id, r.payment_status, r.payment_method, r.amount_paid, r.is_completed,
                   CONCAT(c.first_name, ' ', c.last_name) AS full_name
            FROM prg_evnt_registrations r
            JOIN contacts c ON r.contact_id = c.contact_id
            WHERE r.prg_evnt_id = ?
            ORDER BY c.last_name ASC, c.first_name ASC
        ");
        $regStmt->bind_param("i", $prgevntId);
        $regStmt->execute();
        $registrations = $regStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $regStmt->close();

        $attStmt = $db->prepare("SELECT contact_id FROM prg_evnt_attendance WHERE prg_evnt_id = ?");
        $attStmt->bind_param("i", $prgevntId);
        $attStmt->execute();
        $attendance = $attStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $attStmt->close();

        sendEventJson(['success' => true, 'registrations' => $registrations, 'attendance' => $attendance]);
        break;

    case 'register_attendee':
        $prgevntId = (int)($_POST['prg_evnt_id'] ?? 0);
        $contactId = (int)($_POST['contact_id'] ?? 0);

        if ($prgevntId <= 0 || $contactId <= 0) {
            sendEventJson(['success' => false, 'error' => 'Invalid event or contact ID.'], 400);
        }

        $paymentMethod = trim($_POST['payment_method'] ?? 'None');
        $paymentStatus = trim($_POST['payment_status'] ?? 'Pending');
        $amountPaid    = floatval($_POST['amount_paid'] ?? 0);

        // Business rules: Online payments always start Pending; Waived means nothing was paid.
        if (strcasecmp($paymentMethod, 'Online') === 0) {
            $paymentStatus = 'Pending';
        }
        if (strcasecmp($paymentStatus, 'Waived') === 0) {
            $amountPaid = 0.0;
        }
        $isCompleted   = in_array(strtolower($paymentStatus), ['paid', 'completed', 'waived'], true) ? 1 : 0;

        $reg = $db->prepare("SELECT registration_id FROM prg_evnt_registrations WHERE prg_evnt_id = ? AND contact_id = ?");
        $reg->bind_param("ii", $prgevntId, $contactId);
        $reg->execute();
        $existing = $reg->get_result()->fetch_assoc();
        $reg->close();
        if ($existing) {
            sendEventJson(['success' => false, 'message' => 'Contact is already registered for this event.'], 400);
        }

        $stmt = $db->prepare("INSERT INTO prg_evnt_registrations (prg_evnt_id, contact_id, payment_status, payment_method, amount_paid, is_completed) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissdi", $prgevntId, $contactId, $paymentStatus, $paymentMethod, $amountPaid, $isCompleted);
        if ($stmt->execute()) {
            $stmt->close();
            logRegistrationFeeActual($db, $prgevntId, $contactId, $amountPaid, $isCompleted, $paymentStatus);
            sendEventJson(['success' => true, 'message' => 'Attendee registered successfully.']);
        } else {
            $stmt->close();
            sendEventJson(['success' => false, 'message' => 'Failed to register attendee.'], 500);
        }
        break;

    case 'save_registration':
        requireAdmin();
        $regId     = (int)($_POST['registration_id'] ?? 0);
        $paymentMethod = trim($_POST['payment_method'] ?? 'None');
        $paymentStatus = trim($_POST['payment_status'] ?? 'Pending');
        $amountPaid    = floatval($_POST['amount_paid'] ?? 0);

        // Business rules: Online payments always start Pending; Waived means nothing was paid.
        if (strcasecmp($paymentMethod, 'Online') === 0) {
            $paymentStatus = 'Pending';
        }
        if (strcasecmp($paymentStatus, 'Waived') === 0) {
            $amountPaid = 0.0;
        }
        $isCompleted   = in_array(strtolower($paymentStatus), ['paid', 'completed', 'waived'], true) ? 1 : 0;

        if ($regId <= 0) {
            sendEventJson(['success' => false, 'error' => 'Invalid registration ID.'], 400);
        }

        $stmt = $db->prepare("UPDATE prg_evnt_registrations SET payment_method = ?, payment_status = ?, amount_paid = ?, is_completed = ? WHERE registration_id = ?");
        $stmt->bind_param("ssdii", $paymentMethod, $paymentStatus, $amountPaid, $isCompleted, $regId);
        if ($stmt->execute()) {
            $stmt->close();

            // Pull event/contact ids so the fee actual can be synced
            $feStmt = $db->prepare("SELECT prg_evnt_id, contact_id FROM prg_evnt_registrations WHERE registration_id = ?");
            $feStmt->bind_param("i", $regId);
            $feStmt->execute();
            $row = $feStmt->get_result()->fetch_assoc();
            $feStmt->close();
            if ($row) {
                logRegistrationFeeActual($db, (int)$row['prg_evnt_id'], (int)$row['contact_id'], $amountPaid, $isCompleted, $paymentStatus);
            }

            sendEventJson(['success' => true, 'message' => 'Registration updated successfully.']);
        } else {
            $stmt->close();
            sendEventJson(['success' => false, 'error' => 'Failed to update registration.'], 500);
        }
        break;

    case 'remove_registration':
        requireAdmin();
        $prgevntId = (int)($_POST['prg_evnt_id'] ?? 0);
        $contactId = (int)($_POST['contact_id'] ?? 0);

        // Remove any associated registration fee actual before deleting the registration
        logRegistrationFeeActual($db, $prgevntId, $contactId, 0.0, 0, 'Pending');

        if ($prgevntId <= 0 || $contactId <= 0) {
            sendEventJson(['success' => false, 'message' => 'Invalid event or contact ID.'], 400);
        }

        $stmt = $db->prepare("DELETE FROM prg_evnt_registrations WHERE prg_evnt_id = ? AND contact_id = ?");
        $stmt->bind_param("ii", $prgevntId, $contactId);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();

        if ($deleted === 0) {
            sendEventJson(['success' => false, 'message' => 'No matching registration found to remove.'], 404);
        }

        $attStmt = $db->prepare("DELETE FROM prg_evnt_attendance WHERE prg_evnt_id = ? AND contact_id = ?");
        $attStmt->bind_param("ii", $prgevntId, $contactId);
        $attStmt->execute();
        $attStmt->close();

        sendEventJson(['success' => true, 'message' => 'Registration removed.']);
        break;

    case 'toggle_attendance':
    case 'record_attendance':
        requireAdmin();
        $prgevntId = (int)($_POST['prg_evnt_id'] ?? 0);
        $contactId = (int)($_POST['contact_id'] ?? 0);

        $chkStmt = $db->prepare("SELECT attendance_id FROM prg_evnt_attendance WHERE prg_evnt_id = ? AND contact_id = ?");
        $chkStmt->bind_param("ii", $prgevntId, $contactId);
        $chkStmt->execute();
        $att = $chkStmt->get_result()->fetch_assoc();
        $chkStmt->close();

        if ($att) {
            if ($action === 'toggle_attendance') {
                $delStmt = $db->prepare("DELETE FROM prg_evnt_attendance WHERE attendance_id = ?");
                $delStmt->bind_param("i", $att['attendance_id']);
                $delStmt->execute();
                $delStmt->close();
                sendEventJson(['success' => true, 'message' => 'Attendance removed.']);
            } else {
                sendEventJson(['success' => true, 'message' => 'Already checked in.']);
            }
        } else {
            $insStmt = $db->prepare("INSERT INTO prg_evnt_attendance (prg_evnt_id, contact_id, check_in_time) VALUES (?, ?, NOW())");
            $insStmt->bind_param("ii", $prgevntId, $contactId);
            $insStmt->execute();
            $insStmt->close();
            sendEventJson(['success' => true, 'message' => 'Checked in successfully.']);
        }
        break;

    default:
        sendEventJson(['success' => false, 'error' => 'Invalid action endpoint.'], 400);
        break;
}