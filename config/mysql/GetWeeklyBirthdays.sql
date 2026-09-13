CREATE PROCEDURE `GetWeeklyBirthdays`(
    IN in_month INT,
    IN in_start_day INT
)
BEGIN
    SELECT 
        first_name,
        last_name,
        DATE_FORMAT(date_of_birth, '%M %e') AS birthday_month_day
    FROM contacts
    WHERE 
        date_of_birth IS NOT NULL 
        AND CAST(date_of_birth AS CHAR) NOT LIKE '0000%'
        AND (is_deceased = 0 OR is_deceased IS NULL)
        
        -- Matches the target month and the 7-day Sunday-to-Saturday span
        AND MONTH(date_of_birth) = in_month
        AND DAY(date_of_birth) BETWEEN in_start_day AND (in_start_day + 6)
        
    ORDER BY 
        DAY(date_of_birth) ASC;
END