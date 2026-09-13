DELIMITER //

CREATE PROCEDURE GenerateCommitteeReport(
    IN p_grp_type_id INT,
    IN p_grp_ids JSON
)
BEGIN
    SELECT 
        mc.min_comm_id,
        mc.min_comm_name AS CommitteeName,
        CONCAT(c.first_name, ' ', c.last_name, ' ' ,coalesce(c.sufix, '' )) AS MemberName,
        c.phone_1,
        c.c_email
    FROM ministry_committee mc
    JOIN member_alliance ma ON mc.min_comm_id = ma.min_comm_id
    JOIN contacts c ON ma.contact_id = c.contact_id
    LEFT JOIN JSON_TABLE(
        COALESCE(p_grp_ids, '[]'),
        '$[*]' COLUMNS (id INT PATH '$')
    ) jt ON mc.min_comm_id = jt.id
    WHERE mc.min_comm_type_id = p_grp_type_id 
      AND mc.is_active = 1 
      AND ma.is_active = 1
      AND (
          p_grp_ids IS NULL 
          OR JSON_LENGTH(p_grp_ids) = 0 
          OR jt.id IS NOT NULL
      )
    ORDER BY mc.min_comm_name, c.last_name, c.first_name;
END //

DELIMITER ;printContacts