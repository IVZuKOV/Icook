ALTER TABLE promocodes
    ADD COLUMN is_global TINYINT(1) NOT NULL DEFAULT 1 AFTER discount_percent,
    ADD KEY idx_promocodes_global_active (cook_id, is_global, is_active);

CREATE TABLE promocode_dishes (
    promocode_id BIGINT UNSIGNED NOT NULL,
    dish_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (promocode_id, dish_id),
    KEY idx_promocode_dishes_dish (dish_id, promocode_id),
    CONSTRAINT fk_promocode_dishes_promocode
        FOREIGN KEY (promocode_id) REFERENCES promocodes(id) ON DELETE CASCADE,
    CONSTRAINT fk_promocode_dishes_dish
        FOREIGN KEY (dish_id) REFERENCES dishes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO promocode_dishes (promocode_id, dish_id)
SELECT id, dish_id FROM promocodes WHERE dish_id IS NOT NULL;

UPDATE promocodes SET is_global = 0 WHERE dish_id IS NOT NULL;

ALTER TABLE promocodes
    DROP FOREIGN KEY fk_promocodes_dish,
    DROP INDEX idx_promocodes_dish_active,
    DROP COLUMN dish_id;
