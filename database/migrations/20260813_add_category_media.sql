CREATE TABLE IF NOT EXISTS catalog_category_media (
    category_id BIGINT UNSIGNED NOT NULL,
    storage_driver VARCHAR(32) NOT NULL,
    storage_key VARCHAR(500) NOT NULL,
    public_url VARCHAR(1000) NOT NULL,
    width INT UNSIGNED NOT NULL,
    height INT UNSIGNED NOT NULL,
    bytes INT UNSIGNED NOT NULL,
    mime VARCHAR(100) NOT NULL DEFAULT 'image/webp',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (category_id),
    CONSTRAINT catalog_category_media_category_fk FOREIGN KEY (category_id)
        REFERENCES catalog_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
