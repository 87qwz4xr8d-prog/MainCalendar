-- นำเข้าไฟล์นี้ใน phpMyAdmin ทั้งไฟล์
-- การนำเข้าซ้ำจะลบข้อมูลเดิมของฐาน company_calendar
SET NAMES utf8mb4;
SET time_zone = '+07:00';

CREATE DATABASE IF NOT EXISTS company_calendar
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE company_calendar;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  color CHAR(7) NOT NULL DEFAULT '#1a73e8',
  description VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_departments_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  employee_code VARCHAR(20) NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'employee') NOT NULL DEFAULT 'employee',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_employee_code (employee_code),
  CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  location VARCHAR(150) NULL,
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  all_day TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('planned', 'in_progress', 'done') NOT NULL DEFAULT 'planned',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_events_range (start_at, end_at),
  KEY idx_events_department (department_id),
  KEY idx_events_user (user_id),
  CONSTRAINT fk_events_user FOREIGN KEY (user_id) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_events_department FOREIGN KEY (department_id) REFERENCES departments (id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_events_time CHECK (end_at > start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO departments (id, name, color, description) VALUES
(1, 'ผู้บริหาร', '#5f6368', 'งานบริหารและประชุมผู้บริหาร'),
(2, 'ฝ่ายบุคคล', '#d81b60', 'สรรหา ปฐมนิเทศ และดูแลพนักงาน'),
(3, 'ฝ่ายขาย', '#1a73e8', 'ลูกค้า เป้าหมาย และปิดการขาย'),
(4, 'ฝ่ายการเงิน', '#188038', 'งบประมาณ รายงาน และปิดงวด'),
(5, 'ฝ่ายไอที', '#f9ab00', 'ระบบภายในและโครงสร้างพื้นฐาน'),
(6, 'ฝ่ายปฏิบัติการ', '#8e24aa', 'คลังสินค้าและการส่งมอบ');

-- ทุกบัญชีเข้าด้วยรหัสพนักงานหรืออีเมล
-- ผู้ดูแล ADMIN รหัสผ่าน Admin@1234
-- พนักงาน EMP001 รหัสผ่าน Employee@1234
INSERT INTO users (id, department_id, name, employee_code, email, password_hash, role, is_active) VALUES
(1, 1, 'ผู้ดูแลระบบ', 'ADMIN', 'admin@company.local', '$2y$12$NxbHBrQC5eoQ8xrhTTjNKumlhhCm/x.bm679B3TdufvwPRpRopFdq', 'admin', 1),
(2, 3, 'สมชาย ใจดี', 'EMP001', 'somchai@company.local', '$2y$12$Y65r0lQqc7V9R5/QoSjQletFwXxHnn0yd6PtrTlmX0wQfP2H0suBC', 'employee', 1),
(3, 2, 'มาลี วงศ์สุข', 'EMP002', 'malee@company.local', '$2y$12$Y65r0lQqc7V9R5/QoSjQletFwXxHnn0yd6PtrTlmX0wQfP2H0suBC', 'employee', 1),
(4, 4, 'อนันต์ ศรีเงิน', 'EMP003', 'anan@company.local', '$2y$12$Y65r0lQqc7V9R5/QoSjQletFwXxHnn0yd6PtrTlmX0wQfP2H0suBC', 'employee', 1),
(5, 5, 'ณิชา พัฒนา', 'EMP004', 'nicha@company.local', '$2y$12$Y65r0lQqc7V9R5/QoSjQletFwXxHnn0yd6PtrTlmX0wQfP2H0suBC', 'employee', 1),
(6, 6, 'วิชัย คลังดี', 'EMP005', 'wichai@company.local', '$2y$12$Y65r0lQqc7V9R5/QoSjQletFwXxHnn0yd6PtrTlmX0wQfP2H0suBC', 'employee', 1);

INSERT INTO events (user_id, department_id, title, description, location, start_at, end_at, all_day, status) VALUES
(2, 3, 'ประชุมทีมขายประจำสัปดาห์', 'สรุปเป้าขายและงานค้างของสัปดาห์', 'ห้องประชุม A', CONCAT(CURDATE(), ' 09:00:00'), CONCAT(CURDATE(), ' 10:30:00'), 0, 'in_progress'),
(3, 2, 'สัมภาษณ์ผู้สมัครตำแหน่งการตลาด', 'รอบสัมภาษณ์ครั้งแรก', 'ห้องบุคคล', CONCAT(CURDATE(), ' 09:30:00'), CONCAT(CURDATE(), ' 11:00:00'), 0, 'planned'),
(5, 5, 'ตรวจสุขภาพเซิร์ฟเวอร์', 'ตรวจพื้นที่ดิสก์และสำรองข้อมูล', 'ห้องเซิร์ฟเวอร์', CONCAT(CURDATE(), ' 14:00:00'), CONCAT(CURDATE(), ' 16:00:00'), 0, 'in_progress'),
(4, 4, 'ส่งรายงานกระแสเงินสด', 'สรุปตัวเลขให้ผู้บริหาร', 'ฝ่ายการเงิน', CONCAT(CURDATE(), ' 15:00:00'), CONCAT(CURDATE(), ' 16:30:00'), 0, 'planned'),
(2, 3, 'พบลูกค้า บริษัท ABC', 'นำเสนอข้อเสนอไตรมาสนี้', 'ห้องลูกค้า', CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 13:00:00'), CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 15:00:00'), 0, 'done'),
(6, 6, 'ตรวจรับสินค้าเข้าคลัง', 'ตรวจจำนวนตามใบสั่งซื้อ', 'คลังสินค้า', CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 08:30:00'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 12:00:00'), 0, 'planned'),
(3, 2, 'ปฐมนิเทศพนักงานใหม่', 'แนะนำนโยบายและสวัสดิการ', 'ห้องฝึกอบรม', CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY), ' 00:00:00'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 2 DAY), ' 00:00:00'), 1, 'planned'),
(1, 1, 'ประชุมผู้บริหาร', 'ติดตามงานค้างข้ามแผนก', 'ห้องประชุมใหญ่', CONCAT(DATE_ADD(CURDATE(), INTERVAL 2 DAY), ' 10:00:00'), CONCAT(DATE_ADD(CURDATE(), INTERVAL 2 DAY), ' 11:30:00'), 0, 'planned');
