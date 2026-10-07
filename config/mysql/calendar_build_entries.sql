DELIMITER %%
DROP PROCEDURE IF EXISTS `nbbtm_central`.`calendar_build_entries`%%
CREATE DEFINER=`admincentral`@`%` PROCEDURE `nbbtm_central`.`calendar_build_entries`(IN p_start_month INT, IN p_start_year INT)
BEGIN
    CALL calendar_build_range(p_start_month, p_start_year, 3, 1);
END%%
DELIMITER ;