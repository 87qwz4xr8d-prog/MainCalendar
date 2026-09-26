<?php
$pageTitle = 'พนักงาน';
$scripts = ['js/admin.js'];
$actorId = (int) $currentUser['id'];
?>
<div class="page-head">
    <div>
        <h1>พนักงาน</h1>
        <p>บัญชีสำหรับเข้าปฏิทินกลาง แต่ละคนแก้ไขได้เฉพาะงานของตนเอง</p>
    </div>
    <button class="btn btn-primary" type="button" id="btnNewUser"><i class="bi bi-plus-lg"></i> เพิ่มพนักงาน</button>
</div>
<div class="panel">
    <div class="p-3 border-bottom">
        <input class="form-control" id="userFilter" type="search" placeholder="ค้นหาชื่อ อีเมล หรือแผนก">
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="userTable">
            <thead>
            <tr>
                <th>ชื่อ</th>
                <th>อีเมล</th>
                <th>แผนก</th>
                <th>บทบาท</th>
                <th>สถานะ</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($users === []): ?>
                <tr><td colspan="6" class="text-muted p-4">ยังไม่มีพนักงาน</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $user): ?>
                <?php
                $payload = [
                    'id' => (int) $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'department_id' => (int) $user['department_id'],
                    'role' => $user['role'],
                    'is_active' => (int) $user['is_active'],
                ];
                $isSelf = (int) $user['id'] === $actorId;
                ?>
                <tr>
                    <td><?= e($user['name']) ?></td>
                    <td><?= e($user['email']) ?></td>
                    <td><span class="swatch me-1" style="background: <?= e($user['department_color']) ?>"></span><?= e($user['department_name']) ?></td>
                    <td><?= $user['role'] === 'admin' ? 'ผู้ดูแล' : 'พนักงาน' ?></td>
                    <td><?= (int) $user['is_active'] === 1 ? 'ใช้งาน' : 'ปิดใช้งาน' ?></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-secondary js-edit-user" type="button" data-user="<?= json_attr($payload) ?>">แก้ไข</button>
                        <form class="d-inline" method="post" action="<?= e(url('users/delete')) ?>" data-confirm="<?= e('ลบพนักงาน ' . $user['name'] . ' หรือไม่') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit" <?= $isSelf ? 'disabled' : '' ?> title="<?= $isSelf ? 'ลบบัญชีของตนเองไม่ได้' : 'ลบพนักงาน' ?>">ลบ</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="userForm" method="post" action="<?= e(url('users/save')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="userId">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="userModalTitle">เพิ่มพนักงาน</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="userName">ชื่อ</label>
                        <input class="form-control" id="userName" name="name" maxlength="120" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="userEmail">อีเมล</label>
                        <input class="form-control" id="userEmail" name="email" type="email" maxlength="190" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="userDepartment">แผนก</label>
                            <select class="form-select" id="userDepartment" name="department_id" required>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= (int) $department['id'] ?>"><?= e($department['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="userRole">บทบาท</label>
                            <select class="form-select" id="userRole" name="role">
                                <option value="employee">พนักงาน</option>
                                <option value="admin">ผู้ดูแล</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-check form-switch my-3">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" id="userActive" name="is_active" value="1" checked>
                        <label class="form-check-label" for="userActive">เปิดใช้งานบัญชี</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="userPassword">รหัสผ่าน</label>
                            <input class="form-control" type="password" id="userPassword" name="password" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="userPasswordConfirm">ยืนยันรหัสผ่าน</label>
                            <input class="form-control" type="password" id="userPasswordConfirm" name="password_confirm" autocomplete="new-password">
                        </div>
                    </div>
                    <p class="text-muted small mt-2 mb-0" id="passwordHint"></p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">ยกเลิก</button>
                    <button class="btn btn-primary" type="submit">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>
