-- 04_notifications.sql
-- Таблица уведомлений пользователей

USE `icook_db`;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT          NOT NULL,
  `title`      VARCHAR(255)          DEFAULT NULL,
  `message`    TEXT         NOT NULL,
  `type`       VARCHAR(50)           DEFAULT 'info',
  `related_id` INT                   DEFAULT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
