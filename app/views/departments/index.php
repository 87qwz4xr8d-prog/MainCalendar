<?php
$pageTitle = 'แผนก';
$scripts = ['js/admin.js'];
?>
<div class="page-head">
    <div>
        <h1>แผนก</h1>
        <p>สีของแผนกใช้แสดงงานบนปฏิทินกลาง</p>
    </div>
    <button class="btn btn-primary" type="button" id="btnNewDept"><i class="bi bi-plus-lg"></i> เพิ่มแผนก</button>
</div>
<div class="panel">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>สี</th>
                <th>ชื่อแผนก</th>
                <th>คำอธิบาย</th>
                <th>พนักงาน</th>
                <th>งาน</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($departments === []): ?>
                <tr><td colspan="6" class="text-muted p-4">ยังไม่มีแผนก</td></tr>
            <?php endif; ?>
            <?php foreach ($departments as $department): ?>
                <?php
                $payload = [
                    'id' => (int) $department['id'],
                    'name' => $department['name'],
                    'color' => $department['color'],
                    'description' => $department['description'] ?? '',
                ];
                $blocked = (int) $department['user_count'] > 0 || (int) $department['event_count'] > 0;
                ?>
                <tr>
                    <td><span class="swatch" style="background: <?= e($department['color']) ?>"></span></td>
                    <td><?= e($department['name']) ?></td>
                    <td><?= e($department['description'] ?? '') ?></td>
                    <td><?= (int) $department['user_count'] ?></td>
                    <td><?= (int) $department['event_count'] ?></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-secondary js-edit-dept" type="button" data-dept="<?= json_attr($payload) ?>">แก้ไข</button>
                        <form class="d-inline" method="post" action="<?= e(url('departments/delete')) ?>" data-confirm="<?= e('ลบแผนก ' . $department['name'] . ' หรือไม่') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $department['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit" <?= $blocked ? 'disabled' : '' ?> title="<?= $blocked ? 'ยังมีพนักงานหรืองานผูกอยู่' : 'ลบแผนก' ?>">ลบ</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="deptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="deptForm" method="post" action="<?= e(url('departments/save')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="deptId">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="deptModalTitle">เพิ่มแผนก</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="deptName">ชื่อแผนก</label>
                        <input class="form-control" id="deptName" name="name" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="deptColor">สี</label>
                        <input class="form-control form-control-color" id="deptColor" name="color" type="color" value="#1a73e8" required>
                    </div>
                    <div>
                        <label class="form-label" for="deptDescription">คำอธิบาย</label>
                        <input class="form-control" id="deptDescription" name="description" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-light" type="button" data-bs-dismiss="modal">ยกเลิก</button>
                    <button class="btn btn-primary" type="submit">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>
