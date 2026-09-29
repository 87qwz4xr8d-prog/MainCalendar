<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$pdo = db();
$id = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') ? post_int('id') : (int) query_string('id', 20);
$record = $id > 0 ? load_price_request($pdo, $id) : null;
if (!$record) {
    flash('error', 'ไม่พบใบขอราคา');
    redirect('price-requests.php');
}

$formError = null;
$extra = [
    'pr_date' => date('Y-m-d'),
    'needed_date' => '',
    'deliver_to' => '',
    'reason' => (string) $record['purpose'],
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf('price-request.php?id=' . (int) $record['id']);
    $action = post_string('action');
    try {
        if ($action === 'delete') {
            delete_price_request($pdo, (int) $record['id']);
            flash('success', 'ลบใบขอราคาแล้ว');
            redirect('price-requests.php');
        } elseif ($action === 'cancel') {
            cancel_price_request($pdo, (int) $record['id']);
            flash('success', 'ยกเลิกใบขอราคาแล้ว');
            redirect('price-request.php?id=' . (int) $record['id']);
        } elseif ($action === 'create_pr') {
            $checked = validate_pr_extra([
                'pr_date' => post_string('pr_date'),
                'needed_date' => post_string('needed_date'),
                'deliver_to' => post_string('deliver_to'),
                'reason' => post_string('reason'),
            ]);
            $extra = $checked['extra'];
            if ($checked['error'] !== null) {
                $formError = (string) $checked['error'];
            } else {
                $prId = create_purchase_request($pdo, (int) $record['id'], (int) $user['id'], $extra);
                flash('success', 'สร้างใบขอซื้อจากราคาที่บันทึกไว้แล้ว');
                redirect('purchase-request.php?id=' . $prId);
            }
        } else {
            $formError = 'คำขอไม่ถูกต้อง';
        }
    } catch (RuntimeException $e) {
        $formError = $e->getMessage();
    } catch (PDOException $e) {
        if (!is_duplicate_key($e)) {
            throw $e;
        }
        $formError = 'เลขที่เอกสารซ้ำ กรุณาบันทึกอีกครั้ง';
    }
    $record = load_price_request($pdo, (int) $record['id']) ?? $record;
}

$totals = document_totals($record, $record['items']);
$canEdit = $record['active_pr'] === null && (string) $record['status'] !== 'cancelled';
$canCreatePr = (string) $record['status'] === 'quoted' && $record['active_pr'] === null && $totals['subtotal'] !== null;

$pageTitle = (string) $record['request_no'];
$activeNav = 'rfq';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1><?= e((string) $record['request_no']) ?></h1>
        <p class="lead"><?= rfq_status_badge((string) $record['status']) ?> บันทึกเมื่อ <?= e(format_display_datetime((string) $record['created_at'])) ?></p>
    </div>
    <div class="toolbar">
        <?php if ($canEdit): ?>
            <a class="btn btn-line" href="price-request-form.php?id=<?= (int) $record['id'] ?>">แก้ไข</a>
        <?php endif; ?>
        <button class="btn btn-line" type="button" onclick="window.print()">พิมพ์</button>
    </div>
</div>
<?php if ($record['active_pr'] !== null): ?>
    <div class="banner">ใบนี้ถูกใช้เขียนใบขอซื้อ <a href="purchase-request.php?id=<?= (int) $record['active_pr']['id'] ?>"><?= e((string) $record['active_pr']['pr_no']) ?></a> แล้ว ถ้าต้องแก้ราคา ให้ยกเลิกใบขอซื้อก่อน</div>
<?php endif; ?>
<section class="card">
    <div class="meta">
        <div><span>วันที่บันทึก</span><?= e(format_date((string) $record['request_date'])) ?></div>
        <div><span>ผู้ขอ</span><?= e((string) $record['requester_name']) ?></div>
        <div><span>แผนก</span><?= e((string) $record['department_name']) ?></div>
        <div><span>เครื่องจักร</span><?= e((string) $record['machine_name']) ?><?= $record['machine_code'] !== '' ? ' (' . e((string) $record['machine_code']) . ')' : '' ?></div>
        <div><span>ผู้ขาย</span><?= e((string) $record['supplier_name']) ?></div>
        <div><span>ประเทศ</span><?= e((string) ($record['supplier_country'] !== '' ? $record['supplier_country'] : '-')) ?></div>
        <div><span>ผู้ติดต่อ</span><?= e((string) ($record['supplier_contact'] !== '' ? $record['supplier_contact'] : '-')) ?></div>
        <div><span>สกุลเงิน / Incoterm</span><?= e((string) $record['currency']) ?><?= $record['incoterm'] !== '' ? ' · ' . e((string) $record['incoterm']) : '' ?></div>
        <div><span>ใบเสนอราคา</span><?= e((string) ($record['quote_no'] !== '' ? $record['quote_no'] : '-')) ?></div>
        <div><span>วันที่เสนอราคา</span><?= e(format_date($record['quote_date'] !== null ? (string) $record['quote_date'] : null)) ?></div>
        <div><span>ราคาใช้ได้ถึง</span><?= e(format_date($record['valid_until'] !== null ? (string) $record['valid_until'] : null)) ?></div>
        <div><span>ระยะเวลาส่งของ</span><?= e((string) ($record['lead_time'] !== '' ? $record['lead_time'] : '-')) ?></div>
        <div class="span-2"><span>เงื่อนไขชำระเงิน</span><?= e((string) ($record['payment_term'] !== '' ? $record['payment_term'] : '-')) ?></div>
        <div class="span-2"><span>เหตุผลที่ขอซื้อ</span><?= e((string) $record['purpose']) ?></div>
        <?php if ($record['remark'] !== null && $record['remark'] !== ''): ?>
            <div class="span-4"><span>หมายเหตุ</span><?= e((string) $record['remark']) ?></div>
        <?php endif; ?>
    </div>
