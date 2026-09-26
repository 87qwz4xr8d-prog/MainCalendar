<?php $pageTitle = 'โปรไฟล์'; ?>
<div class="page-head">
    <div>
        <h1>โปรไฟล์</h1>
        <p><?= e($currentUser['department_name']) ?> · <?= $currentUser['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'พนักงาน' ?></p>
    </div>
</div>
<div class="panel p-4" style="max-width: 640px;">
    <form method="post" action="<?= e(url('profile')) ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label" for="name">ชื่อ</label>
            <input class="form-control" id="name" name="name" value="<?= e($currentUser['name']) ?>" maxlength="120" required>
        </div>
        <?php if (($currentUser['employee_code'] ?? '') !== ''): ?>
            <div class="mb-3">
                <label class="form-label" for="employee_code">รหัสพนักงาน</label>
                <input class="form-control" id="employee_code" value="<?= e($currentUser['employee_code']) ?>" readonly>
            </div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label" for="email">อีเมล</label>
            <input class="form-control" id="email" value="<?= e($currentUser['email']) ?>" readonly>
        </div>
        <hr>
        <p class="text-muted">เปลี่ยนรหัสผ่านเมื่อต้องการเท่านั้น</p>
        <div class="mb-3">
            <label class="form-label" for="current_password">รหัสผ่านปัจจุบัน</label>
            <input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password">
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="password">รหัสผ่านใหม่</label>
                <input class="form-control" type="password" id="password" name="password" autocomplete="new-password">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password_confirm">ยืนยันรหัสผ่านใหม่</label>
                <input class="form-control" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password">
            </div>
        </div>
        <button class="btn btn-primary mt-4" type="submit">บันทึก</button>
    </form>
</div>
