CREATE TABLE IF NOT EXISTS index_banners (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(80) NOT NULL,
    tag VARCHAR(40) DEFAULT NULL,
    show_tag TINYINT(1) NOT NULL DEFAULT 1,
    title VARCHAR(120) NOT NULL,
    show_title TINYINT(1) NOT NULL DEFAULT 1,
    description VARCHAR(500) NOT NULL DEFAULT '',
    show_description TINYINT(1) NOT NULL DEFAULT 1,
    link VARCHAR(2048) DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_index_banners_slug (slug),
    KEY idx_index_banners_active_sort (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO index_banners (slug, tag, title, description, link, is_active, sort_order)
VALUES
    ('homepage-hot', 'Хит', 'Горячее', 'Сковородки, мангалы и духовки — лучшие рецепты недели от наших поваров.', 'index.html', 1, 10),
    ('homepage-new', 'Новинки', 'Свежие идеи', 'Новые блюда и десерты от наших поваров.', 'index.html', 1, 20),
    ('homepage-sale', 'Спец', 'Скидки', 'Выгодные предложения для наших подписчиков.', 'index.html', 1, 30)
ON DUPLICATE KEY UPDATE slug = VALUES(slug);
