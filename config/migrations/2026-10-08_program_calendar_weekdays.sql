-- Run from the repository root with the mysql client against nbbtm_central.
-- NULL preserves continuous activities. Bits 0-6 select Sunday-Saturday.
ALTER TABLE prg_evnt_schedules
    MODIFY COLUMN end_datetime DATETIME NULL,
    ADD COLUMN calendar_weekdays TINYINT UNSIGNED NULL DEFAULT NULL,
    ADD CONSTRAINT chk_program_calendar_weekdays
        CHECK (calendar_weekdays IS NULL OR calendar_weekdays BETWEEN 1 AND 127);

CREATE TABLE calendar_program_exceptions (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    occurrence_date DATE NOT NULL,
    is_cancelled TINYINT(1) NOT NULL DEFAULT 1,
    replacement_start DATETIME NULL,
    replacement_end DATETIME NULL,
    UNIQUE KEY program_occurrence (schedule_id, occurrence_date),
    CONSTRAINT fk_calendar_program_exception
        FOREIGN KEY (schedule_id) REFERENCES prg_evnt_schedules (schedule_id) ON DELETE CASCADE,
    CONSTRAINT chk_calendar_program_exception
        CHECK ((is_cancelled = 1 AND replacement_start IS NULL AND replacement_end IS NULL)
            OR (is_cancelled = 0 AND replacement_start IS NOT NULL
                AND (replacement_end IS NULL OR replacement_end >= replacement_start)))
) DEFAULT CHARSET=utf8mb4;

SOURCE config/mysql/calendar_build_range.sql;
