DELIMITER %%
DROP PROCEDURE IF EXISTS `nbbtm_central`.`calendar_build_range`%%
CREATE DEFINER=`admincentral`@`%` PROCEDURE `nbbtm_central`.`calendar_build_range`(IN p_start_month INT, IN p_start_year INT, IN p_month_count INT, IN p_include_private TINYINT)
BEGIN
    DECLARE v_start DATE;
    DECLARE v_end DATE;
    DECLARE v_date DATE;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        DROP TEMPORARY TABLE IF EXISTS tmp_calendar_days, tmp_calendar_raw, tmp_calendar_entries, tmp_calendar_grid, tmp_calendar_candidates, tmp_calendar_merge;
        RESIGNAL;
    END;

    DROP TEMPORARY TABLE IF EXISTS tmp_calendar_days, tmp_calendar_raw, tmp_calendar_entries, tmp_calendar_grid, tmp_calendar_candidates, tmp_calendar_merge;
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
        end_datetime DATETIME NULL, entry_type VARCHAR(20) NOT NULL,
        is_anchor TINYINT NOT NULL DEFAULT 0
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
      AND s.calendar_weekdays IS NULL
      AND s.start_datetime >= '1000-01-01 00:00:00' AND s.start_datetime < v_end
      AND (s.end_datetime IS NULL OR s.end_datetime >= s.start_datetime)
      AND (s.start_datetime >= v_start OR s.end_datetime > v_start);

    -- Weekly services anchor their time slot. Monthly services and timed reminders
    -- that start at exactly the same date and time are shown in parentheses after
    -- the weekly service; otherwise they are listed as their own entries.
    CREATE TEMPORARY TABLE tmp_calendar_candidates (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title LONGTEXT NOT NULL, start_datetime DATETIME NOT NULL,
        end_datetime DATETIME NULL, entry_type VARCHAR(20) NOT NULL,
        is_anchor TINYINT NOT NULL DEFAULT 0
    ) DEFAULT CHARSET=utf8mb4;
    CREATE TEMPORARY TABLE tmp_calendar_merge (
        candidate_id BIGINT UNSIGNED PRIMARY KEY, anchor_id BIGINT UNSIGNED NOT NULL
    );

    SET v_date = DATE_SUB(v_start, INTERVAL 1 DAY);
    WHILE v_date < v_end DO
        INSERT INTO tmp_calendar_raw (title, start_datetime, end_datetime, entry_type)
        SELECT COALESCE(NULLIF(CONCAT_WS(' - ', NULLIF(TRIM(p.prg_evnt_name), ''), NULLIF(TRIM(s.activity_scheduled), '')), ''), 'Program'),
               TIMESTAMP(v_date, TIME(s.start_datetime)),
               CASE WHEN s.end_datetime IS NULL THEN NULL
                    WHEN TIME(s.end_datetime) < TIME(s.start_datetime)
                        THEN TIMESTAMP(DATE_ADD(v_date, INTERVAL 1 DAY), TIME(s.end_datetime))
                    ELSE TIMESTAMP(v_date, TIME(s.end_datetime)) END,
               'Program'
        FROM prg_evnt_schedules s
        JOIN programs_events p ON p.prg_evnt_id = s.prg_evnt_id
        LEFT JOIN calendar_program_exceptions e
          ON e.schedule_id = s.schedule_id AND e.occurrence_date = v_date
        WHERE (p_include_private = 1 OR p.is_public = 1)
          AND s.calendar_weekdays IS NOT NULL
          AND (s.calendar_weekdays & (1 << (DAYOFWEEK(v_date) - 1))) <> 0
          AND v_date BETWEEN DATE(s.start_datetime) AND DATE(COALESCE(s.end_datetime, s.start_datetime))
          AND (s.end_datetime IS NULL OR s.end_datetime >= s.start_datetime)
          AND e.id IS NULL;

        INSERT INTO tmp_calendar_candidates (title, start_datetime, end_datetime, entry_type, is_anchor)
        SELECT s.name, TIMESTAMP(v_date, s.start_time),
               CASE WHEN s.end_time IS NULL THEN NULL
                    WHEN s.end_time < s.start_time
                        THEN TIMESTAMP(DATE_ADD(v_date, INTERVAL 1 DAY), s.end_time)
                    ELSE TIMESTAMP(v_date, s.end_time) END,
               'Service', s.recurrence = 'weekly'
        FROM calendar_weekly_services s
        LEFT JOIN calendar_service_exceptions e
          ON e.service_id = s.id AND e.occurrence_date = v_date
        WHERE s.active = 1 AND (p_include_private = 1 OR s.is_public = 1)
          AND calendar_service_occurs_on(s.recurrence, s.weekday, s.week_of_month, s.day_of_month, v_date) = 1
          AND v_date >= s.effective_start
          AND (s.effective_end IS NULL OR v_date <= s.effective_end)
          AND e.id IS NULL;

        INSERT INTO tmp_calendar_candidates (title, start_datetime, end_datetime, entry_type, is_anchor)
        SELECT d.title, TIMESTAMP(v_date, d.start_time),
               CASE WHEN d.end_time IS NULL THEN NULL
                    WHEN d.end_time < d.start_time
                        THEN TIMESTAMP(DATE_ADD(v_date, INTERVAL 1 DAY), d.end_time)
                    ELSE TIMESTAMP(v_date, d.end_time) END,
               'Reminder', 0
        FROM calendar_special_dates d
        WHERE d.start_time IS NOT NULL AND (p_include_private = 1 OR d.is_public = 1)
          AND v_date BETWEEN d.start_date AND COALESCE(d.end_date, d.start_date);
        SET v_date = DATE_ADD(v_date, INTERVAL 1 DAY);
    END WHILE;

    INSERT INTO tmp_calendar_raw (title, start_datetime, end_datetime, entry_type)
    SELECT COALESCE(NULLIF(CONCAT_WS(' - ', NULLIF(TRIM(p.prg_evnt_name), ''), NULLIF(TRIM(s.activity_scheduled), '')), ''), 'Program'),
           e.replacement_start, e.replacement_end, 'Program'
    FROM calendar_program_exceptions e
    JOIN prg_evnt_schedules s ON s.schedule_id = e.schedule_id
    JOIN programs_events p ON p.prg_evnt_id = s.prg_evnt_id
    WHERE e.is_cancelled = 0 AND (p_include_private = 1 OR p.is_public = 1)
      AND s.calendar_weekdays IS NOT NULL
      AND (s.calendar_weekdays & (1 << (DAYOFWEEK(e.occurrence_date) - 1))) <> 0
      AND e.occurrence_date BETWEEN DATE(s.start_datetime) AND DATE(COALESCE(s.end_datetime, s.start_datetime))
      AND (s.end_datetime IS NULL OR s.end_datetime >= s.start_datetime)
      AND e.replacement_start < v_end
      AND (e.replacement_start >= v_start OR e.replacement_end > v_start);

    INSERT INTO tmp_calendar_candidates (title, start_datetime, end_datetime, entry_type, is_anchor)
    SELECT s.name, e.replacement_start, e.replacement_end, 'Service', s.recurrence = 'weekly'
    FROM calendar_service_exceptions e
    JOIN calendar_weekly_services s ON s.id = e.service_id
    WHERE e.is_cancelled = 0 AND s.active = 1 AND (p_include_private = 1 OR s.is_public = 1)
      AND calendar_service_occurs_on(s.recurrence, s.weekday, s.week_of_month, s.day_of_month, e.occurrence_date) = 1
      AND e.occurrence_date >= s.effective_start
      AND (s.effective_end IS NULL OR e.occurrence_date <= s.effective_end)
      AND e.replacement_start < v_end
      AND (e.replacement_start >= v_start OR e.replacement_end > v_start);

    INSERT INTO tmp_calendar_raw (title, start_datetime, end_datetime, entry_type, is_anchor)
    SELECT title, start_datetime, end_datetime, entry_type, 1
    FROM tmp_calendar_candidates WHERE is_anchor = 1 ORDER BY start_datetime, id;
    DELETE FROM tmp_calendar_candidates WHERE is_anchor = 1;

    INSERT INTO tmp_calendar_merge (candidate_id, anchor_id)
    SELECT c.id, MIN(r.id)
    FROM tmp_calendar_candidates c
    JOIN tmp_calendar_raw r ON r.is_anchor = 1 AND r.start_datetime = c.start_datetime
    GROUP BY c.id;

    UPDATE tmp_calendar_raw r
    JOIN (
        SELECT m.anchor_id,
               GROUP_CONCAT(c.title ORDER BY c.entry_type = 'Reminder', c.title, c.id SEPARATOR '; ') AS extra
        FROM tmp_calendar_merge m
        JOIN tmp_calendar_candidates c ON c.id = m.candidate_id
        GROUP BY m.anchor_id
    ) x ON x.anchor_id = r.id
    SET r.title = CONCAT(r.title, ' (', x.extra, ')');

    INSERT INTO tmp_calendar_raw (title, start_datetime, end_datetime, entry_type)
    SELECT c.title, c.start_datetime, c.end_datetime, c.entry_type
    FROM tmp_calendar_candidates c
    LEFT JOIN tmp_calendar_merge m ON m.candidate_id = c.id
    WHERE m.candidate_id IS NULL
    ORDER BY c.start_datetime, c.id;
    DROP TEMPORARY TABLE tmp_calendar_candidates, tmp_calendar_merge;

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
        SELECT v_date, d.title, 'Reminder'
        FROM calendar_special_dates d
        WHERE d.start_time IS NULL AND (p_include_private = 1 OR d.is_public = 1)
          AND v_date BETWEEN d.start_date AND COALESCE(d.end_date, d.start_date);

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
        DECLARE v_safe_title LONGTEXT;
        DECLARE v_color VARCHAR(7);
        DECLARE entry_cursor CURSOR FOR
            SELECT calendar_date, title, entry_type, start_datetime, end_datetime
            FROM tmp_calendar_entries
            ORDER BY calendar_date, start_datetime IS NULL, start_datetime, entry_type, title, id;
        DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = TRUE;
        OPEN entry_cursor;
        entry_loop: LOOP
            FETCH entry_cursor INTO v_entry_date, v_title, v_type, v_entry_start, v_entry_end;
            IF v_done THEN LEAVE entry_loop; END IF;
            -- Preserve the styled-text output used by the deployed calendar reports.
            SET v_safe_title = REPLACE(REPLACE(REPLACE(v_title, '&', '&amp;'), '<', '&lt;'), '>', '&gt;');
            SET v_color = CASE v_type
                WHEN 'Program' THEN '#2E7D32'
                WHEN 'Service' THEN '#6A1B9A'
                WHEN 'Reminder' THEN '#EF6C00'
                WHEN 'Birthday' THEN '#C2185B'
                WHEN 'Anniversary' THEN '#00838F'
                ELSE '#000000' END;
            SET v_line = CONCAT('<style forecolor="', v_color, '">', v_safe_title,
                CASE WHEN v_entry_start IS NULL THEN ''
                     ELSE CONCAT(' (', DATE_FORMAT(v_entry_start, '%l:%i %p'),
                         CASE WHEN v_entry_end IS NULL OR v_entry_end = v_entry_start THEN ''
                              ELSE CONCAT('-', DATE_FORMAT(v_entry_end, '%l:%i %p'),
                                  CASE WHEN DATE(v_entry_end) <> DATE(v_entry_start)
                                       THEN CONCAT(' ', DATE_FORMAT(v_entry_end, '%b %e')) ELSE '' END) END,
                         CASE WHEN DATE(v_entry_start) <> v_entry_date
                              THEN CONCAT('; from ', DATE_FORMAT(v_entry_start, '%b %e')) ELSE '' END,
                         ')') END, '</style>');
            UPDATE tmp_calendar_days
            SET entries = CONCAT(entries, CASE WHEN entries = '' THEN '' ELSE CHAR(10) END, v_line)
            WHERE calendar_date = v_entry_date;
        END LOOP;
        CLOSE entry_cursor;
    END;
END%%
DELIMITER ;