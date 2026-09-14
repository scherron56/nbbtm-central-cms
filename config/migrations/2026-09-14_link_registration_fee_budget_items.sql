-- Migration: 2026-09-14_link_registration_fee_budget_items.sql
-- Ensure Registration Fee budget items exist for fee-requiring events and link existing registration fee actuals.

-- 1. Insert 'Registration Fee' budget item for events that require a fee but don't have one
INSERT INTO prg_evnt_budget_items (prg_evnt_id, item_description, item_type, amount)
SELECT 
    e.prg_evnt_id, 
    'Registration Fee', 
    'Income', 
    IF(e.attend_estimate IS NOT NULL AND e.attend_estimate > 0 AND e.registration_fee > 0, e.attend_estimate * e.registration_fee, e.registration_fee)
FROM programs_events e
WHERE e.requires_fee = 1
  AND NOT EXISTS (
      SELECT 1 FROM prg_evnt_budget_items bi 
      WHERE bi.prg_evnt_id = e.prg_evnt_id 
        AND LOWER(TRIM(bi.item_description)) IN ('registration fee', 'registration fees')
  );

-- 2. Link unlinked registration fee actuals to their event's Registration Fee budget item
UPDATE prg_evnt_budget_actuals a
JOIN prg_evnt_budget_items b ON a.prg_evnt_id = b.prg_evnt_id AND LOWER(TRIM(b.item_description)) IN ('registration fee', 'registration fees')
SET a.budget_item_id = b.budget_item_id
WHERE (a.budget_item_id IS NULL OR a.budget_item_id = 0)
  AND a.entry_type = 'Income'
  AND LOWER(a.description) LIKE 'registration fee%';
