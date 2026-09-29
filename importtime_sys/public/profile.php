<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$formError = null;
$values = [
    'full_name' => (string) $user['full_name'],
    'department_name' => (string) $user['department_name'],
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf('profile.php');
    $values['full_name'] = post_string('full_name');
    $values['department_name'] = post_string('department_name');
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';
    $nameError = text_error($values['full_name'], 150, 'ชื่อ', true);
    $deptError = text_error($values['department_name'], 120, 'แผนก', false);
    if ($nameError !== null) {
        $formError = $nameError;
    } elseif ($deptError !== null) {
        $formError = $deptError;
    } elseif ($password !== '' && ($problem = password_problem($password)) !== null) {
        $formError = $problem;
    } else {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('UPDATE users SET full_name = ?, department_name = ?, password_hash = ? WHERE id = ?');
            $stmt->execute([$values['full_name'], $values['department_name'], $hash, (int) $user['id']]);
        } else {
            $stmt = db()->prepare('UPDATE users SET full_name = ?, department_name = ? WHERE id = ?');
            $stmt->execute([$values['full_name'], $values['department_name'], (int) $user['id']]);
        }
        flash('success', 'บันทึกโปรไฟล์แล้ว');
        redirect('profile.php');
    }
}

$pageTitle = 'โปรไฟล์';
$activeNav = '';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>โปรไฟล์</h1>
        <p class="lead">ชื่อและแผนกนี้จะถูกเติมให้ตอนสร้างใบขอราคาใหม่</p>
    </div>
</div>
<form class="card" method="post" action="profile.php" style="max-width:560px">
    <?= csrf_input() ?>
    <div class="stack">
        <div>
            <label for="username">ชื่อผู้ใช้</label>
            <input id="username" value="<?= e((string) $user['username']) ?>" disabled>
        </div>
        <div>
            <label class="req" for="full_name">ชื่อ</label>
            <input id="full_name" name="full_name" maxlength="150" required value="<?= e($values['full_name']) ?>">
        </div>
        <div>
            <label for="department_name">แผนก</label>
            <input id="department_name" name="department_name" maxlength="120" value="<?= e($values['department_name']) ?>">
        </div>
        <div>
            <label for="password">รหัสผ่านใหม่</label>
            <input id="password" name="password" type="password" maxlength="72" autocomplete="new-password">
            <p class="note">เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน ต้องมีอย่างน้อย 8 ตัว และมีทั้งตัวอักษรกับตัวเลข</p>
        </div>
        <button class="btn" type="submit">บันทึก</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
