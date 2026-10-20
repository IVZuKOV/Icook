-- 02_add_profile_geo_columns.sql
-- Добавляет колонки гео-профиля в users (country, city, address, lat, lng, search_radius)
-- и seller_description, если их нет. Идемпотентно.

USE `icook_db`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `country` VARCHAR(100) DEFAULT 'Россия' AFTER `phone`,
  ADD COLUMN IF NOT EXISTS `city` VARCHAR(100) DEFAULT NULL AFTER `country`,
  ADD COLUMN IF NOT EXISTS `address` TEXT DEFAULT NULL AFTER `city`,
  ADD COLUMN IF NOT EXISTS `lat` DECIMAL(10,7) DEFAULT NULL AFTER `address`,
  ADD COLUMN IF NOT EXISTS `lng` DECIMAL(10,7) DEFAULT NULL AFTER `lat`,
  ADD COLUMN IF NOT EXISTS `search_radius` INT DEFAULT 10 AFTER `lng`,
  ADD COLUMN IF NOT EXISTS `seller_description` TEXT DEFAULT NULL;
