USE nbbtm_central;

DELIMITER &&

CREATE PROCEDURE contactsListing(
    IN contact_type_ids JSON
)
BEGIN
    -- The report's "contact_type_ids" parameter is rendered as a single-select
    -- dropdown, so JasperReports always passes a bare scalar (e.g. 1101), not
    -- a JSON array. JSON_TABLE('$[*]') only iterates JSON arrays, so a scalar
    -- silently produces zero matches. Normalize scalars into a single-element
    -- array here so both a bare id and an actual JSON array (e.g. [1101,1103])
    -- work the same way.
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
            normalized_ids IS NULL                     -- If the JSON parameter is NULL
            OR JSON_LENGTH(normalized_ids) = 0         -- Or if it's an empty JSON array []
            OR c.contact_type_id IN (                    -- Or if the contact_type_id is in the provided list
                SELECT jt_type.id
                FROM JSON_TABLE(
                    normalized_ids,
                    '$[*]' COLUMNS (id INT PATH '$')
                ) AS jt_type
            )
          );
END &&

DELIMITER ;