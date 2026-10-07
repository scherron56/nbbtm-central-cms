DELIMITER %%
CREATE DEFINER=`admincentral`@`%` PROCEDURE `nbbtm_central`.`getCalendarEntries`(IN p_start_month INT, IN p_start_year INT)
BEGIN
    CALL getCalendarRangeEntries(p_start_month, p_start_year, 3);
END%%
DELIMITER ;