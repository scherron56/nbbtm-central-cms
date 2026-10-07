-- Public calendar support.
--  * programs_events.is_public marks programs/events that may appear on the
--    public (signed-out) calendar. Existing and new events default to private.
--  * calendar_build_range gains p_include_private (1 = members' calendar with
--    birthdays, anniversaries and all programs; 0 = public calendar).
--  * The month grid moves to calendar_month_grid; getMonthCalendar keeps its
--    3-parameter signature (members' view) and getPublicMonthCalendar is new.
--  * getCalendarRangeEntries and calendar_build_entries pass 1 so their
--    results are unchanged.
-- Run with the mysql client (uses DELIMITER) against nbbtm_central.

ALTER TABLE programs_events
    ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER registration_fee;

DELIMITER %%
DROP PROCEDURE IF EXISTS `calendar_build_range`%%
CREATE PROCEDURE `calendar_build_range`(IN p_start_month INT, IN p_start_year INT, IN p_month_count INT, IN p_include_private TINYINT)
BEGIN
    DECLARE v_start DATE;
    DECLARE v_end DATE;
    DECLARE v_date DATE;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        DROP TEMPORARY TABLE IF EXISTS tmp_calendar_days, tmp_calendar_raw, tmp_calendar_entries, tmp_calendar_grid;
        RESIGNAL;
    END;

    DROP TEMPORARY TABLE IF EXISTS tmp_calendar_days, tmp_calendar_raw, tmp_calendar_entries, tmp_calendar_grid;
    -- Keep a representable exclusive end and the preceding overnight day.
    IF p_start_month IS NULL OR p_start_month NOT BETWEEN 1 AND 12
       OR p_start_year IS NULL OR p_start_year NOT BETWEEN 1001 AND 9999
       OR p_month_count IS NULL OR p_month_count NOT BETWEEN 1 AND 12 THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Calendar requires month 1-12, year 1001-9999, and count 1-12';
    END IF;
    -- Check the exclusive-end month arithmetically before DATE_ADD can overflow.
    IF p_start_year * 12 + p_start_month - 1 + p_month_count >= 10000 * 12 THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Calendar requires an exclusive end before year 10000';
    END IF;
    -- 1 = members' calendar (birthdays, anniversaries, all programs); 0 = public calendar.
    IF p_include_private IS NULL OR p_include_private NOT IN (0, 1) THEN
       SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Calendar requires p_include_private of 0 or 1';
    END IF;
    SET v_start = STR_TO_DATE(CONCAT(p_start_year, '-', LPAD(p_start_month, 2, '0'), '-01'), '%Y-%m-%d');
    SET v_end = DATE_ADD(v_start, INTERVAL p_month_count MONTH);
    CREATE TEMPORARY TABLE tmp_calendar_days (
        calendar_date DATE PRIMARY KEY, entries LONGTEXT NOT NULL
    ) DEFAULT CHARSET=utf8mb4;
    CREATE TEMPORARY TABLE tmp_calendar_raw (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title LONGTEXT NOT NULL, start_datetime DATETIME NOT NULL,
        end_datetime DATETIME NULL, entry_type VARCHAR(20) NOT NULL
    ) DEFAULT CHARSET=utf8mb4;
    CREATE TEMPORARY TABLE tmp_calendar_entries (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        calendar_date DATE NOT NULL, title LONGTEXT NOT NULL,
        start_datetime DATETIME NULL, end_datetime DATETIME NULL,
        entry_type VARCHAR(20) NOT NULL,
        KEY calendar_entry_date (calendar_date)
    ) DEFAULT CHARSET=utf8mb4;

    INSERT INTO tmp_calendar_raw (title, start_datetime, end_datetime, entry_type)
    SELECT COALESCE(NULLIF(CONCAT_WS(' - ', NULLIF(TRIM(p.prg_evnt_name), ''), NULLIF(TRIM(s.activity_scheduled), '')), ''), 'Program'),
           s.start_datetime, s.end_datetime, 'Program'
    FROM prg_evnt_schedules s
    JOIN programs_events p ON p.prg_evnt_id = s.prg_evnt_id
    WHERE (p_include_private = 1 OR p.is_public = 1)
      AND s.start_datetime >= '1000-01-01 00:00:00' AND s.start_datetime < v_end
      AND (s.end_datetime IS NULL OR s.end_datetime >= s.start_datetime)
      AND (s.start_datetime >= v_start OR s.end_datetime > v_start);

    SET v_date = DATE_SUB(v_start, INTERVAL 1 DAY);
    WHILE v_date < v_end DO
        INSERT INTO tmp_calendar_raw (title, start_datetime, end_datetime, entry_type)
        SELECT s.name, TIMESTAMP(v_date, s.start_time),
               CASE WHEN s.end_time IS NULL THEN NULL
                    WHEN s.end_time < s.start_time
                        THEN TIMESTAMP(DATE_ADD(v_date, INTERVAL 1 DAY), s.end_time)
                    ELSE TIMESTAMP(v_date, s.end_time) END,
               'Service'
        FROM calendar_weekly_services s
        LEFT JOIN calendar_service_exceptions e
          ON e.service_id = s.id AND e.occurrence_date = v_date
        WHERE s.active = 1 AND s.weekday = DAYOFWEEK(v_date) - 1
          AND v_date >= s.effective_start
          AND (s.effective_end IS NULL OR v_date <= s.effective_end)
          AND e.id IS NULL;
        SET v_date = DATE_ADD(v_date, INTERVAL 1 DAY);
    END WHILE;

    INSERT INTO tmp_calendar_raw (title, start_datetime, end_datetime, entry_type)
    SELECT s.name, e.replacement_start, e.replacement_end, 'Service'
    FROM calendar_service_exceptions e
    JOIN calendar_weekly_services s ON s.id = e.service_id
    WHERE e.is_cancelled = 0 AND s.active = 1
      AND s.weekday = DAYOFWEEK(e.occurrence_date) - 1
      AND e.occurrence_date >= s.effective_start
      AND (s.effective_end IS NULL OR e.occurrence_date <= s.effective_end)
      AND e.replacement_start < v_end
      AND (e.replacement_start >= v_start OR e.replacement_end > v_start);

    SET v_date = v_start;
    WHILE v_date < v_end DO
        INSERT INTO tmp_calendar_days VALUES (v_date, '');
        INSERT INTO tmp_calendar_entries (calendar_date, title, start_datetime, end_datetime, entry_type)
        SELECT v_date, title, start_datetime, end_datetime, entry_type
        FROM tmp_calendar_raw
        WHERE start_datetime < DATE_ADD(v_date, INTERVAL 1 DAY)
          AND (end_datetime > v_date OR
               ((end_datetime IS NULL OR end_datetime = start_datetime) AND DATE(start_datetime) = v_date));

        INSERT INTO tmp_calendar_entries (calendar_date, title, entry_type)
        SELECT v_date, TRIM(CONCAT_WS(' ', NULLIF(TRIM(c.first_name), ''), NULLIF(TRIM(c.last_name), ''))), 'Birthday'
        FROM contacts c
        WHERE p_include_private = 1
          AND COALESCE(c.is_deceased, 0) = 0
          AND c.date_of_birth >= '1000-01-01'
          AND c.date_of_birth <= CURRENT_DATE() AND c.date_of_birth <= v_date
          AND MONTH(c.date_of_birth) BETWEEN 1 AND 12
          AND DAY(c.date_of_birth) BETWEEN 1 AND DAY(LAST_DAY(c.date_of_birth))
          AND MONTH(c.date_of_birth) = MONTH(v_date) AND DAY(c.date_of_birth) = DAY(v_date);

        INSERT INTO tmp_calendar_entries (calendar_date, title, entry_type)
        SELECT v_date,
               CONCAT(TRIM(CONCAT_WS(' ', NULLIF(TRIM(h.first_name), ''), NULLIF(TRIM(h.last_name), ''))),
                      CASE WHEN spouse.contact_id IS NULL THEN ''
                           ELSE CONCAT(' & ', TRIM(CONCAT_WS(' ', NULLIF(TRIM(spouse.first_name), ''), NULLIF(TRIM(spouse.last_name), '')))) END),
               'Anniversary'
        FROM contacts h
        LEFT JOIN contacts spouse ON spouse.family_id = h.family_id
          AND spouse.contact_id <> h.contact_id AND spouse.anniv_date = h.anniv_date
          AND spouse.is_head = 0 AND COALESCE(spouse.is_deceased, 0) = 0
        WHERE p_include_private = 1
          AND h.is_head = 1 AND COALESCE(h.is_deceased, 0) = 0
          AND h.anniv_date >= '1000-01-01'
          AND h.anniv_date <= CURRENT_DATE() AND h.anniv_date <= v_date
          AND MONTH(h.anniv_date) BETWEEN 1 AND 12
          AND DAY(h.anniv_date) BETWEEN 1 AND DAY(LAST_DAY(h.anniv_date))
          AND MONTH(h.anniv_date) = MONTH(v_date) AND DAY(h.anniv_date) = DAY(v_date)
          AND NOT EXISTS (
              SELECT 1 FROM contacts other_head
              WHERE other_head.family_id = h.family_id AND other_head.anniv_date = h.anniv_date
                AND other_head.is_head = 1 AND other_head.contact_id <> h.contact_id
                AND COALESCE(other_head.is_deceased, 0) = 0
          )
          AND (SELECT COUNT(*) FROM contacts candidate
               WHERE candidate.family_id = h.family_id AND candidate.anniv_date = h.anniv_date
                 AND candidate.is_head = 0 AND candidate.contact_id <> h.contact_id
                 AND COALESCE(candidate.is_deceased, 0) = 0) <= 1;
        SET v_date = DATE_ADD(v_date, INTERVAL 1 DAY);
    END WHILE;

    -- Cursor concatenation uses LONGTEXT, not GROUP_CONCAT or session limits.
    BEGIN
        DECLARE v_done BOOLEAN DEFAULT FALSE;
        DECLARE v_entry_date DATE;
        DECLARE v_title LONGTEXT;
        DECLARE v_type VARCHAR(20);
        DECLARE v_entry_start DATETIME;
        DECLARE v_entry_end DATETIME;
        DECLARE v_line LONGTEXT;
        DECLARE entry_cursor CURSOR FOR
            SELECT calendar_date, title, entry_type, start_datetime, end_datetime
            FROM tmp_calendar_entries
            ORDER BY calendar_date, start_datetime IS NULL, start_datetime, entry_type, title, id;
        DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;
        OPEN entry_cursor;
        entry_loop: LOOP
            FETCH entry_cursor INTO v_entry_date, v_title, v_type, v_entry_start, v_entry_end;
            IF v_done THEN LEAVE entry_loop; END IF;
            SET v_line = CONCAT(v_type, ': ', v_title,
                CASE WHEN v_entry_start IS NULL THEN ''
                     ELSE CONCAT(' (', DATE_FORMAT(v_entry_start, '%H:%i'),
                         CASE WHEN v_entry_end IS NULL OR v_entry_end = v_entry_start THEN ''
                              ELSE CONCAT('-', DATE_FORMAT(v_entry_end, '%H:%i'),
                                  CASE WHEN DATE(v_entry_end) <> DATE(v_entry_start)
                                       THEN CONCAT(' ', DATE_FORMAT(v_entry_end, '%b %e')) ELSE '' END) END,
                         CASE WHEN DATE(v_entry_start) <> v_entry_date
                              THEN CONCAT('; from ', DATE_FORMAT(v_entry_start, '%b %e')) ELSE '' END,
                         ')') END);
            UPDATE tmp_calendar_days
            SET entries = CONCAT(entries, CASE WHEN entries = '' THEN '' ELSE CHAR(10) END, v_line)
            WHERE calendar_date = v_entry_date;
        END LOOP;
        CLOSE entry_cursor;
    END;
END%%
DELIMITER ;

DELIMITER %%
DROP PROCEDURE IF EXISTS `calendar_month_grid`%%
CREATE PROCEDURE `calendar_month_grid`(IN p_start_month INT, IN p_start_year INT, IN p_month_count INT, IN p_include_private TINYINT)
BEGIN
    DECLARE v_month DATE;
    DECLARE v_date DATE;
    DECLARE v_month_index INT DEFAULT 0;
    DECLARE v_cell INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        DROP TEMPORARY TABLE IF EXISTS tmp_calendar_days, tmp_calendar_raw, tmp_calendar_entries, tmp_calendar_grid;
        RESIGNAL;
    END;
    CALL calendar_build_range(p_start_month, p_start_year, p_month_count, p_include_private);
    SET v_month = STR_TO_DATE(CONCAT(p_start_year, '-', LPAD(p_start_month, 2, '0'), '-01'), '%Y-%m-%d');
    CREATE TEMPORARY TABLE tmp_calendar_grid (
        month_start DATE, month_label VARCHAR(40), week_index INT,
        weekday INT, calendar_date DATE NULL
    );
    WHILE v_month_index < p_month_count DO
        SET v_cell = 0;
        WHILE v_cell < 42 DO
            SET v_date = DATE_ADD(DATE_SUB(v_month, INTERVAL (DAYOFWEEK(v_month) - 1) DAY), INTERVAL v_cell DAY);
            INSERT INTO tmp_calendar_grid VALUES (
                v_month, DATE_FORMAT(v_month, '%M %Y'), FLOOR(v_cell / 7) + 1, MOD(v_cell, 7),
                CASE WHEN v_date BETWEEN v_month AND LAST_DAY(v_month) THEN v_date ELSE NULL END
            );
            SET v_cell = v_cell + 1;
        END WHILE;
        SET v_month = DATE_ADD(v_month, INTERVAL 1 MONTH);
        SET v_month_index = v_month_index + 1;
    END WHILE;
    SELECT g.month_start, g.month_label, g.week_index,
        MAX(CASE WHEN g.weekday = 0 THEN COALESCE(CAST(DAY(g.calendar_date) AS CHAR), '') END) AS sun_day,
        MAX(CASE WHEN g.weekday = 0 THEN COALESCE(d.entries, '') END) AS sun_entries,
        MAX(CASE WHEN g.weekday = 1 THEN COALESCE(CAST(DAY(g.calendar_date) AS CHAR), '') END) AS mon_day,
        MAX(CASE WHEN g.weekday = 1 THEN COALESCE(d.entries, '') END) AS mon_entries,
        MAX(CASE WHEN g.weekday = 2 THEN COALESCE(CAST(DAY(g.calendar_date) AS CHAR), '') END) AS tue_day,
        MAX(CASE WHEN g.weekday = 2 THEN COALESCE(d.entries, '') END) AS tue_entries,
        MAX(CASE WHEN g.weekday = 3 THEN COALESCE(CAST(DAY(g.calendar_date) AS CHAR), '') END) AS wed_day,
        MAX(CASE WHEN g.weekday = 3 THEN COALESCE(d.entries, '') END) AS wed_entries,
        MAX(CASE WHEN g.weekday = 4 THEN COALESCE(CAST(DAY(g.calendar_date) AS CHAR), '') END) AS thu_day,
        MAX(CASE WHEN g.weekday = 4 THEN COALESCE(d.entries, '') END) AS thu_entries,
        MAX(CASE WHEN g.weekday = 5 THEN COALESCE(CAST(DAY(g.calendar_date) AS CHAR), '') END) AS fri_day,
        MAX(CASE WHEN g.weekday = 5 THEN COALESCE(d.entries, '') END) AS fri_entries,
        MAX(CASE WHEN g.weekday = 6 THEN COALESCE(CAST(DAY(g.calendar_date) AS CHAR), '') END) AS sat_day,
        MAX(CASE WHEN g.weekday = 6 THEN COALESCE(d.entries, '') END) AS sat_entries
    FROM tmp_calendar_grid g
    LEFT JOIN tmp_calendar_days d ON d.calendar_date = g.calendar_date
    GROUP BY g.month_start, g.month_label, g.week_index
    ORDER BY g.month_start, g.week_index;
    DROP TEMPORARY TABLE tmp_calendar_days, tmp_calendar_raw, tmp_calendar_entries, tmp_calendar_grid;
END%%
DELIMITER ;

DELIMITER %%
DROP PROCEDURE IF EXISTS `getMonthCalendar`%%
-- Members' calendar: includes birthdays, anniversaries, and all programs/events.
CREATE PROCEDURE `getMonthCalendar`(IN p_start_month INT, IN p_start_year INT, IN p_month_count INT)
BEGIN
    CALL calendar_month_grid(p_start_month, p_start_year, p_month_count, 1);
END%%
DELIMITER ;


DELIMITER %%
DROP PROCEDURE IF EXISTS `getPublicMonthCalendar`%%
-- Public calendar: weekly services and programs/events marked is_public; no birthdays or anniversaries.
CREATE PROCEDURE `getPublicMonthCalendar`(IN p_start_month INT, IN p_start_year INT, IN p_month_count INT)
BEGIN
    CALL calendar_month_grid(p_start_month, p_start_year, p_month_count, 0);
END%%
DELIMITER ;


DELIMITER %%
DROP PROCEDURE IF EXISTS `getCalendarRangeEntries`%%
CREATE PROCEDURE `getCalendarRangeEntries`(IN p_start_month INT, IN p_start_year INT, IN p_month_count INT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        DROP TEMPORARY TABLE IF EXISTS tmp_calendar_days, tmp_calendar_raw, tmp_calendar_entries, tmp_calendar_grid;
        RESIGNAL;
    END;
    CALL calendar_build_range(p_start_month, p_start_year, p_month_count, 1);
    -- Every real date is present; empty dates have NULL entry fields.
    SELECT d.calendar_date, e.title, e.start_datetime, e.end_datetime, e.entry_type
    FROM tmp_calendar_days d
    LEFT JOIN tmp_calendar_entries e ON e.calendar_date = d.calendar_date
    ORDER BY d.calendar_date, e.start_datetime IS NULL, e.start_datetime, e.entry_type, e.title, e.id;
    DROP TEMPORARY TABLE tmp_calendar_days, tmp_calendar_raw, tmp_calendar_entries;
END%%
DELIMITER ;

DELIMITER %%
DROP PROCEDURE IF EXISTS `calendar_build_entries`%%
CREATE PROCEDURE `calendar_build_entries`(IN p_start_month INT, IN p_start_year INT)
BEGIN
    CALL calendar_build_range(p_start_month, p_start_year, 3, 1);
END%%
DELIMITER ;

