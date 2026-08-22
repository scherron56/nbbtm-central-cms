SELECT 
  first_name,
  last_name,
  DATE_FORMAT(date_of_birth, '%M %e') AS birthday_month_day
FROM contacts
WHERE 
  -- Ignore deceased members if applicable
  (is_deceased = 0 OR is_deceased IS NULL)
  
  -- Normalize birth dates to a fixed leap year (2000) to safely check month/day ranges
  AND STR_TO_DATE(CONCAT('2000-', DATE_FORMAT(date_of_birth, '%m-%d')), '%Y-%m-%d')
      BETWEEN 
        -- Start of the week (Sunday input date mapped to dummy year 2000)
        STR_TO_DATE(CONCAT('2000-', DATE_FORMAT('2026-08-23', '%m-%d')), '%Y-%m-%d')
        AND 
        -- End of the week (Saturday = Sunday input + 6 days mapped to dummy year 2000)
        STR_TO_DATE(CONCAT('2000-', DATE_FORMAT(DATE_ADD('2026-08-23', INTERVAL 6 DAY), '%m-%d')), '%Y-%m-%d')
ORDER BY 
  MONTH(date_of_birth) ASC, 
  DAY(date_of_birth) ASC;