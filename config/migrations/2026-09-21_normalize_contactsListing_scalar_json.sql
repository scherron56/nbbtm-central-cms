-- The "New Beginnings Contact Lists" report (report_key = nbbtm_contacts)
-- renders contact_type_ids as a single-select dropdown, so generate_report.php
-- always passes a bare scalar (e.g. 1101), not a JSON array. JSON_TABLE with
-- '$[*]' only iterates JSON arrays; against a scalar it silently returns zero
-- rows, which produced a "blank" PDF (only the letterhead/header rendered,
-- no contact rows or totals). Recreate the procedure so it normalizes a
-- scalar id into a single-element JSON array before filtering.
USE nbbtm_central;

DROP PROCEDURE IF EXISTS contactsListing;

DELIMITER &&

CREATE PROCEDURE contactsListing(
    IN contact_type_ids JSON
)
BEGIN
    DECLARE normalized_ids JSON DEFAULT CASE
        WHEN contact_type_ids IS NULL THEN NULL
        WHEN JSON_TYPE(contact_type_ids) = 'ARRAY' THEN contact_type_ids
        ELSE JSON_ARRAY(contact_type_ids)
    END;

    SELECT
        ct.contact_desc,
        c.contact_id,
        COALESCE(t.titleabr, '') AS titleabr,
        COALESCE(c.last_name, '') AS last_name,
        COALESCE(c.first_name, '') AS first_name,
        COALESCE(c.n_sufix, '') AS n_sufix,
        c.gender,
        c.phone_1,
        c.c_email
    FROM contact_type ct
    JOIN contacts c ON c.contact_type_id = ct.contact_type_id
    LEFT JOIN title t ON t.title_id = c.title_id
    WHERE (
            normalized_ids IS NULL
            OR JSON_LENGTH(normalized_ids) = 0
            OR c.contact_type_id IN (
                SELECT jt_type.id
                FROM JSON_TABLE(
                    normalized_ids,
                    '$[*]' COLUMNS (id INT PATH '$')
                ) AS jt_type
            )
          );
END &&

DELIMITER ;
