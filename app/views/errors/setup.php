<?php $pageTitle = 'ตั้งค่าฐานข้อมูล'; ?>
<div class="setup-card">
    <h1>ยังเชื่อมฐานข้อมูลไม่ได้</h1>
    <p>หน้าเข้าสู่ระบบจะพร้อมเมื่อนำเข้าตารางใน phpMyAdmin แล้ว</p>
    <ol>
        <li>เปิด Apache และ MySQL</li>
        <li>เข้า phpMyAdmin แล้วนำเข้าไฟล์ <code>database/schema.sql</code></li>
        <li>ตรวจผู้ใช้และรหัสผ่านใน <code>app/config/database.php</code></li>
        <li>เปิดหน้านี้ใหม่อีกครั้ง</li>
    </ol>
    <?php if (app_config('debug') && !empty($error)): ?>
        <pre class="setup-error"><?= e($error) ?></pre>
    <?php endif; ?>
</div>
