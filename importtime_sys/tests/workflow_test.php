<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$failures = 0;

function check(bool $ok, string $label): void
{
    global $failures;
    if ($ok) {
        echo "ok  $label\n";
        return;
    }
    $failures++;
    echo "FAIL $label\n";
}

function sample_request(array $overrides = [], array $lineOverrides = []): array
{
    $line = array_merge([
        'part_no' => '6205-2RS',
        'part_name' => 'ลูกปืน',
        'specification' => 'ขนาด 25x52x15',
        'brand' => 'SKF',
        'unit_name' => 'ชิ้น',
        'qty' => '2',
        'unit_price' => '12.50',
        'line_remark' => '',
    ], $lineOverrides);
    return array_merge([
        'request_date' => date('Y-m-d'),
        'requester_name' => 'ทดสอบ ระบบ',
        'department_name' => 'ซ่อมบำรุง',
        'machine_name' => 'สายพานลำเลียง',
        'machine_code' => 'TEST-WF',
        'purpose' => 'อะไหล่สำรองเครื่องจักร',
        'supplier_name' => 'Test Supplier WF',
        'supplier_country' => 'Japan',
        'supplier_contact' => 'buyer@example.jp',
        'currency' => 'USD',
        'incoterm' => 'FOB',
        'payment_term' => 'T/T 30 วัน',
        'quote_no' => 'Q-100',
        'quote_date' => date('Y-m-d'),
        'valid_until' => date('Y-m-d', strtotime('+30 days')),
        'lead_time' => '6 สัปดาห์',
        'freight' => '20',
        'exchange_rate' => '35.5',
        'status' => 'quoted',
        'remark' => '',
        'lines' => [$line, [
            'part_no' => 'AB_100%',
            'part_name' => 'ซีล',
            'specification' => '',
            'brand' => '',
            'unit_name' => 'ชิ้น',
            'qty' => '3',
            'unit_price' => '1.225',
            'line_remark' => 'MOQ',
        ]],
    ], $overrides);
}

check(money_mul('3', '1.225') === '3.68', 'ปัดเศษครึ่งขึ้น');
check(money_mul('1.25', '10.5') === '13.13', 'คูณจำนวนกับราคา');
check(money_mul('1', '0.005') === '0.01', 'ปัด 0.005 เป็นสตางค์');
check(thb_amount('45.00', '35.5', 'USD') === '1597.50', 'ประมาณการบาท');
check(thb_amount('45.00', null, 'THB') === '45.00', 'สกุลบาทไม่ต้องใช้อัตรา');

$missingPrice = validate_price_request(sample_request(['status' => 'quoted'], ['unit_price' => '']));
check($missingPrice['error'] !== null, 'สถานะได้ราคาแล้วต้องมีราคาทุกรายการ');

$draft = validate_price_request(sample_request([
    'status' => 'draft',
    'quote_no' => '',
    'quote_date' => '',
    'lines' => [[
        'part_no' => 'ABC-1',
        'part_name' => 'พาร์ททดสอบ',
        'specification' => '',
        'brand' => '',
        'unit_name' => 'ชิ้น',
        'qty' => '1',
        'unit_price' => '',
        'line_remark' => '',
    ], blank_price_line()],
]));
check($draft['error'] === null && count($draft['clean_lines']) === 1, 'แถวว่างไม่ถูกบันทึก และร่างยังไม่ต้องมีราคา');

$badPart = validate_price_request(sample_request([], ['part_no' => '-BAD']));
check($badPart['error'] !== null, 'รหัสพาร์ทที่ขึ้นต้นด้วยขีดไม่ผ่าน');

$valid = validate_price_request(sample_request());
check($valid['error'] === null, 'ใบขอราคาที่ข้อมูลครบผ่านการตรวจ');
if ($valid['error'] === null) {
    $totals = document_totals($valid['header'], $valid['clean_lines']);
    check($totals['subtotal'] === '28.68', 'รวมรายการ 25.00 + 3.68');
    check($totals['grand'] === '48.68', 'รวมค่าขนส่ง');
    check($totals['thb'] === '1728.14', 'รวมบาท');
}

