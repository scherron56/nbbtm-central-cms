DELIMITER $$
CREATE PROCEDURE getEventSched( IN p_event_id int)
BEGIN
	SELECT ps.prg_evnt_id, ps.schedule_id, ps.activity_scheduled, DATE_FORMAT(ps.start_datetime, '%M %e, %Y') as start_date,
		 DATE_FORMAT(ps.start_datetime, '%h:%I %p') start_time, DATE_FORMAT(ps.end_datetime, '%M %e, %Y') end_date,
		 DATE_FORMAT(ps.end_datetime, '%h:%I %p') end_time
	FROM prg_evnt_schedules ps
	where ps.prg_evnt_id = p_event_id;
END$$
DELIMITER ;