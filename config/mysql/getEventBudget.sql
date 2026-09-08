DELIMITER $$
CREATE PROCEDURE getEventBudget( IN p_event_id int)
BEGIN
	SELECT bi.prg_evnt_id, bi.budget_item_id, bi.item_description, bi.item_type, bi.amount
	FROM prg_evnt_budget_items bi
	WHERE bi.prg_evnt_id=p_event_id;
END $$
DELIMITER ;