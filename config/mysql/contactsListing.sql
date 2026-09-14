USE nbbtm_central;

DELIMITER &&

CREATE PROCEDURE contactsListing(
    IN contact_type_ids JSON
)
BEGIN
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
            contact_type_ids IS NULL                     -- If the JSON parameter is NULL
            OR JSON_LENGTH(contact_type_ids) = 0         -- Or if it's an empty JSON array []
            OR c.contact_type_id IN (                    -- Or if the contact_type_id is in the provided list
                SELECT jt_type.id
                FROM JSON_TABLE(
                    contact_type_ids,
                    '$[*]' COLUMNS (id INT PATH '$')
                ) AS jt_type
            )
          );
END &&

DELIMITER ;