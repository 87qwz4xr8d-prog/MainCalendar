<?php

declare(strict_types=1);

/**
 * ค่าทั่วไปของระบบ
 * เปลี่ยนชื่อบริษัทได้ที่คีย์ company โดยไม่ต้องแก้หลายไฟล์
 * ก่อนใช้งานจริงให้ตั้ง debug เป็น false
 */
return [
    'name' => 'ปฏิทินกลาง',
    'company' => 'บริษัทของเรา',
    'timezone' => 'Asia/Bangkok',
    'debug' => true,
    'session_name' => 'company_calendar_sess',
];
