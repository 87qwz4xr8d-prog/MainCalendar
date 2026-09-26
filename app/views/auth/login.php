<?php $pageTitle = 'เข้าสู่ระบบ'; ?>
<div class="login-wrap">
    <section class="login-hero">
        <div class="login-brand">
            <span class="brand-mark brand-mark-lg" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
            <div>
                <strong><?= e($appName) ?></strong>
                <small><?= e($companyName) ?></small>
            </div>
        </div>
        <h1>ปฏิทินกลางของทุกแผนก</h1>
        <p>พนักงานแต่ละแผนกเข้ามาอัปเดตงานของตนเอง ส่วนผู้ดูแลเห็นภาพรวมทั้งบริษัท</p>
        <ul class="login-points">
            <li><i class="bi bi-check2-circle"></i> มุมมองวัน สัปดาห์ และเดือน</li>
            <li><i class="bi bi-check2-circle"></i> กรองงานตามแผนก</li>
            <li><i class="bi bi-check2-circle"></i> แก้ไขได้เฉพาะงานของตนเอง</li>
        </ul>
    </section>
    <section class="login-panel">
        <h2>เข้าสู่ระบบ</h2>
        <p class="text-muted">ใช้อีเมลบริษัทและรหัสผ่านที่ผู้ดูแลกำหนดให้</p>
        <form method="post" action="<?= e(url('login')) ?>" class="login-form" autocomplete="on">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="email">อีเมล</label>
                <input class="form-control form-control-lg" type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" placeholder="name@company.local" autocomplete="username" required autofocus>
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">รหัสผ่าน</label>
                <input class="form-control form-control-lg" type="password" id="password" name="password" placeholder="รหัสผ่าน" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit">เข้าสู่ระบบ</button>
        </form>
        <a class="d-inline-block mt-3" href="<?= e(url('calendar')) ?>">ดูปฏิทินโดยไม่เข้าสู่ระบบ</a>
        <?php if (app_config('debug')): ?>
            <div class="demo-box">
                <p>บัญชีทดลอง</p>
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-fill="admin@company.local" data-pass="Admin@1234">ผู้ดูแลระบบ</button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-fill="somchai@company.local" data-pass="Employee@1234">พนักงานฝ่ายขาย</button>
                </div>
            </div>
        <?php endif; ?>
    </section>
</div>
