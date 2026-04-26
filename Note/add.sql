ALTER TABLE diseases ADD COLUMN short_description TEXT NULL AFTER parent_id;
ALTER TABLE diseases ADD COLUMN description TEXT NULL AFTER short_description;
