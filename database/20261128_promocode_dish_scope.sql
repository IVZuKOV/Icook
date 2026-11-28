ALTER TABLE promocodes
    ADD COLUMN dish_id INT NULL AFTER cook_id,
    ADD KEY idx_promocodes_dish_active (dish_id, is_active),
    ADD CONSTRAINT fk_promocodes_dish
        FOREIGN KEY (dish_id) REFERENCES dishes(id) ON DELETE CASCADE;
