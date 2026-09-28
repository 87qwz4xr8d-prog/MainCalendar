<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$formError = null;
$editId = (int) query_string('id', 20);
$name = '';
$description = '';

if (post_too_large()) {
    flash('error', 'ข้อมูลที่ส่งมีขนาดใหญ่เกินไป');
    redirect('categories.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_login();
    require_csrf('categories.php');
    $action = post_string('action');
    if ($action === 'delete') {
        $id = post_int('id');
        $stmt = db()->prepare('SELECT id, name FROM categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        if (!$category) {
            flash('error', 'ไม่พบหมวดหมู่ที่ต้องการลบ');
            redirect('categories.php');
        }
        $countStmt = db()->prepare('SELECT COUNT(*) FROM documents WHERE category_id = ?');
        $countStmt->execute([$id]);
        if ((int) $countStmt->fetchColumn() > 0) {
            flash('error', 'ไม่สามารถลบหมวดหมู่ที่มีเอกสารอยู่ได้');
            redirect('categories.php');
        }
        $delete = db()->prepare('DELETE FROM categories WHERE id = ?');
        $delete->execute([$id]);
        flash('success', 'ลบหมวดหมู่แล้ว');
        redirect('categories.php');
    }

    if ($action !== 'save') {
        flash('error', 'คำขอไม่ถูกต้อง');
        redirect('categories.php');
    }

    $editId = post_int('id');
    $name = post_string('name');
    $description = post_string('description');
    if ($name === '' || mb_strlen($name) > 150 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
        $formError = 'กรุณาระบุชื่อหมวดหมู่ไม่เกิน 150 ตัวอักษร';
    } elseif (mb_strlen($description) > 500 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $description) === 1) {
        $formError = 'คำอธิบายต้องไม่เกิน 500 ตัวอักษร';
    } else {
        $descriptionValue = $description !== '' ? $description : null;
        try {
            if ($editId > 0) {
                $exists = db()->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
                $exists->execute([$editId]);
                if (!$exists->fetch()) {
                    $formError = 'ไม่พบหมวดหมู่ที่ต้องการแก้ไข';
                } else {
                    $update = db()->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');
                    $update->execute([$name, $descriptionValue, $editId]);
                    flash('success', 'แก้ไขหมวดหมู่แล้ว');
                    redirect('categories.php');
                }
            } else {
                $insert = db()->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
                $insert->execute([$name, $descriptionValue]);
                flash('success', 'เพิ่มหมวดหมู่แล้ว');
                redirect('categories.php');
            }
        } catch (Throwable $e) {
            if (is_duplicate_key($e)) {
                $formError = 'ชื่อหมวดหมู่นี้มีอยู่แล้ว';
            } else {
                throw $e;
            }
        }
    }
}

$editCategory = null;
if ($formError === null && $editId > 0 && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $stmt = db()->prepare('SELECT id, name, description FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$editId]);
    $editCategory = $stmt->fetch() ?: null;
    if (!$editCategory) {
        flash('error', 'ไม่พบหมวดหมู่');
        redirect('categories.php');
    }
    $name = (string) $editCategory['name'];
    $description = (string) ($editCategory['description'] ?? '');
}

$categories = db()->query(
    'SELECT c.id, c.name, c.description, c.created_at,
            (SELECT COUNT(*) FROM documents d WHERE d.category_id = c.id) AS doc_count
     FROM categories c
     ORDER BY c.name'
)->fetchAll();

$pageTitle = 'หมวดหมู่';
$activeNav = 'categories';
require __DIR__ . '/../includes/header.php';
?>
<div class="row">
    <div class="col-lg-4">
        <div class="card card-primary">
            <div class="card-header">
                <h2 class="card-title h5 mb-0"><?= $editId > 0 && $formError !== null || $editCategory ? 'แก้ไขหมวดหมู่' : 'เพิ่มหมวดหมู่' ?></h2>
            </div>
            <form method="post" action="categories.php">
                <div class="card-body">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int) $editId ?>">
                    <div class="form-group">
                        <label class="required" for="name">ชื่อหมวดหมู่</label>
                        <input type="text" class="form-control" id="name" name="name" maxlength="150" required value="<?= e($name) ?>">
                    </div>
                    <div class="form-group">
                        <label for="description">คำอธิบาย</label>
                        <textarea class="form-control" id="description" name="description" rows="3" maxlength="500"><?= e($description) ?></textarea>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                    <?php if ($editId > 0): ?>
                        <a href="categories.php" class="btn btn-default">ยกเลิก</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title h5 mb-0">รายการหมวดหมู่</h2>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                    <tr>
                        <th>ชื่อ</th>
                        <th>คำอธิบาย</th>
                        <th class="text-center">เอกสาร</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$categories): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">ยังไม่มีหมวดหมู่</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?= e((string) $category['name']) ?></td>
                                <td><?= e((string) ($category['description'] ?? '')) ?></td>
                                <td class="text-center"><?= (int) $category['doc_count'] ?></td>
                                <td class="text-nowrap text-right">
                                    <a class="btn btn-sm btn-outline-primary" href="categories.php?id=<?= (int) $category['id'] ?>">แก้ไข</a>
                                    <?php if ((int) $category['doc_count'] === 0): ?>
                                        <form id="delete-category-<?= (int) $category['id'] ?>" method="post" action="categories.php" class="d-none">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-confirm="delete" data-form="delete-category-<?= (int) $category['id'] ?>" data-message="<?= e('ต้องการลบหมวดหมู่ "' . $category['name'] . '" หรือไม่?') ?>">ลบ</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
