-- Migration: add control_type and static_options to app_report_parameters
-- Lets report parameters be rendered as something richer than a plain
-- text/number/date input (checkbox, single_select, multi_select) without
-- changing param_type, which is still used for casting the submitted value
-- before it's handed to the Jasper report.
--
-- control_type defaults to 'auto' so every existing row keeps rendering
-- exactly as it does today (admin_reports.js falls back to inferring the
-- widget from param_type when control_type is 'auto'/empty).
--
-- Run once. Safe to re-run if wrapped in an existence check by your deploy tooling.

ALTER TABLE app_report_parameters
  ADD COLUMN control_type VARCHAR(20) NOT NULL DEFAULT 'auto' AFTER param_type,
  ADD COLUMN static_options TEXT NULL AFTER control_type;
