SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS catalog_categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    config_key VARCHAR(120) NULL DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY catalog_categories_name_unique (name),
    UNIQUE KEY catalog_categories_config_key_unique (config_key),
    KEY catalog_categories_sort_idx (sort_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_regions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    config_key VARCHAR(120) NULL DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY catalog_regions_name_unique (name),
    UNIQUE KEY catalog_regions_config_key_unique (config_key),
    KEY catalog_regions_sort_idx (sort_order, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_settlements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    region_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(140) NOT NULL,
    config_key CHAR(64) NULL DEFAULT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 100,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY catalog_settlements_region_name_unique (region_id, name),
    UNIQUE KEY catalog_settlements_config_key_unique (config_key),
    KEY catalog_settlements_sort_idx (region_id, sort_order, name),
    CONSTRAINT catalog_settlements_region_fk FOREIGN KEY (region_id) REFERENCES catalog_regions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
