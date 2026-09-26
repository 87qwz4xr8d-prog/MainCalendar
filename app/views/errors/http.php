<?php $pageTitle = (string) ($code ?? 'ข้อผิดพลาด'); ?>
<div class="setup-card">
    <h1><?= e((string) ($code ?? '')) ?></h1>
    <p><?= e($message ?? 'ไม่สามารถเปิดหน้านี้ได้') ?></p>
    <a class="btn btn-primary" href="<?= e(url(current_user() ? 'calendar' : 'login')) ?>">กลับหน้าหลัก</a>
</div>
