<?php
// calendar_schedule_api.php - Recurring services, exceptions, and calendar reminders
ob_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/include/program_calendar.php';

requireAdmin();

header('Content-Type: application/json; charset=utf-8');

const WEEKDAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function respond(int $status, array $payload): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function fail(string $message, int $status = 400): void
{
    respond($status, ['status' => 'error', 'message' => $message]);
}

function parseDateValue(?string $value, string $label, bool $required = true): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        if ($required) {
            fail("$label is required.");
        }
        return null;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || (int)$date->format('Y') < 1000) {
        fail("$label must be a valid date.");
    }
    return $value;
}

function parseTimeValue(?string $value, string $label, bool $required = true): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        if ($required) {
            fail("$label is required.");
        }
        return null;
    }
    if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $m)) {
        fail("$label must be a valid time.");
    }
    return sprintf('%s:%s:%s', $m[1], $m[2], $m[3] ?? '00');
}

function parseDateTimeValue(?string $value, string $label, bool $required = true): ?string
{
    $value = str_replace('T', ' ', trim((string)$value));
    if ($value === '') {
        if ($required) {
            fail("$label is required.");
        }
        return null;
    }
    foreach (['!Y-m-d H:i:s', '!Y-m-d H:i'] as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        $errors = DateTime::getLastErrors();
        if ($dt && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && (int)$dt->format('Y') >= 1000) {
            return $dt->format('Y-m-d H:i:s');
        }
    }
    fail("$label must be a valid date and time.");
}

const ORDINALS = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 5 => '5th', -1 => 'Last'];

