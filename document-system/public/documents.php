<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$formError = null;

if (post_too_large()) {
    flash('error', 'ข้อมูลที่ส่งมีขนาดใหญ่เกินไป');
    redirect('documents.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_login();
    $parsedReturn = [];
    parse_str(post_string('return_query'), $parsedReturn);
    $returnQuery = whitelist_query($parsedReturn);
    $target = 'documents.php' . ($returnQuery !== '' ? '?' . $returnQuery : '');
    require_csrf($target);
    if (post_string('action') !== 'delete') {
        flash('error', 'คำขอไม่ถูกต้อง');
        redirect($target);
    }
    $id = post_int('id');
    $stmt = db()->prepare('SELECT id, stored_name FROM documents WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $doc = $stmt->fetch();
    if (!$doc) {
        flash('error', 'ไม่พบเอกสารที่ต้องการลบ');
        redirect($target);
    }
    $delete = db()->prepare('DELETE FROM documents WHERE id = ?');
    $delete->execute([$id]);
    delete_stored_file((string) $doc['stored_name']);
    flash('success', 'ลบเอกสารแล้ว');
    redirect($target);
}

$title = query_string('title');
$docNumber = query_string('doc_number');
$categoryId = (int) query_string('category_id', 20);
$dateFrom = query_string('date_from', 10);
$dateTo = query_string('date_to', 10);
$status = query_string('status', 20);

$where = [];
$params = [];
if ($title !== '') {
    $where[] = 'd.title LIKE ?';
    $params[] = like_contains($title);
}
if ($docNumber !== '') {
    $where[] = 'd.doc_number LIKE ?';
    $params[] = like_contains($docNumber);
}
if ($categoryId > 0) {
    $where[] = 'd.category_id = ?';
    $params[] = $categoryId;
}
if (!in_array($status, ['', 'active', 'archived'], true)) {
    $status = '';
}
if ($status !== '') {
    $where[] = 'd.status = ?';
    $params[] = $status;
}
if ($dateFrom !== '' || $dateTo !== '') {
    if ($dateFrom !== '' && !valid_date($dateFrom)) {
        $formError = 'รูปแบบวันที่เริ่มต้นไม่ถูกต้อง';
    } elseif ($dateTo !== '' && !valid_date($dateTo)) {
        $formError = 'รูปแบบวันที่สิ้นสุดไม่ถูกต้อง';
    } elseif ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        $formError = 'วันที่เริ่มต้นต้องไม่เกินวันที่สิ้นสุด';
    } else {
        if ($dateFrom !== '') {
            $where[] = 'DATE(d.uploaded_at) >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 'DATE(d.uploaded_at) <= ?';
            $params[] = $dateTo;
        }
    }
}

$sqlWhere = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
$countStmt = db()->prepare('SELECT COUNT(*) FROM documents d ' . $sqlWhere);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / PER_PAGE));
$page = max(1, (int) query_string('page', 10));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * PER_PAGE;
$listStmt = db()->prepare(
    'SELECT d.id, d.doc_number, d.title, d.status, d.uploaded_at, d.file_size,
            c.name AS category_name, u.full_name AS uploader_name
     FROM documents d
     INNER JOIN categories c ON c.id = d.category_id
     INNER JOIN users u ON u.id = d.uploaded_by
     ' . $sqlWhere . '
     ORDER BY d.uploaded_at DESC, d.id DESC
     LIMIT ' . PER_PAGE . ' OFFSET ' . $offset
);
$listStmt->execute($params);
$documents = $listStmt->fetchAll();
$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$pagerQuery = array_filter([
    'title' => $title,
    'doc_number' => $docNumber,
    'category_id' => $categoryId > 0 ? (string) $categoryId : '',
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'status' => $status,
], static fn (string $value): bool => $value !== '');
$returnQuery = http_build_query($pagerQuery);
$fromRow = $total === 0 ? 0 : $offset + 1;
$toRow = min($total, $offset + count($documents));

$pageTitle = 'เอกสาร';
$activeNav = 'documents';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-header">
        <h2 class="card-title h5 mb-0">ค้นหาและกรอง</h2>
    </div>
    <div class="card-body">
        <form method="get" action="documents.php">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="title">ชื่อเอกสาร</label>
                    <input type="text" class="form-control" id="title" name="title" maxlength="255" value="<?= e($title) ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="doc_number">เลขที่เอกสาร</label>
                    <input type="text" class="form-control" id="doc_number" name="doc_number" maxlength="50" value="<?= e($docNumber) ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="category_id">หมวดหมู่</label>
                    <select class="form-control" id="category_id" name="category_id">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>"<?= selected_attr((string) $categoryId, (string) $category['id']) ?>><?= e((string) $category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="date_from">ตั้งแต่วันที่ (ค.ศ.)</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= e($dateFrom) ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="date_to">ถึงวันที่ (ค.ศ.)</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= e($dateTo) ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="status">สถานะ</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">ทั้งหมด</option>
                        <option value="active"<?= selected_attr($status, 'active') ?>>ใช้งาน</option>
                        <option value="archived"<?= selected_attr($status, 'archived') ?>>จัดเก็บถาวร</option>
                    </select>
                </div>
                <div class="form-group col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">ค้นหา</button>
                    <a href="documents.php" class="btn btn-default">ล้างตัวกรอง</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="card-title h5 mb-0">รายการเอกสาร</h2>
        <a href="document-form.php" class="btn btn-primary btn-sm"><i class="fas fa-upload"></i> อัปโหลดเอกสาร</a>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>เลขที่</th>
                <th>ชื่อเอกสาร</th>
                <th>หมวดหมู่</th>
                <th>สถานะ</th>
                <th>ขนาด</th>
                <th>ผู้อัปโหลด</th>
                <th>วันที่อัปโหลด</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$documents): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">ไม่พบเอกสาร</td>
                </tr>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                    <tr>
                        <td><?= e((string) $doc['doc_number']) ?></td>
                        <td><?= e((string) $doc['title']) ?></td>
                        <td><?= e((string) $doc['category_name']) ?></td>
                        <td><?= status_badge((string) $doc['status']) ?></td>
                        <td><?= e(format_filesize((int) $doc['file_size'])) ?></td>
                        <td><?= e((string) $doc['uploader_name']) ?></td>
                        <td><?= e(format_display_datetime((string) $doc['uploaded_at'])) ?></td>
                        <td class="text-nowrap text-right">
                            <a class="btn btn-sm btn-outline-secondary" href="download.php?id=<?= (int) $doc['id'] ?>">ดาวน์โหลด</a>
                            <a class="btn btn-sm btn-outline-primary" href="document-form.php?id=<?= (int) $doc['id'] ?>">แก้ไข</a>
                            <form id="delete-doc-<?= (int) $doc['id'] ?>" method="post" action="documents.php" class="d-none">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
                                <input type="hidden" name="return_query" value="<?= e($returnQuery) ?>">
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-confirm="delete" data-form="delete-doc-<?= (int) $doc['id'] ?>" data-message="<?= e('ต้องการลบเอกสาร "' . $doc['title'] . '" หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้') ?>">ลบ</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
        <span class="text-muted">แสดง <?= $fromRow ?>–<?= $toRow ?> จาก <?= $total ?> รายการ</span>
        <?php render_pagination($page, $totalPages, 'documents.php', $pagerQuery); ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
