<?php

function parseProgramActivityDateTime(string $value): string
{
    $value = str_replace('T', ' ', trim($value));
    foreach (['!Y-m-d H:i:s', '!Y-m-d H:i'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date && (!$errors || (!$errors['warning_count'] && !$errors['error_count']))
            && (int)$date->format('Y') >= 1000) {
            return $date->format('Y-m-d H:i:s');
        }
    }
    throw new InvalidArgumentException('Activity dates must be valid dates and times.');
}

function programCalendarOccursOn(array $schedule, string $date): bool
{
    if ($schedule['calendar_weekdays'] === null) {
        return false;
    }
    $start = substr($schedule['start_datetime'], 0, 10);
    $end = substr($schedule['end_datetime'] ?? $schedule['start_datetime'], 0, 10);
    $weekday = (int)(new DateTimeImmutable($date))->format('w');
    return $date >= $start && $date <= $end
        && (((int)$schedule['calendar_weekdays'] & (1 << $weekday)) !== 0);
}

function validateProgramCalendarSchedule(mysqli $db, array $schedule): void
{
    if ($schedule['end_datetime'] !== null && $schedule['end_datetime'] < $schedule['start_datetime']) {
        throw new InvalidArgumentException('Activity end cannot be before its start.');
    }
    if ($schedule['calendar_weekdays'] !== null) {
        $date = new DateTimeImmutable(substr($schedule['start_datetime'], 0, 10));
        $hasOccurrence = false;
        for ($i = 0; $i < 7; $i++, $date = $date->modify('+1 day')) {
            if (programCalendarOccursOn($schedule, $date->format('Y-m-d'))) {
                $hasOccurrence = true;
                break;
            }
        }
        if (!$hasOccurrence) {
            throw new InvalidArgumentException('The activity date range contains none of the selected weekdays.');
        }
    }
    $stmt = $db->prepare('SELECT occurrence_date FROM calendar_program_exceptions WHERE schedule_id = ?');
    $stmt->bind_param('i', $schedule['schedule_id']);
    $stmt->execute();
    $exceptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($exceptions as $exception) {
        if (!programCalendarOccursOn($schedule, $exception['occurrence_date'])) {
            throw new InvalidArgumentException('This change would leave an exception outside the activity schedule. Remove or change that exception first in Calendar Schedule.');
        }
    }
}
