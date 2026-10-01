-- The event program summary report and its subreports load their style
-- template from $P{path} + "central_admin_style1.jrtx". generate_report.php
-- fills the "path" and "SUBREPORT_DIR" parameters from REPORTS_STYLE_PATH and
-- REPORTS_SUBREPORT_PATH in .env, but only for parameters registered here.
INSERT INTO app_report_parameters (report_id, param_name, param_type, is_required)
SELECT reports.id, 'path', 'string', 0
FROM app_reports AS reports
WHERE reports.report_key = 'event_prog_confer'
  AND NOT EXISTS (
      SELECT 1
      FROM app_report_parameters AS parameters
      WHERE parameters.report_id = reports.id
        AND parameters.param_name = 'path'
  );
