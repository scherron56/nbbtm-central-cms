DELIMITER %%
DROP PROCEDURE IF EXISTS `nbbtm_central`.`getMonthCalendar`%%
-- Members' calendar: includes birthdays, anniversaries, and all programs/events.
CREATE DEFINER=`admincentral`@`%` PROCEDURE `nbbtm_central`.`getMonthCalendar`(IN p_start_month INT, IN p_start_year INT, IN p_month_count INT)
BEGIN
    CALL calendar_month_grid(p_start_month, p_start_year, p_month_count, 1);
END%%
DELIMITER ;