function fetchService(mysqli $db, int $serviceId): ?array
{
    $stmt = $db->prepare('SELECT id, name, recurrence, weekday, week_of_month, day_of_month, start_time, end_time, effective_start, effective_end, active, is_public FROM calendar_weekly_services WHERE id = ?');
    $stmt->bind_param('i', $serviceId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function describeRecurrence(array $s): string
{
    switch ($s['recurrence']) {
        case 'monthly_weekday':
            return ORDINALS[(int)$s['week_of_month']] . ' ' . WEEKDAY_NAMES[(int)$s['weekday']] . ' of each month';
        case 'monthly_date':
            $d = (int)$s['day_of_month'];
            $suffix = ($d % 100 >= 11 && $d % 100 <= 13) ? 'th' : (['th', 'st', 'nd', 'rd'][$d % 10] ?? 'th');
            return "the $d$suffix of each month";
        default:
            return 'every ' . WEEKDAY_NAMES[(int)$s['weekday']];
    }
}

function parseFlag(string $key): int
{
    return !empty($_POST[$key]) ? 1 : 0;
}

$action = $_REQUEST['action'] ?? '';
$writeActions = ['save_service', 'delete_service', 'save_exception', 'delete_exception', 'save_reminder', 'delete_reminder',
    'save_program_schedule', 'save_program_exception', 'delete_program_exception'];
if (in_array($action, $writeActions, true) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('This action requires a POST request.', 405);
}

$programTransaction = false;
try {
    switch ($action) {
        case 'list_program_schedules':
            $rows = $db->query(
                'SELECT s.*, p.prg_evnt_name,
                        (SELECT COUNT(*) FROM calendar_program_exceptions e WHERE e.schedule_id = s.schedule_id) AS exception_count
                 FROM prg_evnt_schedules s JOIN programs_events p ON p.prg_evnt_id = s.prg_evnt_id
                 ORDER BY p.prg_evnt_name, s.start_datetime, s.schedule_id'
            )->fetch_all(MYSQLI_ASSOC);
            respond(200, ['status' => 'success', 'data' => $rows]);

        case 'save_program_schedule':
        case 'save_program_exception':
            $scheduleId = (int)($_POST['schedule_id'] ?? 0);
            $db->begin_transaction();
            $programTransaction = true;
            $stmt = $db->prepare('SELECT * FROM prg_evnt_schedules WHERE schedule_id = ? FOR UPDATE');
            $stmt->bind_param('i', $scheduleId);
            $stmt->execute();
            $schedule = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$schedule) {
                $db->rollback();
                fail('Select a valid program activity.', 404);
            }
            if ($action === 'save_program_schedule') {
                $mode = $_POST['calendar_mode'] ?? '';
                if (!in_array($mode, ['continuous', 'weekdays'], true)) {
                    fail('Select a valid calendar display mode.');
                }
                $mask = null;
                if ($mode === 'weekdays') {
                    $days = $_POST['weekdays'] ?? [];
                    if (!is_array($days) || !$days) {
                        fail('Select at least one weekday.');
                    }
                    $mask = 0;
                    foreach ($days as $day) {
                        if (!is_string($day) || !preg_match('/^[0-6]$/', $day)) {
                            fail('Select valid weekdays.');
                        }
                        $mask |= 1 << (int)$day;
                    }
                }
                $schedule['calendar_weekdays'] = $mask;
                validateProgramCalendarSchedule($db, $schedule);
                $stmt = $db->prepare('UPDATE prg_evnt_schedules SET calendar_weekdays = ? WHERE schedule_id = ?');
                $stmt->bind_param('ii', $mask, $scheduleId);
                $stmt->execute();
                $stmt->close();
                $db->commit();
                $programTransaction = false;
                respond(200, ['status' => 'success', 'message' => 'Program calendar days saved.']);
            }
            $id = (int)($_POST['id'] ?? 0);
            $date = parseDateValue($_POST['occurrence_date'] ?? '', 'Occurrence date');
            if (!programCalendarOccursOn($schedule, $date)) {
                fail('Choose a selected weekday within the activity date range. Set the activity to selected weekdays first.');
            }
            $type = $_POST['exception_type'] ?? '';
            if (!in_array($type, ['cancel', 'reschedule'], true)) {
                fail('Select a valid exception type.');
            }
            $cancelled = $type === 'cancel' ? 1 : 0;
            $start = $cancelled ? null : parseDateTimeValue($_POST['replacement_start'] ?? '', 'Replacement start');
            $end = $cancelled ? null : parseDateTimeValue($_POST['replacement_end'] ?? '', 'Replacement end', false);
            if ($end !== null && $end < $start) {
                fail('Replacement end cannot be before replacement start.');
            }
            $stmt = $db->prepare('SELECT id FROM calendar_program_exceptions WHERE schedule_id = ? AND occurrence_date = ? AND id <> ?');
            $stmt->bind_param('isi', $scheduleId, $date, $id);
            $stmt->execute();
            $duplicate = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($duplicate) {
                fail('An exception already exists for this activity on that date.', 409);
            }
            if ($id > 0) {
                $stmt = $db->prepare('SELECT id FROM calendar_program_exceptions WHERE id = ?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $exists = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$exists) {
                    fail('Program exception not found.', 404);
                }
                $stmt = $db->prepare('UPDATE calendar_program_exceptions SET schedule_id = ?, occurrence_date = ?, is_cancelled = ?, replacement_start = ?, replacement_end = ? WHERE id = ?');
                $stmt->bind_param('isissi', $scheduleId, $date, $cancelled, $start, $end, $id);
            } else {
                $stmt = $db->prepare('INSERT INTO calendar_program_exceptions (schedule_id, occurrence_date, is_cancelled, replacement_start, replacement_end) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('isiss', $scheduleId, $date, $cancelled, $start, $end);
            }
            $stmt->execute();
            $stmt->close();
            $db->commit();
            $programTransaction = false;
            respond(200, ['status' => 'success', 'message' => 'Program exception saved.']);

        case 'list_program_exceptions':
            $rows = $db->query(
                'SELECT e.*, p.prg_evnt_name, s.activity_scheduled
                 FROM calendar_program_exceptions e
                 JOIN prg_evnt_schedules s ON s.schedule_id = e.schedule_id
                 JOIN programs_events p ON p.prg_evnt_id = s.prg_evnt_id
                 ORDER BY e.occurrence_date DESC, p.prg_evnt_name'
            )->fetch_all(MYSQLI_ASSOC);
            respond(200, ['status' => 'success', 'data' => $rows]);

        case 'delete_program_exception':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM calendar_program_exceptions WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();
            if (!$deleted) {
                fail('Program exception not found.', 404);
            }
            respond(200, ['status' => 'success', 'message' => 'Program exception deleted.']);

        case 'list_services':
            $result = $db->query(
                "SELECT s.id, s.name, s.recurrence, s.weekday, s.week_of_month, s.day_of_month, s.start_time, s.end_time,
                        s.effective_start, s.effective_end, s.active, s.is_public,
                        (SELECT COUNT(*) FROM calendar_service_exceptions e WHERE e.service_id = s.id) AS exception_count
                 FROM calendar_weekly_services s
                 ORDER BY s.active DESC, FIELD(s.recurrence, 'weekly', 'monthly_weekday', 'monthly_date'),
                          COALESCE(s.weekday, 9), s.week_of_month = -1, s.week_of_month, s.day_of_month, s.start_time, s.name"
            );
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            foreach ($rows as &$row) {
                $row['schedule'] = describeRecurrence($row);
            }
            unset($row);
            respond(200, ['status' => 'success', 'data' => $rows]);

        case 'list_exceptions':
            $serviceId = (int)($_GET['service_id'] ?? 0);
            $sql = 'SELECT e.id, e.service_id, s.name AS service_name, e.occurrence_date, e.is_cancelled,
                           e.replacement_start, e.replacement_end
                    FROM calendar_service_exceptions e
                    JOIN calendar_weekly_services s ON s.id = e.service_id';
            if ($serviceId > 0) {
                $stmt = $db->prepare($sql . ' WHERE e.service_id = ? ORDER BY e.occurrence_date DESC');
                $stmt->bind_param('i', $serviceId);
                $stmt->execute();
                $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            } else {
                $rows = $db->query($sql . ' ORDER BY e.occurrence_date DESC, s.name')->fetch_all(MYSQLI_ASSOC);
            }
            respond(200, ['status' => 'success', 'data' => $rows]);

        case 'save_service':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 255) {
                fail('Service name is required and must be 255 characters or fewer.');
            }
            $recurrence = (string)($_POST['recurrence'] ?? 'weekly');
            if (!in_array($recurrence, ['weekly', 'monthly_weekday', 'monthly_date'], true)) {
                fail('Select a valid repeat pattern.');
            }
            $weekday = null;
            $weekOfMonth = null;
            $dayOfMonth = null;
            if ($recurrence === 'monthly_date') {
                $dayRaw = (string)($_POST['day_of_month'] ?? '');
                if (!preg_match('/^([1-9]|[12]\d|3[01])$/', $dayRaw)) {
                    fail('Day of month must be between 1 and 31.');
                }
                $dayOfMonth = (int)$dayRaw;
            } else {
                $weekdayRaw = (string)($_POST['weekday'] ?? '');
                if (!preg_match('/^[0-6]$/', $weekdayRaw)) {
                    fail('Select a valid weekday.');
                }
                $weekday = (int)$weekdayRaw;
                if ($recurrence === 'monthly_weekday') {
                    $weekRaw = (string)($_POST['week_of_month'] ?? '');
                    if (!preg_match('/^(-1|[1-5])$/', $weekRaw)) {
                        fail('Select which week of the month.');
                    }
                    $weekOfMonth = (int)$weekRaw;
                }
            }
            $startTime = parseTimeValue($_POST['start_time'] ?? '', 'Start time');
            $endTime = parseTimeValue($_POST['end_time'] ?? '', 'End time', false);
            $effectiveStart = parseDateValue($_POST['effective_start'] ?? '', 'Effective start');
            $effectiveEnd = parseDateValue($_POST['effective_end'] ?? '', 'Effective end', false);
            if ($effectiveEnd !== null && $effectiveEnd < $effectiveStart) {
                fail('Effective end cannot be before effective start.');
            }
            $active = parseFlag('active');
            $isPublic = parseFlag('is_public');

            if ($id > 0) {
                if (!fetchService($db, $id)) {
                    fail('Service not found.', 404);
                }
                // The calendar ignores exceptions that are no longer an occurrence of the service, so block changes that would orphan them.
                $stmt = $db->prepare(
                    'SELECT COUNT(*) AS cnt FROM calendar_service_exceptions
                     WHERE service_id = ?
                       AND (calendar_service_occurs_on(?, ?, ?, ?, occurrence_date) = 0
                            OR occurrence_date < ? OR (? IS NOT NULL AND occurrence_date > ?))'
                );
                $stmt->bind_param('isiiisss', $id, $recurrence, $weekday, $weekOfMonth, $dayOfMonth, $effectiveStart, $effectiveEnd, $effectiveEnd);
                $stmt->execute();
                $mismatched = (int)$stmt->get_result()->fetch_assoc()['cnt'];
                $stmt->close();
                if ($mismatched > 0) {
                    fail("$mismatched exception(s) for this service would no longer fall on one of its scheduled dates. Update or delete those exceptions first.", 409);
                }

                $stmt = $db->prepare('UPDATE calendar_weekly_services SET name = ?, recurrence = ?, weekday = ?, week_of_month = ?, day_of_month = ?, start_time = ?, end_time = ?, effective_start = ?, effective_end = ?, active = ?, is_public = ? WHERE id = ?');
                $stmt->bind_param('ssiiissssiii', $name, $recurrence, $weekday, $weekOfMonth, $dayOfMonth, $startTime, $endTime, $effectiveStart, $effectiveEnd, $active, $isPublic, $id);
                $stmt->execute();
                $stmt->close();
                respond(200, ['status' => 'success', 'message' => 'Service updated.', 'id' => $id]);
            }

            $stmt = $db->prepare('INSERT INTO calendar_weekly_services (name, recurrence, weekday, week_of_month, day_of_month, start_time, end_time, effective_start, effective_end, active, is_public) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssiiissssii', $name, $recurrence, $weekday, $weekOfMonth, $dayOfMonth, $startTime, $endTime, $effectiveStart, $effectiveEnd, $active, $isPublic);
            $stmt->execute();
            $newId = $db->insert_id;
            $stmt->close();
            respond(200, ['status' => 'success', 'message' => 'Service added.', 'id' => $newId]);

        case 'delete_service':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                fail('Invalid service.');
            }
            $stmt = $db->prepare('DELETE FROM calendar_weekly_services WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();
            if ($deleted < 1) {
                fail('Service not found.', 404);
            }
            respond(200, ['status' => 'success', 'message' => 'Service and its exceptions deleted.']);

        case 'save_exception':
            $id = (int)($_POST['id'] ?? 0);
            $serviceId = (int)($_POST['service_id'] ?? 0);
            $service = $serviceId > 0 ? fetchService($db, $serviceId) : null;
            if (!$service) {
                fail('Select a valid service.');
            }
            $occurrenceDate = parseDateValue($_POST['occurrence_date'] ?? '', 'Occurrence date');
            $stmt = $db->prepare('SELECT calendar_service_occurs_on(?, ?, ?, ?, ?) AS occurs');
            $stmt->bind_param('siiis', $service['recurrence'], $service['weekday'], $service['week_of_month'], $service['day_of_month'], $occurrenceDate);
            $stmt->execute();
            $occurs = (int)$stmt->get_result()->fetch_assoc()['occurs'];
            $stmt->close();
            if ($occurs !== 1) {
                fail('Occurrence date must be one of this service\'s scheduled dates (' . describeRecurrence($service) . ').');
            }
            if ($occurrenceDate < $service['effective_start'] || ($service['effective_end'] !== null && $occurrenceDate > $service['effective_end'])) {
                fail('Occurrence date must be within the service\'s effective date range.');
            }

            $isCancelled = (($_POST['exception_type'] ?? '') === 'cancel') ? 1 : 0;
            $replacementStart = null;
            $replacementEnd = null;
            if (!$isCancelled) {
                $replacementStart = parseDateTimeValue($_POST['replacement_start'] ?? '', 'Replacement start');
                $replacementEnd = parseDateTimeValue($_POST['replacement_end'] ?? '', 'Replacement end', false);
                if ($replacementEnd !== null && $replacementEnd < $replacementStart) {
                    fail('Replacement end cannot be before replacement start.');
                }
            }

            $stmt = $db->prepare('SELECT id FROM calendar_service_exceptions WHERE service_id = ? AND occurrence_date = ? AND id <> ?');
            $stmt->bind_param('isi', $serviceId, $occurrenceDate, $id);
            $stmt->execute();
            $duplicate = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($duplicate) {
                fail('An exception already exists for this service on that date.', 409);
            }

            if ($id > 0) {
                $stmt = $db->prepare('UPDATE calendar_service_exceptions SET service_id = ?, occurrence_date = ?, is_cancelled = ?, replacement_start = ?, replacement_end = ? WHERE id = ?');
                $stmt->bind_param('isissi', $serviceId, $occurrenceDate, $isCancelled, $replacementStart, $replacementEnd, $id);
                $stmt->execute();
                $stmt->close();
                respond(200, ['status' => 'success', 'message' => 'Exception updated.', 'id' => $id]);
            }

            $stmt = $db->prepare('INSERT INTO calendar_service_exceptions (service_id, occurrence_date, is_cancelled, replacement_start, replacement_end) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('isiss', $serviceId, $occurrenceDate, $isCancelled, $replacementStart, $replacementEnd);
            $stmt->execute();
            $newId = $db->insert_id;
            $stmt->close();
            respond(200, ['status' => 'success', 'message' => 'Exception added.', 'id' => $newId]);

        case 'delete_exception':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                fail('Invalid exception.');
            }
            $stmt = $db->prepare('DELETE FROM calendar_service_exceptions WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();
            if ($deleted < 1) {
                fail('Exception not found.', 404);
            }
            respond(200, ['status' => 'success', 'message' => 'Exception deleted.']);

        case 'list_reminders':
            $scope = ($_GET['scope'] ?? 'upcoming') === 'all' ? 'all' : 'upcoming';
            $sql = 'SELECT id, title, start_date, end_date, start_time, end_time, is_public FROM calendar_special_dates';
            if ($scope === 'upcoming') {
                $sql .= ' WHERE COALESCE(end_date, start_date) >= CURRENT_DATE() ORDER BY start_date, start_time IS NOT NULL, start_time, title';
            } else {
                $sql .= ' ORDER BY start_date DESC, start_time, title';
            }
            respond(200, ['status' => 'success', 'data' => $db->query($sql)->fetch_all(MYSQLI_ASSOC)]);

        case 'save_reminder':
            $id = (int)($_POST['id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 255) {
                fail('Title is required and must be 255 characters or fewer.');
            }
            $startDate = parseDateValue($_POST['start_date'] ?? '', 'Date');
            $endDate = parseDateValue($_POST['end_date'] ?? '', 'Through date', false);
            if ($endDate !== null && $endDate < $startDate) {
                fail('Through date cannot be before the start date.');
            }
            if ($endDate === $startDate) {
                $endDate = null;
            }
            $startTime = parseTimeValue($_POST['start_time'] ?? '', 'Start time', false);
            $endTime = parseTimeValue($_POST['end_time'] ?? '', 'End time', false);
            if ($endTime !== null && $startTime === null) {
                fail('Enter a start time when an end time is given.');
            }
            $isPublic = parseFlag('is_public');

            if ($id > 0) {
                $stmt = $db->prepare('UPDATE calendar_special_dates SET title = ?, start_date = ?, end_date = ?, start_time = ?, end_time = ?, is_public = ? WHERE id = ?');
                $stmt->bind_param('sssssii', $title, $startDate, $endDate, $startTime, $endTime, $isPublic, $id);
                $stmt->execute();
                $stmt->close();
                respond(200, ['status' => 'success', 'message' => 'Reminder updated.', 'id' => $id]);
            }
            $stmt = $db->prepare('INSERT INTO calendar_special_dates (title, start_date, end_date, start_time, end_time, is_public) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('sssssi', $title, $startDate, $endDate, $startTime, $endTime, $isPublic);
            $stmt->execute();
            $newId = $db->insert_id;
            $stmt->close();
            respond(200, ['status' => 'success', 'message' => 'Reminder added.', 'id' => $newId]);

        case 'delete_reminder':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                fail('Invalid reminder.');
            }
            $stmt = $db->prepare('DELETE FROM calendar_special_dates WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();
            if ($deleted < 1) {
                fail('Reminder not found.', 404);
            }
            respond(200, ['status' => 'success', 'message' => 'Reminder deleted.']);

        default:
            fail('Invalid action.');
    }
} catch (InvalidArgumentException $e) {
    if ($programTransaction) {
        $db->rollback();
    }
    fail($e->getMessage());
} catch (mysqli_sql_exception $e) {
    if ($programTransaction) {
        $db->rollback();
    }
    error_log('calendar_schedule_api: ' . $e->getMessage());
    fail('Database error: the change could not be saved.', 500);
}
