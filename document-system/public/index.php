<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$docCount = (int) db()->query('SELECT COUNT(*) FROM documents')->fetchColumn();
$categoryCount = (int) db()->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$activeCount = (int) db()->query("SELECT COUNT(*) FROM documents WHERE status = 'active'")->fetchColumn();
$recent = db()->query(
    'SELECT d.id, d.doc_number, d.title, d.status, d.uploaded_at, c.name AS category_name, u.full_name AS uploader_name
     FROM documents d
     INNER JOIN categories c ON c.id = d.category_id
     INNER JOIN users u ON u.id = d.uploaded_by
     ORDER BY d.uploaded_at DESC, d.id DESC
     LIMIT 8'
)->fetchAll();

$pageTitle = 'แดชบอร์ด';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="row">
    <div class="col-lg-4 col-md-6">
        <div class="small-box bg-info">
            <div class="inner">
                <h3><?= $docCount ?></h3>
                <p>จำนวนเอกสาร</p>
            </div>
            <div class="icon"><i class="fas fa-file-alt"></i></div>
            <a href="documents.php" class="small-box-footer">ดูเอกสารทั้งหมด <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="small-box bg-success">
            <div class="inner">
                <h3><?= $categoryCount ?></h3>
                <p>จำนวนหมวดหมู่</p>
            </div>
            <div class="icon"><i class="fas fa-folder-open"></i></div>
            <a href="categories.php" class="small-box-footer">จัดการหมวดหมู่ <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-lg-4 col-md-6">
        <div class="small-box bg-primary">
            <div class="inner">
                <h3><?= $activeCount ?></h3>
                <p>เอกสารที่ใช้งาน</p>
            </div>
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <a href="documents.php?status=active" class="small-box-footer">ดูเอกสารที่ใช้งาน <i class="fas fa-arrow-circle-right"></i></a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title h5 mb-0">เอกสารที่อัปโหลดล่าสุด</h2>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>เลขที่เอกสาร</th>
                <th>ชื่อเอกสาร</th>
                <th>หมวดหมู่</th>
                <th>สถานะ</th>
                <th>ผู้อัปโหลด</th>
                <th>วันที่อัปโหลด</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$recent): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">ยังไม่มีเอกสาร</td>
                </tr>
            <?php else: ?>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td><?= e((string) $row['doc_number']) ?></td>
                        <td><?= e((string) $row['title']) ?></td>
                        <td><?= e((string) $row['category_name']) ?></td>
                        <td><?= status_badge((string) $row['status']) ?></td>
                        <td><?= e((string) $row['uploader_name']) ?></td>
                        <td><?= e(format_display_datetime((string) $row['uploaded_at'])) ?></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="download.php?id=<?= (int) $row['id'] ?>">ดาวน์โหลด</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
