-- ระบบบันทึกการขอราคาพาร์ทต่างประเทศ และเขียนใบขอซื้อ
-- นำเข้าไฟล์นี้ผ่าน phpMyAdmin
-- รหัสผ่านของบัญชีเริ่มต้นอยู่ใน README เท่านั้น (ไฟล์นี้เก็บเฉพาะค่าแฮช)
-- การนำเข้าซ้ำจะลบข้อมูลเดิมในฐาน importtime_sys

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `importtime_sys`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `importtime_sys`;

DROP TABLE IF EXISTS `purchase_request_items`;
DROP TABLE IF EXISTS `purchase_requests`;
DROP TABLE IF EXISTS `price_request_items`;
DROP TABLE IF EXISTS `price_requests`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `department_name` VARCHAR(120) NOT NULL DEFAULT '',
  `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `suppliers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(200) NOT NULL,
  `country` VARCHAR(80) NOT NULL DEFAULT '',
  `contact` VARCHAR(200) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_suppliers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `price_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_no` VARCHAR(30) NOT NULL,
  `request_date` DATE NOT NULL,
  `requester_name` VARCHAR(150) NOT NULL,
  `department_name` VARCHAR(120) NOT NULL,
  `machine_name` VARCHAR(150) NOT NULL,
  `machine_code` VARCHAR(80) NOT NULL DEFAULT '',
  `purpose` TEXT NOT NULL,
  `supplier_name` VARCHAR(200) NOT NULL,
  `supplier_country` VARCHAR(80) NOT NULL DEFAULT '',
  `supplier_contact` VARCHAR(200) NOT NULL DEFAULT '',
  `currency` CHAR(3) NOT NULL,
  `incoterm` VARCHAR(10) NOT NULL DEFAULT '',
  `payment_term` VARCHAR(150) NOT NULL DEFAULT '',
  `quote_no` VARCHAR(80) NOT NULL DEFAULT '',
  `quote_date` DATE NULL,
  `valid_until` DATE NULL,
  `lead_time` VARCHAR(120) NOT NULL DEFAULT '',
  `freight` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `exchange_rate` DECIMAL(14,6) NULL,
  `status` ENUM('draft', 'requested', 'quoted', 'cancelled') NOT NULL DEFAULT 'draft',
  `remark` TEXT NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_requests_no` (`request_no`),
  KEY `idx_price_requests_status` (`status`, `request_date`),
  KEY `idx_price_requests_supplier` (`supplier_name`),
  CONSTRAINT `fk_price_requests_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `price_request_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `price_request_id` INT UNSIGNED NOT NULL,
  `line_no` INT UNSIGNED NOT NULL,
  `part_no` VARCHAR(80) NOT NULL,
  `part_name` VARCHAR(200) NOT NULL,
  `specification` VARCHAR(500) NOT NULL DEFAULT '',
  `brand` VARCHAR(120) NOT NULL DEFAULT '',
  `unit_name` VARCHAR(30) NOT NULL DEFAULT 'ชิ้น',
  `qty` DECIMAL(12,2) NOT NULL,
  `unit_price` DECIMAL(14,4) NULL,
  `line_remark` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_items_request` (`price_request_id`, `line_no`),
  KEY `idx_items_part` (`part_no`),
  CONSTRAINT `fk_items_request` FOREIGN KEY (`price_request_id`) REFERENCES `price_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_requests` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pr_no` VARCHAR(30) NOT NULL,
  `pr_date` DATE NOT NULL,
  `price_request_id` INT UNSIGNED NOT NULL,
  `request_no` VARCHAR(30) NOT NULL,
  `requester_name` VARCHAR(150) NOT NULL,
  `department_name` VARCHAR(120) NOT NULL,
  `machine_name` VARCHAR(150) NOT NULL,
  `machine_code` VARCHAR(80) NOT NULL DEFAULT '',
  `supplier_name` VARCHAR(200) NOT NULL,
  `supplier_country` VARCHAR(80) NOT NULL DEFAULT '',
  `supplier_contact` VARCHAR(200) NOT NULL DEFAULT '',
  `currency` CHAR(3) NOT NULL,
  `incoterm` VARCHAR(10) NOT NULL DEFAULT '',
  `payment_term` VARCHAR(150) NOT NULL DEFAULT '',
  `quote_no` VARCHAR(80) NOT NULL DEFAULT '',
  `quote_date` DATE NULL,
  `valid_until` DATE NULL,
  `lead_time` VARCHAR(120) NOT NULL DEFAULT '',
  `freight` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `exchange_rate` DECIMAL(14,6) NULL,
  `needed_date` DATE NULL,
  `deliver_to` VARCHAR(200) NOT NULL DEFAULT '',
  `reason` TEXT NOT NULL,
  `remark` TEXT NULL,
  `status` ENUM('draft', 'issued', 'cancelled') NOT NULL DEFAULT 'draft',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_no` (`pr_no`),
  KEY `idx_pr_source` (`price_request_id`, `status`),
  CONSTRAINT `fk_pr_source` FOREIGN KEY (`price_request_id`) REFERENCES `price_requests` (`id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `purchase_request_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_request_id` INT UNSIGNED NOT NULL,
  `line_no` INT UNSIGNED NOT NULL,
  `part_no` VARCHAR(80) NOT NULL,
  `part_name` VARCHAR(200) NOT NULL,
  `specification` VARCHAR(500) NOT NULL DEFAULT '',
  `brand` VARCHAR(120) NOT NULL DEFAULT '',
  `unit_name` VARCHAR(30) NOT NULL,
  `qty` DECIMAL(12,2) NOT NULL,
  `quoted_qty` DECIMAL(12,2) NOT NULL,
  `unit_price` DECIMAL(14,4) NOT NULL,
  `line_remark` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_pr_items` (`purchase_request_id`, `line_no`),
  CONSTRAINT `fk_pr_items` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`username`, `password_hash`, `full_name`, `department_name`, `role`, `is_active`) VALUES
('admin', '$2y$10$N.SRSIigClj2EHM7QcJyXOjBRMyWf5tIMIGXV52cleht/R4LXcL/W', 'ผู้ดูแลระบบ', 'จัดซื้อ', 'admin', 1);

INSERT INTO `suppliers` (`name`, `country`, `contact`) VALUES
('SMC Corporation', 'Japan', 'sales@example.jp'),
('SKF', 'Sweden', 'quotation@example.se'),
('MISUMI', 'Japan', 'export@example.jp');

SET FOREIGN_KEY_CHECKS = 1;
