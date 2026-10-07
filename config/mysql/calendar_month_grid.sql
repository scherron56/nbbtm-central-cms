DELIMITER %%
DROP PROCEDURE IF EXISTS `nbbtm_central`.`calendar_month_grid`%%
CREATE DEFINER=`admincentral`@`%` PROCEDURE `nbbtm_central`.`calendar_month_grid`(IN p_start_month INT, IN p_start_year INT, IN p_month_count INT, IN p_include_private TINYINT)
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