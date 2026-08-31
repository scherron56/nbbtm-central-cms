DELIMITER $$
PROCEDURE `getContacts`( p_is_active tinyint, p_is_child tinyint, p_is_member tinyint)
SELECT c.contact_id, t.titleabr, COALESCE(c.last_name, '') last_name, COALESCE(c.sufix, '') sufix,  COALESCE(c.first_name, '') first_name, c.gender, c.phone_1, c.c_email
	FROM contacts c
     JOIN title t on t.title_id=c.title_id
	WHERE is_active=p_is_active 
		AND	is_child=p_is_child
		AND is_member=p_is_member
	ORDER BY last_name, first_name ASC$$
DELIMITER ;
