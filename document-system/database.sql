-- ระบบจัดเก็บเอกสาร
-- นำเข้าไฟล์นี้ผ่าน phpMyAdmin
-- รหัสผ่านของบัญชีเริ่มต้นอยู่ใน README เท่านั้น (ไฟล์นี้เก็บเฉพาะค่าแฮช)

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `document_archive`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `document_archive`;

DROP TABLE IF EXISTS `documents`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doc_number` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `description` TEXT DEFAULT NULL,
  `stored_name` VARCHAR(80) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `mime_type` VARCHAR(127) NOT NULL,
  `status` ENUM('active', 'archived') NOT NULL DEFAULT 'active',
  `uploaded_by` INT UNSIGNED NOT NULL,
  `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documents_doc_number` (`doc_number`),
  KEY `idx_documents_category` (`category_id`),
  KEY `idx_documents_status` (`status`),
  KEY `idx_documents_uploaded_at` (`uploaded_at`),
  KEY `idx_documents_title` (`title`),
  CONSTRAINT `fk_documents_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `fk_documents_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`username`, `password_hash`, `full_name`, `role`, `is_active`) VALUES
('admin', '$2y$10$Xhx/yma09zyv3dKwFOPXiOqib29gmQDHbPyjV8AqBgKcqT6AweI2q', 'ผู้ดูแลระบบ', 'admin', 1);

INSERT INTO `categories` (`name`, `description`) VALUES
('หนังสือราชการ', 'หนังสือภายในและหนังสือภายนอก'),
('เอกสารการเงิน', 'ใบสำคัญและรายงานการเงิน'),
('เอกสารสัญญา', 'สัญญาและบันทึกข้อตกลง'),
('รูปภาพและสื่อ', 'ภาพถ่ายและไฟล์สื่อประกอบ');

SET FOREIGN_KEY_CHECKS = 1;
