SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS teachers (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(190) NOT NULL,
    name_ka VARCHAR(190) NOT NULL DEFAULT '',
    name_en VARCHAR(190) NOT NULL DEFAULT '',
    name_ru VARCHAR(190) NOT NULL DEFAULT '',
    profession_ka VARCHAR(190) NOT NULL DEFAULT '',
    profession_en VARCHAR(190) NOT NULL DEFAULT '',
    profession_ru VARCHAR(190) NOT NULL DEFAULT '',
    bio_ka TEXT NULL,
    bio_en TEXT NULL,
    bio_ru TEXT NULL,
    category VARCHAR(120) NOT NULL DEFAULT '',
    region VARCHAR(120) NOT NULL DEFAULT '',
    settlement VARCHAR(140) NOT NULL DEFAULT '',
    languages VARCHAR(255) NOT NULL DEFAULT '',
    format_online TINYINT(1) NOT NULL DEFAULT 0,
    format_in_person TINYINT(1) NOT NULL DEFAULT 1,
    price_from DECIMAL(10,2) NULL,
    price_unit VARCHAR(40) NOT NULL DEFAULT 'hour',
    phone VARCHAR(50) NOT NULL DEFAULT '',
    facebook_url VARCHAR(500) NOT NULL DEFAULT '',
    instagram_url VARCHAR(500) NOT NULL DEFAULT '',
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    published_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY teachers_slug_unique (slug),
    KEY teachers_status_published_idx (status, published_at),
    KEY teachers_filter_idx (status, category, region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id BIGINT UNSIGNED NOT NULL,
    variant ENUM('large', 'profile', 'thumbnail') NOT NULL,
    storage_driver ENUM('local', 'r2') NOT NULL,
    storage_key VARCHAR(500) NOT NULL,
    public_url VARCHAR(1000) NOT NULL,
    width SMALLINT UNSIGNED NOT NULL,
    height SMALLINT UNSIGNED NOT NULL,
    bytes INT UNSIGNED NOT NULL,
    mime_type VARCHAR(80) NOT NULL DEFAULT 'image/webp',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY teacher_media_variant_unique (teacher_id, variant),
    CONSTRAINT teacher_media_teacher_fk FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id BIGINT UNSIGNED NOT NULL,
    visitor_hash CHAR(64) NOT NULL,
    author_name VARCHAR(100) NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    body VARCHAR(1500) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    published_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY comments_one_per_visitor_teacher (teacher_id, visitor_hash),
    KEY comments_status_updated_idx (status, updated_at),
    CONSTRAINT comments_teacher_fk FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS search_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visitor_hash CHAR(64) NOT NULL,
    source ENUM('search', 'matching') NOT NULL DEFAULT 'search',
    keyword VARCHAR(190) NOT NULL DEFAULT '',
    category VARCHAR(120) NOT NULL DEFAULT '',
    region VARCHAR(120) NOT NULL DEFAULT '',
    teaching_format VARCHAR(30) NOT NULL DEFAULT '',
    language VARCHAR(60) NOT NULL DEFAULT '',
    result_count MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    filters_json TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY search_events_result_created_idx (result_count, created_at),
    KEY search_events_group_idx (category, region, teaching_format, language)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    visitor_hash CHAR(64) NOT NULL,
    category VARCHAR(120) NOT NULL DEFAULT '',
    region VARCHAR(120) NOT NULL DEFAULT '',
    teaching_format VARCHAR(30) NOT NULL DEFAULT '',
    language VARCHAR(60) NOT NULL DEFAULT '',
    matched_count MEDIUMINT UNSIGNED NOT NULL DEFAULT 0,
    request_json TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY match_requests_result_created_idx (matched_count, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_daily_visitors (
    visit_date DATE NOT NULL,
    visitor_hash CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (visit_date, visitor_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_views_daily (
    view_date DATE NOT NULL,
    path VARCHAR(190) NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (view_date, path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teacher_daily_visitors (
    view_date DATE NOT NULL,
    teacher_id BIGINT UNSIGNED NOT NULL,
    visitor_hash CHAR(64) NOT NULL,
    view_count MEDIUMINT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (view_date, teacher_id, visitor_hash),
    KEY teacher_daily_views_teacher_idx (teacher_id, view_date),
    CONSTRAINT teacher_daily_views_teacher_fk FOREIGN KEY (teacher_id) REFERENCES teachers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

