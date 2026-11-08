-- Run once for databases created before XinPHP collected separate profile fields.
-- This migration intentionally retains full_name so existing account data is not deleted.
-- New columns are nullable for existing accounts; backfill them before making them
-- NOT NULL in a future migration. New registrations validate and supply every field.
USE xinphp_db;

ALTER TABLE users
    MODIFY COLUMN full_name VARCHAR(100) NULL,
    ADD COLUMN first_name VARCHAR(50) NULL AFTER id,
    ADD COLUMN middle_name VARCHAR(50) NULL AFTER first_name,
    ADD COLUMN last_name VARCHAR(50) NULL AFTER middle_name,
    ADD COLUMN age TINYINT UNSIGNED NULL AFTER last_name,
    ADD COLUMN birthdate DATE NULL AFTER age;
