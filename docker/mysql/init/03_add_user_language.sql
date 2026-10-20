-- 03_add_user_language.sql
-- Добавляет колонку языка интерфейса в users

USE `icook_db`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `lang` VARCHAR(10) DEFAULT 'ru' AFTER `search_radius`;
