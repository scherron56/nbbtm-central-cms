DELIMITER //

DROP PROCEDURE IF EXISTS `GetWeeklyCelebrations`//

CREATE PROCEDURE `GetWeeklyCelebrations`(
    IN in_week_start DATE
)
BEGIN
    DECLARE v_week_end DATE;

    SET v_week_end = DATE_ADD(in_week_start, INTERVAL 6 DAY);

    SELECT title, celebration_type, display_date
    FROM (
        SELECT
            CONCAT(birthday_contacts.first_name, ' ', birthday_contacts.last_name) AS title,
            'Birthday' AS celebration_type,
            DATE_FORMAT(birthday_date, '%b %e') AS display_date,
            birthday_date AS occurrence_date
        FROM (
            SELECT
                c.first_name,
                c.last_name,
                CASE
                    WHEN STR_TO_DATE(CONCAT(YEAR(in_week_start), '-', DATE_FORMAT(c.date_of_birth, '%m-%d')), '%Y-%m-%d')
                         BETWEEN in_week_start AND v_week_end
                    THEN STR_TO_DATE(CONCAT(YEAR(in_week_start), '-', DATE_FORMAT(c.date_of_birth, '%m-%d')), '%Y-%m-%d')
                    ELSE STR_TO_DATE(CONCAT(YEAR(v_week_end), '-', DATE_FORMAT(c.date_of_birth, '%m-%d')), '%Y-%m-%d')
                END AS birthday_date
            FROM contacts c
            WHERE c.date_of_birth IS NOT NULL
              AND CAST(c.date_of_birth AS CHAR) NOT LIKE '0000%'
              AND (c.is_deceased = 0 OR c.is_deceased IS NULL)
        ) AS birthday_contacts
        WHERE birthday_date BETWEEN in_week_start AND v_week_end

        UNION ALL

        SELECT
            CASE
                WHEN spouse.contact_id IS NOT NULL
                THEN CONCAT(head.first_name, ' & ', spouse.first_name, ' ', head.last_name)
                ELSE CONCAT(head.first_name, ' ', head.last_name)
            END AS title,
            'Anniversary' AS celebration_type,
            DATE_FORMAT(anniversary_date, '%b %e') AS display_date,
            anniversary_date AS occurrence_date
        FROM (
            SELECT
                c.contact_id,
                c.family_id,
                c.first_name,
                c.last_name,
                c.anniv_date,
                CASE
                    WHEN STR_TO_DATE(CONCAT(YEAR(in_week_start), '-', DATE_FORMAT(c.anniv_date, '%m-%d')), '%Y-%m-%d')
                         BETWEEN in_week_start AND v_week_end
                    THEN STR_TO_DATE(CONCAT(YEAR(in_week_start), '-', DATE_FORMAT(c.anniv_date, '%m-%d')), '%Y-%m-%d')
                    ELSE STR_TO_DATE(CONCAT(YEAR(v_week_end), '-', DATE_FORMAT(c.anniv_date, '%m-%d')), '%Y-%m-%d')
                END AS anniversary_date
            FROM contacts c
            WHERE c.anniv_date IS NOT NULL
              AND CAST(c.anniv_date AS CHAR) NOT LIKE '0000%'
              AND c.is_head = 1
              AND (c.is_deceased = 0 OR c.is_deceased IS NULL)
        ) AS head
        LEFT JOIN contacts spouse
            ON spouse.family_id = head.family_id
           AND spouse.contact_id <> head.contact_id
           AND spouse.anniv_date = head.anniv_date
           AND spouse.is_head = 0
           AND (spouse.is_deceased = 0 OR spouse.is_deceased IS NULL)
        WHERE anniversary_date BETWEEN in_week_start AND v_week_end
    ) AS celebrations
    ORDER BY occurrence_date ASC, celebration_type ASC, title ASC;
END//

DELIMITER ;