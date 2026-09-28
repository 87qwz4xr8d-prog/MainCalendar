<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();

if (post_too_large()) {
    flash('error', 'ไฟล์มีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์รับได้ ตั้ง upload_max_filesize อย่างน้อย 20M และ post_max_size อย่างน้อย 25M');
    redirect('document-form.php');
}

$formError = null;
$id = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') ? post_int('id') : (int) query_string('id', 20);
$existing = null;
if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM documents WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $existing = $stmt->fetch() ?: null;
    if (!$existing && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        flash('error', 'ไม่พบเอกสาร');
        redirect('documents.php');
    }
}

$values = [
    'doc_number' => (string) ($existing['doc_number'] ?? ''),
    'title' => (string) ($existing['title'] ?? ''),
    'category_id' => (string) ($existing['category_id'] ?? ''),
    'description' => (string) ($existing['description'] ?? ''),
    'status' => (string) ($existing['status'] ?? 'active'),
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_login();
    $back = $id > 0 ? 'document-form.php?id=' . $id : 'document-form.php';
    require_csrf($back);
    $values['doc_number'] = post_string('doc_number');
    $values['title'] = post_string('title');
    $values['category_id'] = (string) post_int('category_id');
    $values['description'] = post_string('description');
    $values['status'] = post_string('status');
    $categoryId = (int) $values['category_id'];

    if (!$existing && $id > 0) {
        $formError = 'ไม่พบเอกสารที่ต้องการแก้ไข';
    } elseif ($values['doc_number'] === '' || mb_strlen($values['doc_number']) > 50 || preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._\-\/()]{0,49}$/u', $values['doc_number']) !== 1) {
        $formError = 'เลขที่เอกสารต้องมีความยาว 1-50 ตัวอักษร และใช้ตัวอักษร ตัวเลข หรือเครื่องหมาย / - _ . ()';
    } elseif ($values['title'] === '' || mb_strlen($values['title']) > 255 || preg_match('/[\x00-\x1F\x7F]/', $values['title']) === 1) {
        $formError = 'กรุณาระบุชื่อเอกสารไม่เกิน 255 ตัวอักษร';
    } elseif ($categoryId <= 0) {
        $formError = 'กรุณาเลือกหมวดหมู่';
    } elseif (mb_strlen($values['description']) > 5000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $values['description']) === 1) {
        $formError = 'รายละเอียดต้องไม่เกิน 5000 ตัวอักษร';
    } elseif (!in_array($values['status'], ['active', 'archived'], true)) {
        $formError = 'สถานะไม่ถูกต้อง';
    } else {
        $catStmt = db()->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
        $catStmt->execute([$categoryId]);
        if (!$catStmt->fetch()) {
            $formError = 'หมวดหมู่ไม่ถูกต้อง';
        }
    }

    $file = $_FILES['file'] ?? ['error' => UPLOAD_ERR_NO_FILE];
    if (!is_array($file)) {
        $file = ['error' => UPLOAD_ERR_NO_FILE];
    }
    $inspected = ['ok' => true, 'skipped' => true];
    if ($formError === null) {
        $inspected = inspect_upload($file, $existing === null);
        if (!$inspected['ok']) {
            $formError = (string) ($inspected['error'] ?? 'อัปโหลดไฟล์ไม่สำเร็จ');
        }
    }

    if ($formError === null) {
        $moved = null;
        if (empty($inspected['skipped'])) {
            $moved = move_upload_to_storage($inspected);
            if (!$moved['ok']) {
                $formError = (string) ($moved['error'] ?? 'บันทึกไฟล์ไม่สำเร็จ');
            }
        }
        if ($formError === null && $existing === null && $moved === null) {
            $formError = 'กรุณาเลือกไฟล์';
        }
        if ($formError === null) {
            if ($moved !== null) {
                $storedName = (string) $moved['stored_name'];
                $originalName = (string) $moved['original_name'];
                $fileSize = (int) $moved['size'];
                $mime = (string) $moved['mime'];
            } else {
                $storedName = (string) $existing['stored_name'];
                $originalName = (string) $existing['original_name'];
                $fileSize = (int) $existing['file_size'];
                $mime = (string) $existing['mime_type'];
            }
            $description = $values['description'] !== '' ? $values['description'] : null;
            try {
                if ($existing) {
                    $update = db()->prepare(
                        'UPDATE documents
                         SET doc_number = ?, title = ?, category_id = ?, description = ?, status = ?,
                             stored_name = ?, original_name = ?, file_size = ?, mime_type = ?
                         WHERE id = ?'
                    );
                    $update->execute([
                        $values['doc_number'],
                        $values['title'],
                        $categoryId,
                        $description,
                        $values['status'],
                        $storedName,
                        $originalName,
                        $fileSize,
                        $mime,
                        (int) $existing['id'],
                    ]);
                    if ($moved !== null) {
                        delete_stored_file((string) $existing['stored_name']);
                    }
                    flash('success', 'แก้ไขเอกสารแล้ว');
                } else {
                    $insert = db()->prepare(
                        'INSERT INTO documents
                         (doc_number, title, category_id, description, stored_name, original_name, file_size, mime_type, status, uploaded_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $insert->execute([
                        $values['doc_number'],
                        $values['title'],
                        $categoryId,
                        $description,
                        $storedName,
                        $originalName,
                        $fileSize,
                        $mime,
                        $values['status'],
                        (int) $user['id'],
                    ]);
                    flash('success', 'อัปโหลดเอกสารแล้ว');
                }
                redirect('documents.php');
            } catch (Throwable $e) {
                if ($moved !== null) {
                    delete_stored_file((string) $moved['stored_name']);
                }
                if (is_duplicate_key($e)) {
                    $formError = 'เลขที่เอกสารนี้มีอยู่แล้ว';
                } elseif ($e instanceof PDOException && (string) ($e->errorInfo[0] ?? '') === '23000') {
                    $formError = 'บันทึกเอกสารไม่สำเร็จ กรุณาตรวจสอบหมวดหมู่';
                } else {
                    throw $e;
                }
            }
        }
    }
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
if (!$categories && $formError === null) {
    $formError = 'ยังไม่มีหมวดหมู่ กรุณาสร้างหมวดหมู่ก่อนอัปโหลดเอกสาร';
}

$pageTitle = $existing ? 'แก้ไขเอกสาร' : 'อัปโหลดเอกสาร';
$activeNav = 'documents';
require __DIR__ . '/../includes/header.php';
?>
<div class="card card-primary">
    <div class="card-header">
        <h2 class="card-title h5 mb-0"><?= e($pageTitle) ?></h2>
    </div>
    <form method="post" action="document-form.php<?= $existing ? '?id=' . (int) $existing['id'] : '' ?>" enctype="multipart/form-data">
        <div class="card-body">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= $existing ? (int) $existing['id'] : 0 ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_BYTES ?>">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label class="required" for="doc_number">เลขที่เอกสาร</label>
                    <input type="text" class="form-control" id="doc_number" name="doc_number" maxlength="50" required value="<?= e($values['doc_number']) ?>">
                </div>
                <div class="form-group col-md-8">
                    <label class="required" for="title">ชื่อเอกสาร</label>
                    <input type="text" class="form-control" id="title" name="title" maxlength="255" required value="<?= e($values['title']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label class="required" for="category_id">หมวดหมู่</label>
                    <select class="form-control" id="category_id" name="category_id" required>
                        <option value="">เลือกหมวดหมู่</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"<?= selected_attr($values['category_id'], (string) $category['id']) ?>><?= e((string) $category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label class="required" for="status">สถานะ</label>
                    <select class="form-control" id="status" name="status" required>
                        <option value="active"<?= selected_attr($values['status'], 'active') ?>>ใช้งาน</option>
                        <option value="archived"<?= selected_attr($values['status'], 'archived') ?>>จัดเก็บถาวร</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="description">รายละเอียด</label>
                <textarea class="form-control" id="description" name="description" rows="4" maxlength="5000"><?= e($values['description']) ?></textarea>
            </div>
            <div class="form-group mb-0">
                <label class="<?= $existing ? '' : 'required' ?>" for="file">ไฟล์เอกสาร</label>
                <input type="file" class="form-control-file" id="file" name="file" <?= $existing ? '' : 'required' ?> accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg">
                <p class="file-hint text-muted mb-0 mt-2">อนุญาต pdf, doc, docx, xls, xlsx, png, jpg, jpeg ขนาดไม่เกิน 20MB</p>
                <?php if ($existing): ?>
                    <p class="mb-0 mt-2">
                        ไฟล์ปัจจุบัน: <?= e((string) $existing['original_name']) ?>
                        (<?= e(format_filesize((int) $existing['file_size'])) ?>)
                        · อัปโหลดเมื่อ <?= e(format_display_datetime((string) $existing['uploaded_at'])) ?>
                        · <a href="download.php?id=<?= (int) $existing['id'] ?>">ดาวน์โหลด</a>
                    </p>
                    <p class="file-hint text-muted mb-0">เว้นว่างถ้าไม่ต้องการเปลี่ยนไฟล์</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary" <?= $categories ? '' : 'disabled' ?>>บันทึก</button>
            <a href="documents.php" class="btn btn-default">กลับ</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
