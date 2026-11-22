CREATE TABLE IF NOT EXISTS promocodes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cook_id INT NOT NULL,
    code VARCHAR(40) NOT NULL,
    discount_percent TINYINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_promocodes_cook_code (cook_id, code),
    KEY idx_promocodes_cook_active (cook_id, is_active),
    CONSTRAINT fk_promocodes_cook FOREIGN KEY (cook_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_promocodes_discount_percent CHECK (discount_percent BETWEEN 1 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
