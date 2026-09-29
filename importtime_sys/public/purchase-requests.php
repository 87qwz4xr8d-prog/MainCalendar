<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$keyword = query_string('q', 120);
$status = query_string('status', 20);
$pageRaw = query_string('page', 10);
$page = ctype_digit($pageRaw) ? (int) $pageRaw : 1;
$result = search_purchase_requests(db(), $keyword, $status, $page);
$query = ['q' => $keyword, 'status' => $result['status']];

$pageTitle = 'ใบขอซื้อ';
$activeNav = 'pr';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>ใบขอซื้อ</h1>
        <p class="lead">สร้างจากใบขอราคาที่บันทึกราคาพาร์ทต่างประเทศไว้แล้ว</p>
    </div>
    <a class="btn btn-line" href="price-requests.php?status=quoted">เลือกใบขอราคาที่ได้ราคาแล้ว</a>
</div>
<form class="card filters" method="get" action="purchase-requests.php">
    <div>
        <label for="q">คำค้น</label>
        <input id="q" name="q" maxlength="120" value="<?= e($keyword) ?>" placeholder="เลขที่ PR, รหัสพาร์ท หรือผู้ขาย">
    </div>
    <div>
        <label for="status">สถานะ</label>
        <select id="status" name="status">
            <option value="">ทั้งหมด</option>
            <?php foreach (['draft', 'issued', 'cancelled'] as $item): ?>
                <option value="<?= e($item) ?>"<?= selected_attr($result['status'], $item) ?>><?= e(pr_status_label($item)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn" type="submit">ค้นหา</button>
</form>
<section class="card">
    <p class="note">พบ <?= (int) $result['total'] ?> รายการ</p>
    <?php if ($result['rows'] === []): ?>
        <p class="empty">ยังไม่มีใบขอซื้อ</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>เลขที่ PR</th><th>วันที่</th><th>อ้างอิง</th><th>เครื่องจักร</th><th>ผู้ขาย</th>
                        <th class="num">มูลค่า</th><th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <?php $grand = grand_total(money_norm((string) $row['subtotal'], 2), money_norm((string) $row['freight'], 2)); ?>
                        <tr>
                            <td><a href="purchase-request.php?id=<?= (int) $row['id'] ?>"><?= e((string) $row['pr_no']) ?></a></td>
                            <td><?= e(format_date((string) $row['pr_date'])) ?></td>
                            <td><?= e((string) $row['request_no']) ?></td>
                            <td><?= e((string) $row['machine_name']) ?></td>
                            <td><?= e((string) $row['supplier_name']) ?></td>
                            <td class="num"><?= e(format_amount($grand) . ' ' . (string) $row['currency']) ?></td>
                            <td><?= pr_status_badge((string) $row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php render_pagination((int) $result['page'], (int) $result['pages'], 'purchase-requests.php', $query); ?>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
