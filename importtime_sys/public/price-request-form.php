<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$user = require_login();
$pdo = db();

$id = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') ? post_int('id') : (int) query_string('id', 20);
$existing = $id > 0 ? load_price_request($pdo, $id) : null;
if ($id > 0 && !$existing && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash('error', 'ไม่พบใบขอราคา');
    redirect('price-requests.php');
}
if ($existing && ($existing['active_pr'] !== null || (string) $existing['status'] === 'cancelled') && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash('error', 'ใบนี้แก้ไขไม่ได้ เพราะถูกยกเลิกหรือมีใบขอซื้อที่ยังใช้งานอยู่');
    redirect('price-request.php?id=' . (int) $existing['id']);
}

$formError = null;
$header = [
    'request_date' => date('Y-m-d'),
    'requester_name' => (string) $user['full_name'],
    'department_name' => (string) $user['department_name'],
    'machine_name' => '',
    'machine_code' => '',
    'purpose' => '',
    'supplier_name' => '',
    'supplier_country' => '',
    'supplier_contact' => '',
    'currency' => 'USD',
    'incoterm' => 'FOB',
    'payment_term' => '',
    'quote_no' => '',
    'quote_date' => '',
    'valid_until' => '',
    'lead_time' => '',
    'freight' => '',
    'exchange_rate' => '',
    'status' => 'draft',
    'remark' => '',
];
$lines = [blank_price_line(), blank_price_line(), blank_price_line()];