</section>
<section class="card">
    <h2>รายการพาร์ท</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ลำดับ</th><th>รหัสพาร์ท</th><th>ชื่อ</th><th>สเปก</th><th>ยี่ห้อ</th><th>หน่วย</th>
                    <th class="num">จำนวน</th><th class="num">ราคา/หน่วย</th><th class="num">จำนวนเงิน</th><th>หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($record['items'] as $item): ?>
                    <?php $amount = $item['unit_price'] === null ? null : money_mul((string) $item['qty'], (string) $item['unit_price']); ?>
                    <tr>
                        <td><?= (int) $item['line_no'] ?></td>
                        <td><?= e((string) $item['part_no']) ?></td>
                        <td><?= e((string) $item['part_name']) ?></td>
                        <td><?= e((string) $item['specification']) ?></td>
                        <td><?= e((string) $item['brand']) ?></td>
                        <td><?= e((string) $item['unit_name']) ?></td>
                        <td class="num"><?= e(format_qty((string) $item['qty'])) ?></td>
                        <td class="num"><?= e(format_price($item['unit_price'] === null ? null : (string) $item['unit_price'])) ?></td>
                        <td class="num"><?= e($amount === null ? '-' : format_amount($amount)) ?></td>
                        <td><?= e((string) $item['line_remark']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="totals">
        <div><span>รวมค่ารายการ</span><strong><?= e($totals['subtotal'] === null ? 'รอราคา' : format_amount($totals['subtotal'])) ?></strong></div>
        <div><span>ค่าขนส่ง</span><strong><?= e(format_amount($totals['freight'])) ?></strong></div>
        <div class="grand"><span>รวมทั้งสิ้น (<?= e((string) $record['currency']) ?>)</span><strong><?= e($totals['grand'] === null ? '-' : format_amount($totals['grand'])) ?></strong></div>
        <div><span>อัตราแลกเปลี่ยน</span><strong><?= e(format_rate($record['exchange_rate'] !== null ? (string) $record['exchange_rate'] : null)) ?></strong></div>
        <div><span>ประมาณการบาท</span><strong><?= e($totals['thb'] === null ? '-' : format_amount($totals['thb'])) ?></strong></div>
    </div>
</section>
<?php if ($canCreatePr): ?>
    <section class="card no-print">
        <h2>เขียน PR ขอซื้อ</h2>
        <p class="note">ระบบจะคัดลอกรายการพาร์ท ราคา ผู้ขาย และเงื่อนไขจากใบนี้ไปเป็นใบขอซื้อ แก้จำนวนใน PR ได้ไม่เกินจำนวนที่เสนอราคา</p>
        <form method="post" action="price-request.php?id=<?= (int) $record['id'] ?>">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
            <input type="hidden" name="action" value="create_pr">
            <div class="form-grid" style="margin-top:12px">
                <div>
                    <label class="req" for="pr_date">วันที่ใบขอซื้อ</label>
                    <input id="pr_date" name="pr_date" type="date" required value="<?= e($extra['pr_date']) ?>">
                </div>
                <div>
                    <label for="needed_date">วันที่ต้องการของ</label>
                    <input id="needed_date" name="needed_date" type="date" value="<?= e($extra['needed_date']) ?>">
                </div>
                <div class="span-2">
                    <label for="deliver_to">สถานที่ส่งมอบ</label>
                    <input id="deliver_to" name="deliver_to" maxlength="200" value="<?= e($extra['deliver_to']) ?>">
                </div>
                <div class="span-4">
                    <label class="req" for="reason">เหตุผลการขอซื้อ</label>
                    <textarea id="reason" name="reason" maxlength="2000" required><?= e($extra['reason']) ?></textarea>
                </div>
            </div>
            <button class="btn" type="submit" style="margin-top:12px">สร้างใบขอซื้อ</button>
        </form>
    </section>
<?php elseif ((string) $record['status'] !== 'quoted' && (string) $record['status'] !== 'cancelled' && $record['active_pr'] === null): ?>
    <div class="banner no-print">ถ้าต้องการเขียน PR ให้แก้ใบนี้เป็นสถานะได้ราคาแล้ว และใส่ราคาครบทุกรายการ</div>
<?php endif; ?>
<?php if ($canEdit): ?>
    <div class="toolbar no-print" style="margin-top:16px">
        <?php if ((string) $record['status'] === 'draft'): ?>
            <form id="delete-rfq" method="post" action="price-request.php?id=<?= (int) $record['id'] ?>">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-stop" type="button" data-confirm="delete" data-form="delete-rfq" data-message="ลบใบขอราคาร่างนี้หรือไม่">ลบฉบับร่าง</button>
            </form>
        <?php else: ?>
            <form id="cancel-rfq" method="post" action="price-request.php?id=<?= (int) $record['id'] ?>">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                <input type="hidden" name="action" value="cancel">
                <button class="btn btn-stop" type="button" data-confirm="action" data-form="cancel-rfq" data-message="ยกเลิกใบขอราคานี้หรือไม่">ยกเลิกใบขอราคา</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
