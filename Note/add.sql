-- CREATE TABLE saved_searches (
--     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
--     user_id BIGINT UNSIGNED,
--     name TEXT NOT NULL,
--     search_data LONGTEXT NOT NULL,
--     created_at TIMESTAMP NULL DEFAULT NULL,
--     updated_at TIMESTAMP NULL DEFAULT NULL,
--     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
-- );


-- ALTER TABLE diseases
--     ADD COLUMN short_description TEXT NULL AFTER parent_id,
--     ADD COLUMN description LONGTEXT NULL AFTER short_description;
--
-- ALTER TABLE organs
--     ADD COLUMN image VARCHAR(500) NULL AFTER parent_id,
--     ADD COLUMN short_description TEXT NULL AFTER image,
--     ADD COLUMN description LONGTEXT NULL AFTER short_description;
--
-- CREATE TABLE `folders` (
--    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
--    `user_id` BIGINT UNSIGNED NOT NULL,
--    `name` VARCHAR(255) NOT NULL,
--    `created_at` TIMESTAMP NULL DEFAULT NULL,
--    `updated_at` TIMESTAMP NULL DEFAULT NULL,
--    PRIMARY KEY (`id`),
--    KEY `folders_user_id_foreign` (`user_id`),
--    CONSTRAINT `folders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
-- );
--
-- ALTER TABLE `articles`
--     ADD COLUMN `folder_id` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `id`,
--     ADD INDEX `articles_folder_id_foreign` (`folder_id`),
--     ADD CONSTRAINT `articles_folder_id_foreign` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL;


-- CREATE TABLE disease_organ (
--    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
--    disease_id BIGINT UNSIGNED NOT NULL,
--    organ_id BIGINT UNSIGNED NOT NULL,
--
--    UNIQUE KEY disease_organ_disease_id_organ_id_unique (disease_id, organ_id),
--
--    CONSTRAINT disease_organ_disease_id_foreign
--        FOREIGN KEY (disease_id)
--            REFERENCES diseases(id)
--            ON DELETE CASCADE,
--
--    CONSTRAINT disease_organ_organ_id_foreign
--        FOREIGN KEY (organ_id)
--            REFERENCES organs(id)
--            ON DELETE CASCADE
-- );

-- CREATE TABLE user_profiles (
--    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
--
--    user_id BIGINT UNSIGNED NOT NULL,
--
--    photo VARCHAR(255) NULL,
--    designation VARCHAR(255) NULL,
--    institution VARCHAR(255) NULL,
--    department VARCHAR(255) NULL,
--    country VARCHAR(255) NULL,
--
--    bio TEXT NULL,
--
--    research_interests JSON NULL,
--    skills JSON NULL,
--
--    personal_website_url VARCHAR(255) NULL,
--    orcid_id VARCHAR(255) NULL,
--
--    publications JSON NULL,
--
--    created_at TIMESTAMP NULL DEFAULT NULL,
--    updated_at TIMESTAMP NULL DEFAULT NULL,
--
--    INDEX user_profiles_user_id_index (user_id),
--    INDEX user_profiles_country_index (country),
--    INDEX user_profiles_institution_index (institution),
--
--    CONSTRAINT user_profiles_user_id_foreign
--        FOREIGN KEY (user_id)
--            REFERENCES users(id)
--            ON DELETE CASCADE
-- );

-- ALTER TABLE article_species_details
--     MODIFY gender ENUM('Male', 'Female', 'Both', 'N/A');

-- ALTER TABLE article_species_details
--     MODIFY health_status ENUM('Healthy', 'Diseased', 'Mixed', 'N/A');

-- ALTER TABLE verified_authors
--     MODIFY COLUMN orcid VARCHAR(500) NULL;

CREATE TABLE `navigation_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` BIGINT UNSIGNED DEFAULT NULL,
    `type` ENUM('nav_item', 'featured', 'section', 'section_item') NOT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `path` VARCHAR(255) DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `has_mega_menu` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `navigation_items_parent_id_foreign` (`parent_id`),
    CONSTRAINT `navigation_items_parent_id_foreign`
        FOREIGN KEY (`parent_id`) REFERENCES `navigation_items` (`id`)
            ON DELETE CASCADE
)
