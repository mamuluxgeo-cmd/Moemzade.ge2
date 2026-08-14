SET NAMES utf8mb4;

ALTER TABLE catalog_categories
    ADD COLUMN parent_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER config_key,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER parent_id,
    ADD KEY catalog_categories_parent_idx (parent_id),
    ADD KEY catalog_categories_tree_idx (is_active, parent_id, sort_order, name),
    ADD CONSTRAINT catalog_categories_parent_fk FOREIGN KEY (parent_id)
        REFERENCES catalog_categories(id) ON DELETE RESTRICT;
