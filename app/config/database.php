<?php

declare(strict_types=1);

/**
 * การเชื่อมต่อ MySQL สำหรับ XAMPP / phpMyAdmin
 * ค่าเริ่มต้นคือผู้ใช้ root ไม่มีรหัสผ่าน ซึ่งเป็นค่ามาตรฐานของ XAMPP
 * ถ้าเครื่องคุณตั้งรหัสไว้ ให้แก้ password ที่นี่
 * หรือส่งค่าผ่านตัวแปรสภาพแวดล้อม DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 */
return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_NAME') ?: 'company_calendar',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') === false ? '' : (string) getenv('DB_PASS'),
    'charset' => 'utf8mb4',
];
