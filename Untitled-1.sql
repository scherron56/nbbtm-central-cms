DELIMITER //

DROP PROCEDURE IF EXISTS `GetWeeklyBirthdays`//

CREATE PROCEDURE `GetWeeklyBirthdays`(
    IN in_week_start DATE
)
BEGIN
    -- Derive Sunday start date and Saturday end date
    DECLARE v_start DATE;
    DECLARE v_end DATE;
    
    SET v_start = in_week_start;
    SET v_end = DATE_ADD(in_week_start, INTERVAL 6 DAY);

    SELECT 
        first_name,
        last_name,
        DATE_FORMAT(date_of_birth, '%M %e') AS birthday_month_day,
        -- Calculate the exact occurrence date in the current year/week for proper sorting
        CASE 
            WHEN STR_TO_DATE(CONCAT(YEAR(v_start), '-', DATE_FORMAT(date_of_birth, '%m-%d')), '%Y-%m-%d') >= v_start 
            THEN STR_TO_DATE(CONCAT(YEAR(v_start), '-', DATE_FORMAT(date_of_birth, '%m-%d')), '%Y-%m-%d')
            ELSE STR_TO_DATE(CONCAT(YEAR(v_end), '-', DATE_FORMAT(date_of_birth, '%m-%d')), '%Y-%m-%d')
        END AS current_bday_date
    FROM contacts
    WHERE 
        date_of_birth IS NOT NULL 
        AND CAST(date_of_birth AS CHAR) NOT LIKE '0000%'
        AND (is_deceased = 0 OR is_deceased IS NULL)
        AND (
            -- Bday mapped to start date's year falls between Sunday and Saturday
            STR_TO_DATE(CONCAT(YEAR(v_start), '-', DATE_FORMAT(date_of_birth, '%m-%d')), '%Y-%m-%d') BETWEEN v_start AND v_end
            
            -- Handles year-end crossover (e.g., Dec 28 to Jan 3) mapped to end date's year
            OR STR_TO_DATE(CONCAT(YEAR(v_end), '-', DATE_FORMAT(date_of_birth, '%m-%d')), '%Y-%m-%d') BETWEEN v_start AND v_end
        )
    ORDER BY 
        current_bday_date ASC;
END//

DELIMITER ;