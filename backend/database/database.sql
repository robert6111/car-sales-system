CREATE DATABASE IF NOT EXISTS `car_sales_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `car_sales_system`;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `leads`;
DROP TABLE IF EXISTS `car_sales`;
DROP TABLE IF EXISTS `car_costs`;
DROP TABLE IF EXISTS `cars`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_setup` tinyint(1) NOT NULL DEFAULT 0,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cars` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `car_name` varchar(120) NOT NULL,
  `plate_number` varchar(50) DEFAULT NULL,
  `chassis_number` varchar(80) NOT NULL,
  `purchase_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `purchase_date` date NOT NULL,
  `status` enum('IN_STOCK','SOLD') NOT NULL DEFAULT 'IN_STOCK',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plate_number` (`plate_number`),
  UNIQUE KEY `chassis_number` (`chassis_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `cars_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `car_costs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `car_id` int NOT NULL,
  `cost_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cost_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `car_id` (`car_id`),
  CONSTRAINT `car_costs_car_fk` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `car_sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `car_id` int NOT NULL,
  `sale_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sale_date` date NOT NULL,
  `buyer_name` varchar(120) NOT NULL,
  `buyer_phone` varchar(40) DEFAULT NULL,
  `buyer_email` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `car_id_unique` (`car_id`),
  CONSTRAINT `car_sales_car_fk` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `leads` (
  `id` int NOT NULL AUTO_INCREMENT,
  `car_id` int DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `status` enum('NEW','FOLLOW_UP','CLOSED') NOT NULL DEFAULT 'NEW',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `car_id` (`car_id`),
  CONSTRAINT `leads_car_fk` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`, `is_setup`) VALUES
('Setup Admin', 'setup@carsales.local', '$2y$12$f5WQ.Jrnvk2tN42B19nuKuUU8qJ9TD6x9y8lXGXHanj.0iG.sjf0u', 'admin', 1, 1);