$pdo = db();
$userId = (int) $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
$created = [];
try {
    $saved = save_price_request($pdo, null, $valid['header'], $valid['clean_lines'], $userId);
    $created[] = $saved;
    $loaded = load_price_request($pdo, $saved);
    check($loaded !== null && str_starts_with((string) $loaded['request_no'], 'RFQ-'), 'สร้างเลขที่ใบขอราคา');
    check(count($loaded['items']) === 2, 'บันทึกรายการพาร์ทครบ');

    $found = search_price_requests($pdo, 'AB_100%', '', 1);
    $ids = array_map(static fn (array $row): int => (int) $row['id'], $found['rows']);
    check(in_array($saved, $ids, true), 'ค้นด้วยรหัสพาร์ทที่มี _ และ %');

    $prId = create_purchase_request($pdo, $saved, $userId, [
        'pr_date' => date('Y-m-d'),
        'needed_date' => date('Y-m-d', strtotime('+45 days')),
        'deliver_to' => 'คลังอะไหล่',
        'reason' => 'อะไหล่สำรองเครื่องจักร',
    ]);
    $pr = load_purchase_request($pdo, $prId);
    check($pr !== null && str_starts_with((string) $pr['pr_no'], 'PR-'), 'สร้างใบขอซื้อจากใบขอราคา');
    check((string) $pr['items'][1]['part_no'] === 'AB_100%', 'คัดลอกรหัสพาร์ทไปใบขอซื้อ');
    $blocked = false;
    try {
        create_purchase_request($pdo, $saved, $userId, [
            'pr_date' => date('Y-m-d'),
            'needed_date' => '',
            'deliver_to' => '',
            'reason' => 'ซ้ำ',
        ]);
    } catch (RuntimeException) {
        $blocked = true;
    }
    check($blocked, 'เขียน PR ซ้ำไม่ได้ขณะใบเดิมยังไม่ยกเลิก');

    $qty = [];
    foreach ($pr['items'] as $item) {
        $qty[(int) $item['id']] = (int) $item['line_no'] === 1 ? '1.00' : (string) $item['qty'];
    }
    save_purchase_request($pdo, $prId, [
        'pr_date' => (string) $pr['pr_date'],
        'needed_date' => (string) $pr['needed_date'],
        'deliver_to' => (string) $pr['deliver_to'],
        'reason' => (string) $pr['reason'],
        'remark' => 'ลดจำนวนตามสต็อก',
    ], $qty);
    $edited = load_purchase_request($pdo, $prId);
    $firstAmount = money_mul((string) $edited['items'][0]['qty'], (string) $edited['items'][0]['unit_price']);
    check($firstAmount === '12.50', 'แก้จำนวนใน PR แล้วคำนวณเงินใหม่');

    $tooMany = false;
    try {
        $qty[(int) $edited['items'][0]['id']] = '9';
        save_purchase_request($pdo, $prId, [
            'pr_date' => (string) $edited['pr_date'],
            'needed_date' => '',
            'deliver_to' => '',
            'reason' => (string) $edited['reason'],
            'remark' => '',
        ], $qty);
    } catch (RuntimeException) {
        $tooMany = true;
    }
    check($tooMany, 'จำนวนขอซื้อต้องไม่เกินจำนวนในใบเสนอราคา');

    set_purchase_request_status($pdo, $prId, 'issued');
    $locked = false;
    try {
        save_purchase_request($pdo, $prId, [
            'pr_date' => (string) $edited['pr_date'],
            'needed_date' => '',
            'deliver_to' => '',
            'reason' => (string) $edited['reason'],
            'remark' => '',
        ], $qty);
    } catch (RuntimeException) {
        $locked = true;
    }
    check($locked, 'ใบที่ออกแล้วแก้ไขไม่ได้');

    set_purchase_request_status($pdo, $prId, 'cancelled');
    $again = create_purchase_request($pdo, $saved, $userId, [
        'pr_date' => date('Y-m-d'),
        'needed_date' => '',
        'deliver_to' => 'คลังอะไหล่',
        'reason' => 'เขียนใหม่หลังยกเลิก',
    ]);
    check($again !== $prId, 'ยกเลิกแล้วเขียน PR ใหม่ได้');
    delete_purchase_request($pdo, $again);
    check(load_purchase_request($pdo, $again) === null, 'ลบ PR ฉบับร่างได้');
} catch (Throwable $e) {
    $failures++;
    echo 'FAIL ข้อยกเว้นระหว่างทดสอบ: ' . $e->getMessage() . "\n";
} finally {
    if ($created !== []) {
        $marks = implode(',', array_fill(0, count($created), '?'));
        $pdo->prepare("DELETE FROM purchase_requests WHERE price_request_id IN ($marks)")->execute($created);
        $pdo->prepare("DELETE FROM price_requests WHERE id IN ($marks)")->execute($created);
    }
    $pdo->prepare('DELETE FROM suppliers WHERE name = ?')->execute(['Test Supplier WF']);
}

if ($failures > 0) {
    echo "ไม่ผ่าน $failures รายการ\n";
    exit(1);
}

echo "ผ่านทั้งหมด\n";
