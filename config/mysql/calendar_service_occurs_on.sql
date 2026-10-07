DELIMITER %%
DROP FUNCTION IF EXISTS `nbbtm_central`.`calendar_service_occurs_on`%%
-- Returns 1 when a recurring service pattern falls on p_date (effective dates are checked by callers).
--   weekly          : every p_weekday (Sunday=0 .. Saturday=6)
--   monthly_weekday : the p_week_of_month-th p_weekday of the month (1-5), or the last one (-1)
--   monthly_date    : day p_day_of_month of the month, or the last day in shorter months
CREATE DEFINER=`admincentral`@`%` FUNCTION `nbbtm_central`.`calendar_service_occurs_on`(
    p_recurrence VARCHAR(20), p_weekday TINYINT, p_week_of_month TINYINT, p_day_of_month TINYINT, p_date DATE
) RETURNS TINYINT
    DETERMINISTIC
    NO SQL
BEGIN
    RETURN COALESCE(CASE p_recurrence
        WHEN 'weekly' THEN DAYOFWEEK(p_date) - 1 = p_weekday
        WHEN 'monthly_weekday' THEN DAYOFWEEK(p_date) - 1 = p_weekday
            AND CASE WHEN p_week_of_month = -1 THEN DAY(p_date) + 7 > DAY(LAST_DAY(p_date))
                     ELSE FLOOR((DAY(p_date) - 1) / 7) + 1 = p_week_of_month END
        WHEN 'monthly_date' THEN DAY(p_date) = LEAST(p_day_of_month, DAY(LAST_DAY(p_date)))
        ELSE 0 END, 0);
END%%
DELIMITER ;
