ALTER TABLE document_lib
    MODIFY entity_type ENUM('contact', 'ministry', 'event', 'admin', 'member') NOT NULL;

ALTER TABLE nbbtm_users
    MODIFY role ENUM('admin', 'staff', 'view', 'browser', 'member', 'developer') NOT NULL DEFAULT 'view';

ALTER TABLE nbbtm_users
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash;

INSERT INTO nbbtm_roles (nbbtm_role_name)
SELECT 'member'
WHERE NOT EXISTS (
    SELECT 1 FROM nbbtm_roles WHERE LOWER(nbbtm_role_name) = 'member'
);

INSERT INTO nbbtm_roles (nbbtm_role_name)
SELECT 'developer'
WHERE NOT EXISTS (
    SELECT 1 FROM nbbtm_roles WHERE LOWER(nbbtm_role_name) = 'developer'
);
