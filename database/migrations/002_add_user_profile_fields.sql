USE xinphp_db;

ALTER TABLE users
    MODIFY COLUMN full_name VARCHAR(100) NULL,
    ADD COLUMN first_name VARCHAR(50) NULL AFTER id,
    ADD COLUMN middle_name VARCHAR(50) NULL AFTER first_name,
    ADD COLUMN last_name VARCHAR(50) NULL AFTER middle_name,
    ADD COLUMN age TINYINT UNSIGNED NULL AFTER last_name,
    ADD COLUMN birthdate DATE NULL AFTER age;
