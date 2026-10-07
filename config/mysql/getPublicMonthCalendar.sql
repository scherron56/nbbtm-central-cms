DELIMITER %%
DROP PROCEDURE IF EXISTS `nbbtm_central`.`getPublicMonthCalendar`%%
-- Public calendar: weekly services and programs/events marked is_public; no birthdays or anniversaries.
CREATE DEFINER=`admincentral`@`%` PROCEDURE `nbbtm_central`.`getPublicMonthCalendar`(IN p_start_month INT, IN p_start_year INT, IN p_month_count INT)
BEGIN
    CALL calendar_month_grid(p_start_month, p_start_year, p_month_count, 0);
END%%
DELIMITER ;
