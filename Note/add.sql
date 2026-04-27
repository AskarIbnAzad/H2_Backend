ALTER TABLE diseases
    ADD COLUMN short_description TEXT NULL AFTER parent_id,
    ADD COLUMN description LONGTEXT NULL AFTER short_description;
