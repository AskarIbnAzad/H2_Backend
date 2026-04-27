ALTER TABLE diseases
    ADD COLUMN short_description TEXT NULL AFTER parent_id,
    ADD COLUMN description LONGTEXT NULL AFTER short_description;

ALTER TABLE organs
    ADD COLUMN image VARCHAR(500) NULL AFTER parent_id,
    ADD COLUMN short_description TEXT NULL AFTER image,
    ADD COLUMN description LONGTEXT NULL AFTER short_description;
