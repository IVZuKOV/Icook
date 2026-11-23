ALTER TABLE dishes
    ADD COLUMN discount_percent TINYINT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE dish_ingredients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dish_id INT NOT NULL,
    ingredient_name VARCHAR(100) NOT NULL,
    amount_grams DECIMAL(10,2) NOT NULL,
    energy_kj_per_100g DECIMAL(10,2) NOT NULL,
    position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_dish_ingredients_dish_position (dish_id, position),
    KEY idx_dish_ingredients_name (ingredient_name),
    CONSTRAINT fk_dish_ingredients_dish FOREIGN KEY (dish_id) REFERENCES dishes(id) ON DELETE CASCADE,
    CONSTRAINT chk_dish_ingredients_amount CHECK (amount_grams > 0),
    CONSTRAINT chk_dish_ingredients_energy CHECK (energy_kj_per_100g >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dish_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dish_id INT NOT NULL,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    review_text VARCHAR(2000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dish_reviews_buyer_dish (user_id, dish_id),
    KEY idx_dish_reviews_public (dish_id, created_at),
    CONSTRAINT fk_dish_reviews_dish FOREIGN KEY (dish_id) REFERENCES dishes(id) ON DELETE CASCADE,
    CONSTRAINT fk_dish_reviews_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_dish_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_dish_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dish_conversations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dish_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dish_conversation_participants (dish_id, buyer_id, seller_id),
    KEY idx_dish_conversation_buyer (buyer_id, updated_at),
    KEY idx_dish_conversation_seller (seller_id, updated_at),
    CONSTRAINT fk_dish_conversation_dish FOREIGN KEY (dish_id) REFERENCES dishes(id) ON DELETE CASCADE,
    CONSTRAINT fk_dish_conversation_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_dish_conversation_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dish_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT UNSIGNED NOT NULL,
    sender_id INT NOT NULL,
    message_text VARCHAR(2000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dish_messages_conversation (conversation_id, id),
    CONSTRAINT fk_dish_messages_conversation FOREIGN KEY (conversation_id) REFERENCES dish_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_dish_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
