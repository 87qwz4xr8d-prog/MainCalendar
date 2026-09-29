<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();
$pdo = db();
$id = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') ? post_int('id') : (int) query_string('id', 20);
$record = $id > 0 ? load_purchase_request($pdo, $id) : null;
if (!$record) {
    flash('error', 'ไม่พบใบขอซื้อ');
    redirect('purchase-requests.php');
}

$formError = null;
$postedQty = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf('purchase-request.php?id=' . (int) $record['id']);
    $action = post_string('action');
    $rawQty = $_POST['qty'] ?? [];
    if (is_array($rawQty)) {
        foreach ($rawQty as $key => $value) {
            if ((is_int($key) || (is_string($key) && ctype_digit($key))) && is_string($value)) {
                $postedQty[(int) $key] = trim($value);
            }
        }
    }
    try {
        if ($action === 'delete') {
            delete_purchase_request($pdo, (int) $record['id']);
            flash('success', 'ลบใบขอซื้อฉบับร่างแล้ว');
            redirect('purchase-requests.php');
        }
        if ($action === 'cancel') {
            set_purchase_request_status($pdo, (int) $record['id'], 'cancelled');
            flash('success', 'ยกเลิกใบขอซื้อแล้ว สามารถกลับไปแก้ใบขอราคาแล้วเขียนใหม่ได้');
            redirect('purchase-request.php?id=' . (int) $record['id']);
        }
        if ($action === 'save' || $action === 'issue') {
            save_purchase_request($pdo, (int) $record['id'], [
                'pr_date' => post_string('pr_date'),
                'needed_date' => post_string('needed_date'),
                'deliver_to' => post_string('deliver_to'),
                'reason' => post_string('reason'),
                'remark' => post_string('remark'),
            ], $postedQty);
            if ($action === 'issue') {
                set_purchase_request_status($pdo, (int) $record['id'], 'issued');
                flash('success', 'ออกใบขอซื้อแล้ว');
            } else {
                flash('success', 'บันทึกใบขอซื้อแล้ว');
            }
            redirect('purchase-request.php?id=' . (int) $record['id']);
        }
        $formError = 'คำขอไม่ถูกต้อง';
    } catch (RuntimeException $e) {
        $formError = $e->getMessage();
        $record['pr_date'] = post_string('pr_date');
        $record['needed_date'] = post_string('needed_date');
        $record['deliver_to'] = post_string('deliver_to');
        $record['reason'] = post_string('reason');
        $record['remark'] = post_string('remark');
    }
    $fresh = load_purchase_request($pdo, (int) $record['id']);
    if ($fresh && $formError === null) {
        $record = $fresh;
    } elseif ($fresh && $postedQty !== []) {
        foreach ($fresh['items'] as $index => $item) {
            $itemId = (int) $item['id'];
            if (isset($postedQty[$itemId]) && preg_match('/^\d+(\.\d+)?$/', $postedQty[$itemId]) === 1) {
                $fresh['items'][$index]['qty'] = $postedQty[$itemId];
            }
        }
        $fresh['pr_date'] = $record['pr_date'];
        $fresh['needed_date'] = $record['needed_date'];
        $fresh['deliver_to'] = $record['deliver_to'];
        $fresh['reason'] = $record['reason'];
        $fresh['remark'] = $record['remark'];
        $record = $fresh;
    }
}

