<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$pdo = db();
$rfqCounts = ['draft' => 0, 'requested' => 0, 'quoted' => 0, 'cancelled' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM price_requests GROUP BY status') as $row) {
    $rfqCounts[(string) $row['status']] = (int) $row['total'];
}
$prCounts = ['draft' => 0, 'issued' => 0, 'cancelled' => 0];
foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM purchase_requests GROUP BY status') as $row) {
    $prCounts[(string) $row['status']] = (int) $row['total'];
}
$waitingPr = (int) $pdo->query(
    "SELECT COUNT(*) FROM price_requests r
     WHERE r.status = 'quoted'
       AND NOT EXISTS (
           SELECT 1 FROM purchase_requests p
           WHERE p.price_request_id = r.id AND p.status <> 'cancelled'
       )"
)->fetchColumn();
$recentRfq = $pdo->query(
    'SELECT id, request_no, request_date, supplier_name, machine_name, currency, status
     FROM price_requests ORDER BY id DESC LIMIT 6'
)->fetchAll();
$recentPr = $pdo->query(
    'SELECT id, pr_no, pr_date, request_no, supplier_name, currency, status
     FROM purchase_requests ORDER BY id DESC LIMIT 6'
)->fetchAll();

$pageTitle = 'แดชบอร์ด';
$activeNav = 'home';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>บันทึกการขอราคาพาร์ทต่างประเทศ</h1>
        <p class="lead">เก็บรหัสพาร์ท ราคา และเงื่อนไขจากผู้ขายต่างประเทศ แล้วดึงไปเขียนใบขอซื้อ</p>
    </div>
    <a class="btn" href="price-request-form.php"><i class="bi bi-plus-lg"></i> บันทึกใบขอราคา</a>
</div>
<section class="stats">
    <article class="card stat"><strong><?= (int) $rfqCounts['requested'] ?></strong><span>อยู่ระหว่างขอราคา</span></article>
    <article class="card stat"><strong><?= $waitingPr ?></strong><span>ได้ราคาแล้ว ยังไม่เขียน PR</span></article>
    <article class="card stat"><strong><?= (int) $prCounts['draft'] ?></strong><span>ใบขอซื้อฉบับร่าง</span></article>
</section>
<section class="card">
    <div class="toolbar">
        <h2>ใบขอราคาล่าสุด</h2>
        <a href="price-requests.php">ดูทั้งหมด</a>
    </div>
    <?php if ($recentRfq === []): ?>
        <p class="empty">ยังไม่มีใบขอราคา เริ่มจากบันทึกรายการพาร์ทที่ต้องการสอบราคา</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>เลขที่</th><th>วันที่</th><th>เครื่องจักร</th><th>ผู้ขาย</th><th>สกุลเงิน</th><th>สถานะ</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentRfq as $row): ?>
                        <tr>
                            <td><a href="price-request.php?id=<?= (int) $row['id'] ?>"><?= e((string) $row['request_no']) ?></a></td>
                            <td><?= e(format_date((string) $row['request_date'])) ?></td>
                            <td><?= e((string) $row['machine_name']) ?></td>
                            <td><?= e((string) $row['supplier_name']) ?></td>
                            <td><?= e((string) $row['currency']) ?></td>
                            <td><?= rfq_status_badge((string) $row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<section class="card">
    <div class="toolbar">
        <h2>ใบขอซื้อล่าสุด</h2>
        <a href="purchase-requests.php">ดูทั้งหมด</a>
    </div>
    <?php if ($recentPr === []): ?>
        <p class="empty">ยังไม่มีใบขอซื้อ เมื่อบันทึกราคาครบแล้ว กดเขียน PR จากหน้ารายละเอียดใบขอราคา</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>เลขที่ PR</th><th>วันที่</th><th>อ้างอิงใบขอราคา</th><th>ผู้ขาย</th><th>สถานะ</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPr as $row): ?>
                        <tr>
                            <td><a href="purchase-request.php?id=<?= (int) $row['id'] ?>"><?= e((string) $row['pr_no']) ?></a></td>
                            <td><?= e(format_date((string) $row['pr_date'])) ?></td>
                            <td><?= e((string) $row['request_no']) ?></td>
                            <td><?= e((string) $row['supplier_name']) ?></td>
                            <td><?= pr_status_badge((string) $row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