if ($existing) {
    foreach ($header as $key => $unused) {
        $value = $existing[$key] ?? '';
        $header[$key] = $value === null ? '' : (string) $value;
    }
    $header['freight'] = money_norm((string) $existing['freight'], 2);
    $header['exchange_rate'] = $existing['exchange_rate'] === null ? '' : money_norm((string) $existing['exchange_rate'], 6);
    $lines = [];
    foreach ($existing['items'] as $item) {
        $lines[] = [
            'part_no' => (string) $item['part_no'],
            'part_name' => (string) $item['part_name'],
            'specification' => (string) $item['specification'],
            'brand' => (string) $item['brand'],
            'unit_name' => (string) $item['unit_name'],
            'qty' => money_norm((string) $item['qty'], 2),
            'unit_price' => $item['unit_price'] === null ? '' : money_norm((string) $item['unit_price'], 4),
            'line_remark' => (string) $item['line_remark'],
        ];
    }
    if ($lines === []) {
        $lines = [blank_price_line()];
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $back = $existing ? 'price-request-form.php?id=' . (int) $existing['id'] : 'price-request-form.php';
    require_csrf($back);
    $checked = validate_price_request(price_request_input_from_post());
    $header = $checked['header'];
    $lines = $checked['lines'] !== [] ? $checked['lines'] : [blank_price_line()];
    if ($checked['error'] !== null) {
        $formError = (string) $checked['error'];
    } elseif ($id > 0 && !$existing) {
        $formError = 'ไม่พบใบขอราคาที่ต้องการแก้ไข';
    } else {
        try {
            $savedId = save_price_request($pdo, $existing ? (int) $existing['id'] : null, $header, $checked['clean_lines'], (int) $user['id']);
            flash('success', $existing ? 'แก้ไขใบขอราคาแล้ว' : 'บันทึกใบขอราคาแล้ว');
            redirect('price-request.php?id=' . $savedId);
        } catch (RuntimeException $e) {
            $formError = $e->getMessage();
        } catch (PDOException $e) {
            if (!is_duplicate_key($e)) {
                throw $e;
            }
            $formError = 'เลขที่เอกสารซ้ำ กรุณาบันทึกอีกครั้ง';
        }
    }
}

$suppliers = supplier_options($pdo);
$pageTitle = $existing ? 'แก้ไขใบขอราคา' : 'บันทึกใบขอราคา';
$activeNav = 'rfq';
$pageScripts = ['price-form.js'];
require __DIR__ . '/../includes/header.php';

$renderLine = static function (array $line): void {
    ?>
    <div class="line-row">
        <div class="line-no"></div>
        <div><label>รหัสพาร์ท<input name="part_no[]" maxlength="80" value="<?= e((string) $line['part_no']) ?>"></label></div>
        <div><label>ชื่อพาร์ท<input name="part_name[]" maxlength="200" value="<?= e((string) $line['part_name']) ?>"></label></div>
        <div><label>สเปก<input name="specification[]" maxlength="500" value="<?= e((string) $line['specification']) ?>"></label></div>
        <div><label>ยี่ห้อ<input name="brand[]" maxlength="120" value="<?= e((string) $line['brand']) ?>"></label></div>
        <div><label>หน่วย<input name="unit_name[]" maxlength="30" value="<?= e((string) ($line['unit_name'] !== '' ? $line['unit_name'] : 'ชิ้น')) ?>"></label></div>
        <div><label>จำนวน<input name="qty[]" inputmode="decimal" value="<?= e((string) $line['qty']) ?>"></label></div>
        <div><label>ราคา/หน่วย<input name="unit_price[]" inputmode="decimal" value="<?= e((string) $line['unit_price']) ?>"></label></div>
        <div class="line-amount">-</div>
        <div><label>หมายเหตุ<input name="line_remark[]" maxlength="255" value="<?= e((string) $line['line_remark']) ?>"></label></div>
        <button class="btn btn-line btn-small remove-line" type="button">ลบ</button>
    </div>
    <?php
};
?>
<div class="page-head">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p class="lead"><?= $existing ? e((string) $existing['request_no']) : 'เลขที่ใบจะถูกสร้างให้อัตโนมัติเมื่อบันทึก' ?></p>
    </div>
    <a class="btn btn-line" href="<?= $existing ? 'price-request.php?id=' . (int) $existing['id'] : 'price-requests.php' ?>">กลับ</a>
</div>
<form method="post" action="price-request-form.php<?= $existing ? '?id=' . (int) $existing['id'] : '' ?>">
    <?= csrf_input() ?>
    <input type="hidden" name="id" value="<?= $existing ? (int) $existing['id'] : 0 ?>">
    <section class="card">
        <h2>ข้อมูลการขอ</h2>
        <div class="form-grid" style="margin-top:12px">
            <div>
                <label class="req" for="request_date">วันที่บันทึก</label>
                <input id="request_date" name="request_date" type="date" required value="<?= e($header['request_date']) ?>">
            </div>
            <div>
                <label class="req" for="requester_name">ผู้ขอ</label>
                <input id="requester_name" name="requester_name" maxlength="150" required value="<?= e($header['requester_name']) ?>">
            </div>
            <div>
                <label class="req" for="department_name">แผนก</label>
                <input id="department_name" name="department_name" maxlength="120" required value="<?= e($header['department_name']) ?>">
            </div>
            <div>
                <label class="req" for="status">สถานะราคา</label>
                <select id="status" name="status">
                    <?php foreach (RFQ_OPEN_STATUSES as $item): ?>
                        <option value="<?= e($item) ?>"<?= selected_attr($header['status'], $item) ?>><?= e(rfq_status_label($item)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="req" for="machine_name">เครื่องจักร / อุปกรณ์</label>
                <input id="machine_name" name="machine_name" maxlength="150" required value="<?= e($header['machine_name']) ?>">
            </div>
            <div>
                <label for="machine_code">รหัสเครื่อง</label>
                <input id="machine_code" name="machine_code" maxlength="80" value="<?= e($header['machine_code']) ?>">
            </div>
            <div class="span-4">
                <label class="req" for="purpose">เหตุผลที่ขอซื้อ</label>
                <textarea id="purpose" name="purpose" maxlength="2000" required><?= e($header['purpose']) ?></textarea>
            </div>
        </div>
    </section>
    <section class="card">
        <h2>ผู้ขายและเงื่อนไขราคา</h2>
        <div class="terms" style="margin-top:12px">
            <div class="span-2">
                <label for="supplier_pick">เลือกผู้ขายที่เคยบันทึก</label>
                <select id="supplier_pick">
                    <option value="">กรอกผู้ขายใหม่ หรือเลือกจากรายการเดิม</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?= (int) $supplier['id'] ?>" data-name="<?= e((string) $supplier['name']) ?>" data-country="<?= e((string) $supplier['country']) ?>" data-contact="<?= e((string) $supplier['contact']) ?>"><?= e((string) $supplier['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="span-2">
                <label class="req" for="supplier_name">ผู้ขายต่างประเทศ</label>
                <input id="supplier_name" name="supplier_name" maxlength="200" required value="<?= e($header['supplier_name']) ?>">
            </div>
            <div>
                <label for="supplier_country">ประเทศ</label>
                <input id="supplier_country" name="supplier_country" maxlength="80" value="<?= e($header['supplier_country']) ?>">
            </div>
            <div>
                <label for="supplier_contact">ผู้ติดต่อ / อีเมล</label>
                <input id="supplier_contact" name="supplier_contact" maxlength="200" value="<?= e($header['supplier_contact']) ?>">
            </div>
            <div>
                <label class="req" for="currency">สกุลเงิน</label>
                <select id="currency" name="currency">
                    <?php foreach (CURRENCIES as $currency): ?>
                        <option value="<?= e($currency) ?>"<?= selected_attr($header['currency'], $currency) ?>><?= e($currency) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="incoterm">Incoterm</label>
                <select id="incoterm" name="incoterm">
                    <option value="">ไม่ระบุ</option>
                    <?php foreach (INCOTERMS as $term): ?>
                        <option value="<?= e($term) ?>"<?= selected_attr($header['incoterm'], $term) ?>><?= e($term) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="payment_term">เงื่อนไขชำระเงิน</label>
                <input id="payment_term" name="payment_term" maxlength="150" value="<?= e($header['payment_term']) ?>">
            </div>
            <div>
                <label for="lead_time">ระยะเวลาส่งของ</label>
                <input id="lead_time" name="lead_time" maxlength="120" value="<?= e($header['lead_time']) ?>" placeholder="เช่น 6-8 สัปดาห์">
            </div>
            <div>
                <label for="quote_no">เลขที่ใบเสนอราคา</label>
                <input id="quote_no" name="quote_no" maxlength="80" value="<?= e($header['quote_no']) ?>">
            </div>
            <div>
                <label for="quote_date">วันที่ใบเสนอราคา</label>
                <input id="quote_date" name="quote_date" type="date" value="<?= e($header['quote_date']) ?>">
            </div>
            <div>
                <label for="valid_until">ราคาใช้ได้ถึง</label>
                <input id="valid_until" name="valid_until" type="date" value="<?= e($header['valid_until']) ?>">
            </div>
            <div>
                <label for="freight">ค่าขนส่ง (สกุลเดียวกับพาร์ท)</label>
                <input id="freight" name="freight" inputmode="decimal" value="<?= e($header['freight']) ?>">
            </div>
            <div>
                <label for="exchange_rate">อัตราแลกเปลี่ยน (บาทต่อ 1 หน่วย)</label>
                <input id="exchange_rate" name="exchange_rate" inputmode="decimal" value="<?= e($header['exchange_rate']) ?>">
            </div>
            <div class="span-4">
                <label for="remark">หมายเหตุ</label>
                <textarea id="remark" name="remark" maxlength="2000"><?= e($header['remark']) ?></textarea>
            </div>
        </div>
    </section>
    <section class="card">
        <div class="toolbar">
            <h2>รายการพาร์ท</h2>
            <button class="btn btn-line" id="add-line" type="button">เพิ่มรายการ</button>
        </div>
        <p class="note">ถ้ายืนยันสถานะเป็นได้ราคาแล้ว ต้องใส่ราคาทุกรายการ พร้อมเลขที่และวันที่ใบเสนอราคา จึงจะเขียน PR ได้</p>
        <div class="lines-head" aria-hidden="true">
            <span></span><span>รหัสพาร์ท</span><span>ชื่อพาร์ท</span><span>สเปก</span><span>ยี่ห้อ</span><span>หน่วย</span><span>จำนวน</span><span>ราคา/หน่วย</span><span>จำนวนเงิน</span><span>หมายเหตุ</span><span></span>
        </div>
        <div id="lines">
            <?php foreach ($lines as $line) {
                $renderLine($line);
            } ?>
        </div>
        <template id="line-template">
            <?php $renderLine(blank_price_line()); ?>
        </template>
        <div class="totals">
            <div><span>รวมค่ารายการ</span><strong id="preview-subtotal">-</strong></div>
            <div class="grand"><span>รวมทั้งสิ้นรวมค่าขนส่ง</span><strong id="preview-grand">-</strong></div>
            <div><span>ประมาณการบาท</span><strong id="preview-thb">-</strong></div>
        </div>
    </section>
    <div class="toolbar" style="margin-top:16px">
        <button class="btn" type="submit">บันทึกใบขอราคา</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