$totals = document_totals($record, $record['items']);
$editable = (string) $record['status'] === 'draft';
$pageTitle = (string) $record['pr_no'];
$activeNav = 'pr';
$pageScripts = ['price-form.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head no-print">
    <div>
        <h1><?= e((string) $record['pr_no']) ?></h1>
        <p class="lead"><?= pr_status_badge((string) $record['status']) ?> อ้างอิงใบขอราคา <a href="price-request.php?id=<?= (int) $record['price_request_id'] ?>"><?= e((string) $record['request_no']) ?></a></p>
    </div>
    <button class="btn btn-line" type="button" onclick="window.print()">พิมพ์ใบขอซื้อ</button>
</div>
<form id="pr-form" method="post" action="purchase-request.php?id=<?= (int) $record['id'] ?>" data-freight="<?= e((string) $record['freight']) ?>" data-rate="<?= e((string) ($record['exchange_rate'] ?? '')) ?>" data-currency="<?= e((string) $record['currency']) ?>">
    <?= csrf_input() ?>
    <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
    <input type="hidden" name="action" value="save" data-action-input>
    <article class="sheet">
        <div class="sheet-top">
            <div>
                <strong><?= e(company_name()) ?></strong>
                <h2 style="margin-top:8px">ใบขอซื้อ</h2>
                <div>Purchase Requisition</div>
            </div>
            <div>
                <div><span class="note">เลขที่</span> <?= e((string) $record['pr_no']) ?></div>
                <div><span class="note">อ้างอิงใบขอราคา</span> <?= e((string) $record['request_no']) ?></div>
                <div><span class="note">ใบเสนอราคา</span> <?= e((string) ($record['quote_no'] !== '' ? $record['quote_no'] : '-')) ?></div>
            </div>
        </div>
        <div class="meta" style="margin-top:16px">
            <div>
                <span>วันที่</span>
                <?php if ($editable): ?>
                    <input name="pr_date" type="date" required value="<?= e((string) $record['pr_date']) ?>">
                <?php else: ?>
                    <?= e(format_date((string) $record['pr_date'])) ?>
                <?php endif; ?>
            </div>
            <div><span>ผู้ขอ</span><?= e((string) $record['requester_name']) ?></div>
            <div><span>แผนก</span><?= e((string) $record['department_name']) ?></div>
            <div>
                <span>วันที่ต้องการของ</span>
                <?php if ($editable): ?>
                    <input name="needed_date" type="date" value="<?= e((string) ($record['needed_date'] ?? '')) ?>">
                <?php else: ?>
                    <?= e(format_date($record['needed_date'] !== null ? (string) $record['needed_date'] : null)) ?>
                <?php endif; ?>
            </div>
            <div class="span-2"><span>เครื่องจักร</span><?= e((string) $record['machine_name']) ?><?= $record['machine_code'] !== '' ? ' (' . e((string) $record['machine_code']) . ')' : '' ?></div>
            <div class="span-2">
                <span>สถานที่ส่งมอบ</span>
                <?php if ($editable): ?>
                    <input name="deliver_to" maxlength="200" value="<?= e((string) $record['deliver_to']) ?>">
                <?php else: ?>
                    <?= e((string) ($record['deliver_to'] !== '' ? $record['deliver_to'] : '-')) ?>
                <?php endif; ?>
            </div>
            <div><span>ผู้ขาย</span><?= e((string) $record['supplier_name']) ?></div>
            <div><span>ประเทศ</span><?= e((string) ($record['supplier_country'] !== '' ? $record['supplier_country'] : '-')) ?></div>
            <div><span>สกุลเงิน</span><?= e((string) $record['currency']) ?><?= $record['incoterm'] !== '' ? ' · ' . e((string) $record['incoterm']) : '' ?></div>
            <div><span>ระยะเวลาส่งของ</span><?= e((string) ($record['lead_time'] !== '' ? $record['lead_time'] : '-')) ?></div>
            <div><span>ราคาใช้ได้ถึง</span><?= e(format_date($record['valid_until'] !== null ? (string) $record['valid_until'] : null)) ?></div>
            <div><span>เงื่อนไขชำระเงิน</span><?= e((string) ($record['payment_term'] !== '' ? $record['payment_term'] : '-')) ?></div>
            <div><span>ผู้ติดต่อ</span><?= e((string) ($record['supplier_contact'] !== '' ? $record['supplier_contact'] : '-')) ?></div>
            <div><span>อัตราแลกเปลี่ยน</span><?= e(format_rate($record['exchange_rate'] !== null ? (string) $record['exchange_rate'] : null)) ?></div>
        </div>
        <div style="margin-top:14px">
            <span class="note">เหตุผลการขอซื้อ</span>
            <?php if ($editable): ?>
                <textarea name="reason" maxlength="2000" required><?= e((string) $record['reason']) ?></textarea>
            <?php else: ?>
                <div><?= e((string) $record['reason']) ?></div>
            <?php endif; ?>
        </div>
        <div class="table-wrap" style="margin-top:16px">
            <table>
                <thead>
                    <tr>
                        <th>ลำดับ</th><th>รหัสพาร์ท</th><th>รายการ</th><th>สเปก / ยี่ห้อ</th><th>หน่วย</th>
                        <th class="num">จำนวนเสนอราคา</th><th class="num">จำนวนขอซื้อ</th><th class="num">ราคา/หน่วย</th><th class="num">จำนวนเงิน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($record['items'] as $item): ?>
                        <?php $amount = money_mul((string) $item['qty'], (string) $item['unit_price']); ?>
                        <tr class="line-row" data-price="<?= e((string) $item['unit_price']) ?>">
                            <td><?= (int) $item['line_no'] ?></td>
                            <td><?= e((string) $item['part_no']) ?></td>
                            <td><?= e((string) $item['part_name']) ?><?= $item['line_remark'] !== '' ? '<div class="note">' . e((string) $item['line_remark']) . '</div>' : '' ?></td>
                            <td><?= e(trim((string) $item['specification'] . ' ' . (string) $item['brand'])) ?></td>
                            <td><?= e((string) $item['unit_name']) ?></td>
                            <td class="num"><?= e(format_qty((string) $item['quoted_qty'])) ?></td>
                            <td class="num">
                                <?php if ($editable): ?>
                                    <input name="qty[<?= (int) $item['id'] ?>]" inputmode="decimal" value="<?= e((string) $item['qty']) ?>">
                                <?php else: ?>
                                    <?= e(format_qty((string) $item['qty'])) ?>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= e(format_price((string) $item['unit_price'])) ?></td>
                            <td class="num line-amount"><?= e(format_amount($amount)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="totals">
            <div><span>รวมค่ารายการ</span><strong id="preview-subtotal"><?= e($totals['subtotal'] === null ? '-' : format_amount($totals['subtotal'])) ?></strong></div>
            <div><span>ค่าขนส่ง</span><strong><?= e(format_amount($totals['freight'])) ?></strong></div>
            <div class="grand"><span>รวมทั้งสิ้น (<?= e((string) $record['currency']) ?>)</span><strong id="preview-grand"><?= e($totals['grand'] === null ? '-' : format_amount($totals['grand'])) ?></strong></div>
            <div><span>ประมาณการบาท</span><strong id="preview-thb"><?= e($totals['thb'] === null ? '-' : format_amount($totals['thb'])) ?></strong></div>
        </div>
        <div style="margin-top:14px">
            <span class="note">หมายเหตุ</span>
            <?php if ($editable): ?>
                <textarea name="remark" maxlength="2000"><?= e((string) ($record['remark'] ?? '')) ?></textarea>
            <?php else: ?>
                <div><?= e((string) (($record['remark'] ?? '') !== '' ? $record['remark'] : '-')) ?></div>
            <?php endif; ?>
        </div>
        <div class="sign-row">
            <div class="sign">ผู้ขอ<br><?= e((string) $record['requester_name']) ?></div>
            <div class="sign">หัวหน้าแผนก</div>
            <div class="sign">ฝ่ายจัดซื้อ</div>
            <div class="sign">ผู้อนุมัติ</div>
        </div>
    </article>
    <?php if ($editable): ?>
        <div class="toolbar no-print" style="margin-top:16px">
            <button class="btn btn-line" type="submit">บันทึกฉบับร่าง</button>
            <button class="btn" type="button" data-confirm="action" data-form="pr-form" data-action="issue" data-message="ออกใบขอซื้อนี้หรือไม่ หลังจากนั้นจะแก้ไขตัวเลขไม่ได้อีก">ออกใบขอซื้อ</button>
        </div>
    <?php endif; ?>
</form>
<?php if ($editable || (string) $record['status'] === 'issued'): ?>
    <div class="toolbar no-print" style="margin-top:12px">
        <?php if ($editable): ?>
            <form id="delete-pr" method="post" action="purchase-request.php?id=<?= (int) $record['id'] ?>">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-stop" type="button" data-confirm="delete" data-form="delete-pr" data-message="ลบใบขอซื้อฉบับร่างนี้หรือไม่">ลบฉบับร่าง</button>
            </form>
        <?php endif; ?>
        <?php if (in_array((string) $record['status'], ['draft', 'issued'], true)): ?>
            <form id="cancel-pr" method="post" action="purchase-request.php?id=<?= (int) $record['id'] ?>">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                <input type="hidden" name="action" value="cancel">
                <button class="btn btn-stop" type="button" data-confirm="action" data-form="cancel-pr" data-message="ยกเลิกใบขอซื้อนี้หรือไม่">ยกเลิกใบขอซื้อ</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
