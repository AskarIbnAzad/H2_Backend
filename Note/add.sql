ALTER TABLE diseases
    ADD COLUMN short_description TEXT NULL AFTER parent_id,
    ADD COLUMN description LONGTEXT NULL AFTER short_description;

ALTER TABLE organs
    ADD COLUMN image VARCHAR(500) NULL AFTER parent_id,
    ADD COLUMN short_description TEXT NULL AFTER image,
    ADD COLUMN description LONGTEXT NULL AFTER short_description;

CREATE TABLE `folders` (
   `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
   `user_id` BIGINT UNSIGNED NOT NULL,
   `name` VARCHAR(255) NOT NULL,
   `created_at` TIMESTAMP NULL DEFAULT NULL,
   `updated_at` TIMESTAMP NULL DEFAULT NULL,
   PRIMARY KEY (`id`),
   KEY `folders_user_id_foreign` (`user_id`),
   CONSTRAINT `folders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
);

ALTER TABLE `articles`
    ADD COLUMN `folder_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `id`,
    ADD INDEX `articles_folder_id_foreign` (`folder_id`),
    ADD CONSTRAINT `articles_folder_id_foreign` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL;
