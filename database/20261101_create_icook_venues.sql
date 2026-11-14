CREATE DATABASE IF NOT EXISTS icook_venues
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE icook_venues;

CREATE TABLE IF NOT EXISTS venues (
    osm_type ENUM('node', 'way', 'relation') NOT NULL,
    osm_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(500) NOT NULL,
    website VARCHAR(2048) NOT NULL DEFAULT '',
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (osm_type, osm_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venue_ratings (
    osm_type ENUM('node', 'way', 'relation') NOT NULL,
    osm_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (osm_type, osm_id, user_id),
    KEY idx_venue_ratings_user (user_id),
    CONSTRAINT fk_venue_ratings_venue
        FOREIGN KEY (osm_type, osm_id) REFERENCES venues (osm_type, osm_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_venue_ratings_value CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venue_photos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    osm_type ENUM('node', 'way', 'relation') NOT NULL,
    osm_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    source_type ENUM('upload', 'official_site') NOT NULL,
    source_url VARCHAR(2048) DEFAULT NULL,
    pending_filename VARCHAR(100) NOT NULL,
    public_path VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_venue_photos_status_created (status, created_at),
    KEY idx_venue_photos_place (osm_type, osm_id, status),
    KEY idx_venue_photos_user (user_id),
    CONSTRAINT fk_venue_photos_venue
        FOREIGN KEY (osm_type, osm_id) REFERENCES venues (osm_type, osm_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
