-- The compiled nbbtm_contacts report and contactsListing procedure expect
-- contact_type_ids. Correct the previously stored misspelled parameter name.
UPDATE app_report_parameters AS parameters
INNER JOIN app_reports AS reports ON reports.id = parameters.report_id
SET parameters.param_name = 'contact_type_ids'
WHERE reports.report_key = 'nbbtm_contacts'
  AND parameters.param_name = 'contac_type_ids';
