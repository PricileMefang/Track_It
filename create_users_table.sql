-- Run this SQL in your MySQL (phpMyAdmin or mysql CLI) to create the users table

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notes:
-- 1) Ensure your `config.php` connection (host, port, database) is correct for your XAMPP MySQL.
-- 2) If MySQL runs on the default port 3306, update `config.php` to use 3306 or remove the port parameter.
-- 3) After running this, you can register via the SignUp page and verify rows in this table.
