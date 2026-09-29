<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$keyword = query_string('q', 120);
$status = query_string('status', 20);
$pageRaw = query_string('page', 10);
$page = ctype_digit($pageRaw) ? (int) $pageRaw : 1;
$result = search_price_requests(db(), $keyword, $status, $page);
$query = ['q' => $keyword, 'status' => $result['status']];

$pageTitle = 'ใบขอราคาพาร์ทต่างประเทศ';
$activeNav = 'rfq';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>ใบขอราคาพาร์ทต่างประเทศ</h1>
        <p class="lead">ค้นจากเลขที่ใบ รหัสพาร์ท ชื่อพาร์ท เครื่องจักร หรือผู้ขาย</p>
    </div>
    <a class="btn" href="price-request-form.php"><i class="bi bi-plus-lg"></i> บันทึกใบขอราคา</a>
</div>
<form class="card filters" method="get" action="price-requests.php">
    <div>
        <label for="q">คำค้น</label>
        <input id="q" name="q" maxlength="120" value="<?= e($keyword) ?>" placeholder="เช่น 6205-2RS หรือ RFQ-2569">
    </div>
    <div>
        <label for="status">สถานะ</label>
        <select id="status" name="status">
            <option value="">ทั้งหมด</option>
            <?php foreach (['draft', 'requested', 'quoted', 'cancelled'] as $item): ?>
                <option value="<?= e($item) ?>"<?= selected_attr($result['status'], $item) ?>><?= e(rfq_status_label($item)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn" type="submit">ค้นหา</button>
</form>
<section class="card">
    <p class="note">พบ <?= (int) $result['total'] ?> รายการ</p>
    <?php if ($result['rows'] === []): ?>
        <p class="empty">ไม่พบใบขอราคาตามเงื่อนไขนี้</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>เลขที่</th>
                        <th>วันที่</th>
                        <th>เครื่องจักร</th>
                        <th>ผู้ขาย</th>
                        <th>รายการ</th>
                        <th class="num">มูลค่า</th>
                        <th>สถานะ</th>
                        <th>PR</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['rows'] as $row): ?>
                        <?php
                        $priced = (int) $row['unpriced_count'] === 0 && (int) $row['item_count'] > 0;
                        $grand = $priced ? grand_total(money_norm((string) $row['subtotal'], 2), money_norm((string) $row['freight'], 2)) : null;
                        ?>
                        <tr>
                            <td><a href="price-request.php?id=<?= (int) $row['id'] ?>"><?= e((string) $row['request_no']) ?></a></td>
                            <td><?= e(format_date((string) $row['request_date'])) ?></td>
                            <td><?= e((string) $row['machine_name']) ?></td>
                            <td><?= e((string) $row['supplier_name']) ?></td>
                            <td><?= (int) $row['item_count'] ?></td>
                            <td class="num"><?= $grand === null ? 'รอราคา' : e(format_amount($grand) . ' ' . (string) $row['currency']) ?></td>
                            <td><?= rfq_status_badge((string) $row['status']) ?></td>
                            <td>
                                <?php if (!empty($row['active_pr_id'])): ?>
                                    <a href="purchase-request.php?id=<?= (int) $row['active_pr_id'] ?>"><?= e((string) $row['active_pr_no']) ?></a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php render_pagination((int) $result['page'], (int) $result['pages'], 'price-requests.php', $query); ?>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
