DELIMITER &&
CREATE PROCEDURE `getEventSupport`( IN p_event_id int)
BEGIN
SELECT pes.event_min_support_id,
    pes.prg_evnt_id,
    pes.min_comm_id,
    mc.min_comm_name
FROM prg_evnt_min_support pes
 JOIN ministry_committee mc on mc.min_comm_id=pes.min_comm_id
WHERE pes.prg_evnt_id=11004;
END &&
DELIMITER ;