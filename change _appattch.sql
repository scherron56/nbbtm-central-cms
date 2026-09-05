To transform nbbtm_central.app_attachments so it matches the older/backup structure in nbbtm_central_bk, run the following SQL query:

SQL
USE `nbbtm_central`;

ALTER TABLE `app_attachments`
  -- 1. Rename primary key back to document_id
  CHANGE COLUMN `attachment_id` `document_id` INT NOT NULL AUTO_INCREMENT,
  
  -- 2. Add document_short_name column
  ADD COLUMN `document_short_name` VARCHAR(50) DEFAULT NULL AFTER `document_name`,
  
  -- 3. Drop columns not present in the backup schema
  DROP COLUMN `entity_id`,
  DROP COLUMN `doc_category_id`,
  DROP COLUMN `document_sub_type`,
  
  -- 4. Revert index structure back to single-column lookup
  DROP INDEX `idx_entity`,
  DROP INDEX `idx_category`,
  ADD KEY `idx_entity` (`entity_type`);
What this migration does:
Renames attachment_id to document_id while keeping the AUTO_INCREMENT definition.

Adds document_short_name after document_name.

Removes entity_id, doc_category_id, and document_sub_type.

Drops idx_category and shrinks idx_entity back down to index only entity_type.