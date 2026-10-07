DELIMITER %%
DROP PROCEDURE IF EXISTS `nbbtm_central`.`getCalendarRangeEntries`%%
CREATE DEFINER=`admincentral`@`%` PROCEDURE `nbbtm_central`.`getCalendarRangeEntries`(IN p_start_month INT, IN p_start_year INT, IN p_month_count INT)
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