DELIMITER &&

CREATE DEFINER=`centadmin`@`localhost` PROCEDURE `nbbtm_central`.`CommitteeReport`(
    IN p_grp_ids JSON
)
BEGIN
    SELECT
        mgt.min_grp_type_desc,
        mc.min_comm_id,
        mc.min_comm_type_id,
        mc.min_comm_name AS CommitteeName,
        CONCAT(c.first_name, ' ', c.last_name, ' ' ,coalesce(c.n_sufix, '' )) AS MemberName,
        c.phone_1,
        c.c_email
    FROM ministry_committee mc
    JOIN member_alliance ma ON mc.min_comm_id = ma.min_comm_id
    JOIN contacts c ON ma.contact_id = c.contact_id
    JOIN min_group_type mgt ON mc.min_comm_type_id = mgt.min_grp_type_id
    WHERE mc.is_active = 1
      AND ma.is_active = 1
      AND (
          p_grp_ids IS NULL
          OR JSON_LENGTH(p_grp_ids) = 0
          OR mc.min_comm_id IN (
              SELECT jt_comm.id
              FROM JSON_TABLE(
                  p_grp_ids,
                  '$[*]' COLUMNS (id INT PATH '$')
              ) AS jt_comm
          )
      )
    ORDER BY mgt.min_grp_type_desc, mc.min_comm_name, c.last_name, c.first_name;
END
DELIMITER //