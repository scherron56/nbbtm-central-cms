-- Adds optional presentation metadata for dynamically rendered Jasper parameters.
-- Existing records use their param_type when control_type is NULL.
ALTER TABLE app_report_parameters
  ADD COLUMN control_type VARCHAR(30) NULL DEFAULT NULL AFTER param_type,
  ADD COLUMN static_options TEXT NULL DEFAULT NULL AFTER control_type,
  ADD COLUMN placeholder VARCHAR(255) NULL DEFAULT NULL AFTER static_options;
