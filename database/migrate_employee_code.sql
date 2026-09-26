-- ใช้กับฐานที่มีอยู่แล้ว ไม่ลบข้อมูล
-- ถ้านำเข้า schema.sql ใหม่ทั้งไฟล์ ไม่ต้องรันไฟล์นี้
USE company_calendar;

ALTER TABLE users
  ADD COLUMN employee_code VARCHAR(20) NULL AFTER name,
  ADD UNIQUE KEY uq_users_employee_code (employee_code);

UPDATE users SET employee_code = 'ADMIN' WHERE email = 'admin@company.local' AND employee_code IS NULL;
UPDATE users SET employee_code = 'EMP001' WHERE email = 'somchai@company.local' AND employee_code IS NULL;
UPDATE users SET employee_code = 'EMP002' WHERE email = 'malee@company.local' AND employee_code IS NULL;
UPDATE users SET employee_code = 'EMP003' WHERE email = 'anan@company.local' AND employee_code IS NULL;
UPDATE users SET employee_code = 'EMP004' WHERE email = 'nicha@company.local' AND employee_code IS NULL;
UPDATE users SET employee_code = 'EMP005' WHERE email = 'wichai@company.local' AND employee_code IS NULL;
