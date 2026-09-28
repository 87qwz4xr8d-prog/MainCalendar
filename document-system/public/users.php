<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$requireAdmin = true;
$actor = require_admin();

$formError = null;
$editId = (int) query_string('id', 20);
$username = '';
$fullName = '';
$role = 'staff';
$isActive = 1;

if (post_too_large()) {
    flash('error', 'ข้อมูลที่ส่งมีขนาดใหญ่เกินไป');
    redirect('users.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_admin();
    require_csrf('users.php');
    $action = post_string('action');

    if ($action === 'toggle') {
        $id = post_int('id');
        $stmt = db()->prepare('SELECT id, role, is_active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            flash('error', 'ไม่พบผู้ใช้');
            redirect('users.php');
        }
        $newActive = (int) $target['is_active'] === 1 ? 0 : 1;
        if ((int) $actor['id'] === (int) $target['id'] && $newActive !== 1) {
            flash('error', 'ไม่สามารถปิดการใช้งานบัญชีของตนเองได้');
            redirect('users.php');
        }
        if ($target['role'] === 'admin' && (int) $target['is_active'] === 1 && $newActive !== 1 && active_admin_count((int) $target['id']) === 0) {
            flash('error', 'ต้องมีผู้ดูแลระบบที่ใช้งานอยู่อย่างน้อย 1 คน');
            redirect('users.php');
        }
        $update = db()->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        $update->execute([$newActive, $id]);
        flash('success', $newActive === 1 ? 'เปิดการใช้งานผู้ใช้แล้ว' : 'ปิดการใช้งานผู้ใช้แล้ว');
        redirect('users.php');
    }

    if ($action !== 'save') {
        flash('error', 'คำขอไม่ถูกต้อง');
        redirect('users.php');
    }

    $editId = post_int('id');
    $username = post_string('username');
    $fullName = post_string('full_name');
    $role = post_string('role');
    $isActive = post_int('is_active') === 1 ? 1 : 0;
    $password = $_POST['password'] ?? '';
    if (!is_string($password)) {
        $password = '';
    }
    $editing = null;
    if ($editId > 0) {
        $stmt = db()->prepare('SELECT id, username, role, is_active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$editId]);
        $editing = $stmt->fetch() ?: null;
    }

    if ($editId > 0 && !$editing) {
        $formError = 'ไม่พบผู้ใช้ที่ต้องการแก้ไข';
    } elseif ($editId === 0 && preg_match('/^[A-Za-z0-9_]{3,50}$/', $username) !== 1) {
        $formError = 'ชื่อผู้ใช้ต้องเป็นภาษาอังกฤษ ตัวเลข หรือ _ ความยาว 3-50 ตัวอักษร';
    } elseif ($fullName === '' || mb_strlen($fullName) > 150 || preg_match('/[\x00-\x1F\x7F]/', $fullName) === 1) {
        $formError = 'กรุณาระบุชื่อ-นามสกุลไม่เกิน 150 ตัวอักษร';
    } elseif (!in_array($role, ['admin', 'staff'], true)) {
        $formError = 'บทบาทไม่ถูกต้อง';
    } elseif (($editId === 0 || $password !== '') && (mb_strlen($password) < 8 || mb_strlen($password) > 72)) {
        $formError = 'รหัสผ่านต้องมีความยาว 8-72 ตัวอักษร';
    } elseif ($editing && (int) $actor['id'] === (int) $editing['id'] && ($role !== 'admin' || $isActive !== 1)) {
        $formError = 'ไม่สามารถปิดการใช้งานหรือลดสิทธิ์บัญชีของตนเองได้';
    } elseif ($editing && $editing['role'] === 'admin' && (int) $editing['is_active'] === 1 && ($role !== 'admin' || $isActive !== 1) && active_admin_count((int) $editing['id']) === 0) {
        $formError = 'ต้องมีผู้ดูแลระบบที่ใช้งานอยู่อย่างน้อย 1 คน';
    } else {
        try {
            if ($editing) {
                if ($password !== '') {
                    $update = db()->prepare('UPDATE users SET full_name = ?, role = ?, is_active = ?, password_hash = ? WHERE id = ?');
                    $update->execute([$fullName, $role, $isActive, password_hash($password, PASSWORD_DEFAULT), (int) $editing['id']]);
                } else {
                    $update = db()->prepare('UPDATE users SET full_name = ?, role = ?, is_active = ? WHERE id = ?');
                    $update->execute([$fullName, $role, $isActive, (int) $editing['id']]);
                }
                flash('success', 'แก้ไขผู้ใช้แล้ว');
            } else {
                $insert = db()->prepare('INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES (?, ?, ?, ?, ?)');
                $insert->execute([$username, password_hash($password, PASSWORD_DEFAULT), $fullName, $role, $isActive]);
                flash('success', 'เพิ่มผู้ใช้แล้ว');
            }
            redirect('users.php');
        } catch (Throwable $e) {
            if (is_duplicate_key($e)) {
                $formError = 'ชื่อผู้ใช้นี้มีอยู่แล้ว';
            } else {
                throw $e;
            }
        }
    }
}

$editUser = null;
if ($formError === null && $editId > 0 && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $stmt = db()->prepare('SELECT id, username, full_name, role, is_active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch() ?: null;
    if (!$editUser) {
        flash('error', 'ไม่พบผู้ใช้');
        redirect('users.php');
    }
    $username = (string) $editUser['username'];
    $fullName = (string) $editUser['full_name'];
    $role = (string) $editUser['role'];
    $isActive = (int) $editUser['is_active'];
} elseif ($editId > 0 && $formError !== null) {
    $stmt = db()->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch() ?: null;
    if ($editUser) {
        $username = (string) $editUser['username'];
    }
}

$users = db()->query('SELECT id, username, full_name, role, is_active, created_at FROM users ORDER BY id')->fetchAll();
$editingSelf = $editUser && (int) $editUser['id'] === (int) $actor['id'];

$pageTitle = 'ผู้ใช้งาน';
$activeNav = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="row">
    <div class="col-lg-4">
        <div class="card card-primary">
            <div class="card-header">
                <h2 class="card-title h5 mb-0"><?= $editUser ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้' ?></h2>
            </div>
            <form method="post" action="users.php" autocomplete="off">
                <div class="card-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $editUser ? (int) $editUser['id'] : 0 ?>">
                    <div class="form-group">
                        <label class="required" for="username">ชื่อผู้ใช้</label>
                        <?php if ($editUser): ?>
                            <input type="text" class="form-control" id="username" value="<?= e($username) ?>" readonly>
                        <?php else: ?>
                            <input type="text" class="form-control" id="username" name="username" maxlength="50" required pattern="[A-Za-z0-9_]{3,50}" value="<?= e($username) ?>">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="required" for="full_name">ชื่อ-นามสกุล</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" maxlength="150" required value="<?= e($fullName) ?>">
                    </div>
                    <div class="form-group">
                        <label class="<?= $editUser ? '' : 'required' ?>" for="password">รหัสผ่าน</label>
                        <input type="password" class="form-control" id="password" name="password" maxlength="72" <?= $editUser ? '' : 'required' ?> autocomplete="new-password">
                        <small class="form-text text-muted"><?= $editUser ? 'เว้นว่างถ้าไม่ต้องการเปลี่ยนรหัสผ่าน' : 'อย่างน้อย 8 ตัวอักษร' ?></small>
                    </div>
                    <div class="form-group">
                        <label class="required" for="role">บทบาท</label>
                        <?php if ($editingSelf): ?>
                            <input type="hidden" name="role" value="admin">
                            <input type="text" class="form-control" id="role" value="ผู้ดูแลระบบ" readonly>
                        <?php else: ?>
                            <select class="form-control" id="role" name="role" required>
                                <option value="staff"<?= selected_attr($role, 'staff') ?>>เจ้าหน้าที่</option>
                                <option value="admin"<?= selected_attr($role, 'admin') ?>>ผู้ดูแลระบบ</option>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="form-group mb-0">
                        <?php if ($editingSelf): ?>
                            <input type="hidden" name="is_active" value="1">
                            <p class="mb-0 text-muted">บัญชีของตนเองต้องเปิดใช้งานอยู่เสมอ</p>
                        <?php else: ?>
                            <input type="hidden" name="is_active" value="0">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" <?= $isActive === 1 ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="is_active">เปิดใช้งาน</label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                    <?php if ($editUser): ?>
                        <a href="users.php" class="btn btn-default">ยกเลิก</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title h5 mb-0">รายชื่อผู้ใช้</h2>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                    <tr>
                        <th>ชื่อผู้ใช้</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>บทบาท</th>
                        <th>สถานะ</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $row): ?>
                        <tr>
                            <td><?= e((string) $row['username']) ?></td>
                            <td><?= e((string) $row['full_name']) ?></td>
                            <td><?= role_badge((string) $row['role']) ?></td>
                            <td><?= active_badge((int) $row['is_active']) ?></td>
                            <td class="text-nowrap text-right">
                                <a class="btn btn-sm btn-outline-primary" href="users.php?id=<?= (int) $row['id'] ?>">แก้ไข</a>
                                <?php if ((int) $row['id'] !== (int) $actor['id']): ?>
                                    <form id="toggle-user-<?= (int) $row['id'] ?>" method="post" action="users.php" class="d-none">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    </form>
                                    <?php if ((int) $row['is_active'] === 1): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-confirm="action" data-form="toggle-user-<?= (int) $row['id'] ?>" data-message="<?= e('ต้องการปิดการใช้งานบัญชี "' . $row['username'] . '" หรือไม่?') ?>">ปิดใช้งาน</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-outline-success" form="toggle-user-<?= (int) $row['id'] ?>">เปิดใช้งาน</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
