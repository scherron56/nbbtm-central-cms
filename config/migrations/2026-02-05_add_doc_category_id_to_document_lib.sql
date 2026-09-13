-- Migration: add doc_category_id to document_lib
-- Lets event (and other) attachments link to a row in doc_categories
-- instead of overloading document_short_name with the category id.
--
-- Run once. Safe to re-run if wrapped in an existence check by your deploy tooling.

ALTER TABLE document_lib
  ADD COLUMN doc_category_id INT UNSIGNED NULL DEFAULT NULL AFTER entity_id,
  ADD KEY idx_doc_category (doc_category_id);

-- One-time data migration: event docs that previously stored the category id
-- in document_short_name (string). Move it into the real column.
UPDATE document_lib d
JOIN doc_categories c
  ON d.document_short_name = CAST(c.doc_category_id AS CHAR)
SET d.doc_category_id = c.doc_category_id
WHERE d.entity_type = 'event'
  AND d.document_short_name REGEXP '^[0-9]+$';
