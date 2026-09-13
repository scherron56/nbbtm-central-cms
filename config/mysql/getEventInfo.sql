CREATE PROCEDURE getEventInfo( IN p_event_id int)
BEGIN

	SELECT DISTINCT pe.prg_evnt_id, pe.prg_evnt_name, pe.min_comm_id, pe.contact_id,     
		concat(coalesce(c.first_name, ''), ' ', coalesce(c.last_name, '') ) as  contact_name,
		pe.contact_phone, pe.contact_email, pe.location, pe.goal, pe.prg_evnt_purpose, pe.requires_registration, pe.requires_fee,
		pe.registration_fee, pe.audience_target, pe.attend_estimate, pe.notes, ps.schedule_id, DATE_FORMAT(ps.start_datetime, '%M %e, %Y') as start_date,
	 DATE_FORMAT(ps.start_datetime, '%h:%I %p') start_time, DATE_FORMAT(ps.end_datetime, '%M %e, %Y') end_date,
	 DATE_FORMAT(ps.end_datetime, '%h:%I %p') end_time, ms.min_comm_id, mc.min_comm_name     
	FROM programs_events pe   
	join contacts c on c.contact_id=pe.contact_id    
	join prg_evnt_min_support ms on ms.prg_evnt_id=pe.prg_evnt_id
	JOIN ministry_committee mc ON mc.min_comm_id = ms.min_comm_id
where pe.prg_evnt_id = p_event_id; 

END$$
DELIMITER ;


