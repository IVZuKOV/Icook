-- ===========================================================================
-- iCook — схема базы данных
-- ===========================================================================
-- Файл выполняется автоматически MySQL-контейнером при ПЕРВОЙ инициализации
-- (когда том db_data ещё пуст). Чтобы применить изменения к уже поднятой БД:
--     docker compose down -v && docker compose up -d
--
-- ВАЖНО: схема реконструирована по коду фронтенда и api/*.php.
--   users / email_verifications  — используются текущим API (точно).
--   categories / dishes / orders / order_items — ожидаются фронтендом
--   (menu.html, profile.html, seller.html), но соответствующие эндпоинты
--   (dishes.php, categories.php, orders.php, ...) в репозитории отсутствуют.
--   Перед продакшеном сверьте схему с реальной базой на сервере.
-- ===========================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `icook_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `icook_db`;

-- ---------------------------------------------------------------------------
-- Пользователи
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)  NOT NULL,
  `email`       VARCHAR(190)  NOT NULL,
  `password`    VARCHAR(255)  NOT NULL,
  `phone`       VARCHAR(32)            DEFAULT NULL,
  `role`        ENUM('buyer','seller','admin') NOT NULL DEFAULT 'buyer',
  `is_verified` TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_name`  (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Токены подтверждения email / сброса пароля
-- (api/send_verify.php, api/reset_password.php используют ON DUPLICATE KEY
--  UPDATE по email — поэтому email PRIMARY KEY)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_verifications` (
  `email`      VARCHAR(190) NOT NULL,
  `token`      VARCHAR(128) NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`email`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Категории блюд (api/categories.php)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `slug`       VARCHAR(100) NOT NULL,
  `icon`       VARCHAR(255)          DEFAULT NULL,
  `sort_order` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Блюда (api/dishes.php, api/add_dish.php, api/edit_dish.php)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dishes` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `seller_id`   INT UNSIGNED           DEFAULT NULL,
  `category_id` INT UNSIGNED           DEFAULT NULL,
  `title`       VARCHAR(150)  NOT NULL,
  `description` TEXT                   DEFAULT NULL,
  `price`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `image`       VARCHAR(255)           DEFAULT NULL,
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dishes_seller`   (`seller_id`),
  KEY `idx_dishes_category` (`category_id`),
  CONSTRAINT `fk_dishes_seller`
    FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dishes_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Заказы (api/orders.php, api/create_order.php, api/update_order.php)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `buyer_id`   INT UNSIGNED  NOT NULL,
  `seller_id`  INT UNSIGNED           DEFAULT NULL,
  `status`     ENUM('new','accepted','cooking','delivering','done','cancelled')
               NOT NULL DEFAULT 'new',
  `total`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `address`    VARCHAR(255)           DEFAULT NULL,
  `phone`      VARCHAR(32)            DEFAULT NULL,
  `comment`    TEXT                   DEFAULT NULL,
  `created_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orders_buyer`  (`buyer_id`),
  KEY `idx_orders_seller` (`seller_id`),
  KEY `idx_orders_status` (`status`),
  CONSTRAINT `fk_orders_buyer`
    FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orders_seller`
    FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Позиции заказа
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED  NOT NULL,
  `dish_id`  INT UNSIGNED           DEFAULT NULL,
  `title`    VARCHAR(150)  NOT NULL,
  `price`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `qty`      INT UNSIGNED  NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  CONSTRAINT `fk_order_items_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_dish`
    FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Демо-данные: категории
-- ---------------------------------------------------------------------------
INSERT INTO `categories` (`name`, `slug`, `sort_order`) VALUES
  ('Пицца',    'pizza',    1),
  ('Суши',     'sushi',    2),
  ('Бургеры',  'burgers',  3),
  ('Десерты',  'desserts', 4),
  ('Напитки',  'drinks',   5)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
