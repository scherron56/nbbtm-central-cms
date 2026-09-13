DELIMITER //
CREATE PROCEDURE getGrpType(IN grp_type_id INT)
BEGIN
    IF grp_type_id = 0 THEN
        SELECT DISTINCT 
            mgt.min_grp_type_id, 
            mgt.min_grp_type_desc 
        FROM min_group_type mgt
        JOIN ministry_committee mc ON mgt.min_grp_type_id = mc.min_comm_type_id
        WHERE mgt.is_active = 1 AND mc.is_active = 1;
    ELSE
        SELECT DISTINCT 
            mgt.min_grp_type_id, 
            mgt.min_grp_type_desc 
        FROM min_group_type mgt
        JOIN ministry_committee mc ON mgt.min_grp_type_id = mc.min_comm_type_id
        WHERE mgt.min_grp_type_id = grp_type_id AND mc.is_active = 1;
    END IF;
END //

DELIMITER ;

      