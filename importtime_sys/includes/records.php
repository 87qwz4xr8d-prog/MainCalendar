<?php

declare(strict_types=1);

function blank_price_line(): array
{
    return [
        'part_no' => '',
        'part_name' => '',
        'specification' => '',
        'brand' => '',
        'unit_name' => 'ชิ้น',
        'qty' => '',
        'unit_price' => '',
        'line_remark' => '',
    ];
}

function price_request_input_from_post(): array
{
    $partNos = post_list('part_no');
    $count = count($partNos);
    $names = post_list('part_name');
    $specs = post_list('specification');
    $brands = post_list('brand');
    $units = post_list('unit_name');
    $qtys = post_list('qty');
    $prices = post_list('unit_price');
    $remarks = post_list('line_remark');
    $lines = [];
    for ($i = 0; $i < $count; $i++) {
        $lines[] = [
            'part_no' => $partNos[$i] ?? '',
            'part_name' => $names[$i] ?? '',
            'specification' => $specs[$i] ?? '',
            'brand' => $brands[$i] ?? '',
            'unit_name' => $units[$i] ?? '',
            'qty' => $qtys[$i] ?? '',
            'unit_price' => $prices[$i] ?? '',
            'line_remark' => $remarks[$i] ?? '',
        ];
    }
    return [
        'request_date' => post_string('request_date'),
        'requester_name' => post_string('requester_name'),
        'department_name' => post_string('department_name'),
        'machine_name' => post_string('machine_name'),
        'machine_code' => post_string('machine_code'),
        'purpose' => post_string('purpose'),
        'supplier_name' => post_string('supplier_name'),
        'supplier_country' => post_string('supplier_country'),
        'supplier_contact' => post_string('supplier_contact'),
        'currency' => strtoupper(post_string('currency')),
        'incoterm' => strtoupper(post_string('incoterm')),
        'payment_term' => post_string('payment_term'),
        'quote_no' => post_string('quote_no'),
        'quote_date' => post_string('quote_date'),
        'valid_until' => post_string('valid_until'),
        'lead_time' => post_string('lead_time'),
        'freight' => post_string('freight'),
        'exchange_rate' => post_string('exchange_rate'),
        'status' => post_string('status'),
        'remark' => post_string('remark'),
        'lines' => $lines,
    ];
}

function line_is_blank(array $line): bool
{
    foreach (['part_no', 'part_name', 'specification', 'brand', 'qty', 'unit_price', 'line_remark'] as $key) {
        if (trim((string) ($line[$key] ?? '')) !== '') {
            return false;
        }
    }
    $unit = trim((string) ($line['unit_name'] ?? ''));
    return $unit === '' || $unit === 'ชิ้น';
}

function text_error(string $value, int $max, string $label, bool $required): ?string
{
    if ($value === '') {
        return $required ? 'กรุณาระบุ' . $label : null;
    }
    if (mb_strlen($value) > $max) {
        return $label . 'ต้องไม่เกิน ' . $max . ' ตัวอักษร';
    }
    if (has_bad_chars($value)) {
        return $label . 'มีอักขระที่ใช้งานไม่ได้';
    }
    return null;
}

function decimal_error(string $value, int $scale, string $label, bool $required, bool $allowZero, int $intDigits = 10): ?string
{
    if ($value === '') {
        return $required ? 'กรุณาระบุ' . $label : null;
    }
    $pattern = '/^\d{1,' . $intDigits . '}(\.\d{1,' . $scale . '})?$/';
    if (preg_match($pattern, $value) !== 1) {
        return $label . 'ต้องเป็นตัวเลขไม่ติดลบ และทศนิยมไม่เกิน ' . $scale . ' ตำแหน่ง';
    }
    if (!$allowZero && money_cmp($value, '0', $scale) !== 1) {
        return $label . 'ต้องมากกว่า 0';
    }
    return null;
}

function validate_price_request(array $input): array
{
    $linesIn = is_array($input['lines'] ?? null) ? $input['lines'] : [];
    $display = [];
    foreach ($linesIn as $line) {
        if (!is_array($line) || line_is_blank($line)) {
            continue;
        }
        $display[] = [
            'part_no' => trim((string) ($line['part_no'] ?? '')),
            'part_name' => trim((string) ($line['part_name'] ?? '')),
            'specification' => trim((string) ($line['specification'] ?? '')),
            'brand' => trim((string) ($line['brand'] ?? '')),
            'unit_name' => trim((string) ($line['unit_name'] ?? '')),
            'qty' => trim((string) ($line['qty'] ?? '')),
            'unit_price' => trim((string) ($line['unit_price'] ?? '')),
            'line_remark' => trim((string) ($line['line_remark'] ?? '')),
        ];
    }

    $header = [
        'request_date' => trim((string) ($input['request_date'] ?? '')),
        'requester_name' => trim((string) ($input['requester_name'] ?? '')),
        'department_name' => trim((string) ($input['department_name'] ?? '')),
        'machine_name' => trim((string) ($input['machine_name'] ?? '')),
        'machine_code' => trim((string) ($input['machine_code'] ?? '')),
        'purpose' => trim((string) ($input['purpose'] ?? '')),
        'supplier_name' => trim((string) ($input['supplier_name'] ?? '')),
        'supplier_country' => trim((string) ($input['supplier_country'] ?? '')),
        'supplier_contact' => trim((string) ($input['supplier_contact'] ?? '')),
        'currency' => strtoupper(trim((string) ($input['currency'] ?? ''))),
        'incoterm' => strtoupper(trim((string) ($input['incoterm'] ?? ''))),
        'payment_term' => trim((string) ($input['payment_term'] ?? '')),
        'quote_no' => trim((string) ($input['quote_no'] ?? '')),
        'quote_date' => trim((string) ($input['quote_date'] ?? '')),
        'valid_until' => trim((string) ($input['valid_until'] ?? '')),
        'lead_time' => trim((string) ($input['lead_time'] ?? '')),
        'freight' => trim((string) ($input['freight'] ?? '')),
        'exchange_rate' => trim((string) ($input['exchange_rate'] ?? '')),
        'status' => trim((string) ($input['status'] ?? '')),
        'remark' => trim((string) ($input['remark'] ?? '')),
    ];

    $fail = static function (string $message) use ($header, $display): array {
        return ['error' => $message, 'header' => $header, 'lines' => $display, 'clean_lines' => []];
    };

    if (!valid_date($header['request_date'])) {
        return $fail('วันที่บันทึกไม่ถูกต้อง');
    }
    foreach ([
        ['requester_name', 150, 'ชื่อผู้ขอ', true],
        ['department_name', 120, 'แผนก', true],
        ['machine_name', 150, 'เครื่องจักรหรืออุปกรณ์', true],
        ['machine_code', 80, 'รหัสเครื่อง', false],
        ['supplier_name', 200, 'ผู้ขาย', true],
        ['supplier_country', 80, 'ประเทศ', false],
        ['supplier_contact', 200, 'ผู้ติดต่อ', false],
        ['payment_term', 150, 'เงื่อนไขชำระเงิน', false],
        ['quote_no', 80, 'เลขที่ใบเสนอราคา', false],
        ['lead_time', 120, 'ระยะเวลาส่งของ', false],
    ] as [$key, $max, $label, $required]) {
        $problem = text_error($header[$key], $max, $label, $required);
        if ($problem !== null) {
            return $fail($problem);
        }
    }
    $purposeError = text_error($header['purpose'], 2000, 'เหตุผลที่ขอซื้อ', true);
    if ($purposeError !== null) {
        return $fail($purposeError);
    }
    $remarkError = text_error($header['remark'], 2000, 'หมายเหตุ', false);
    if ($remarkError !== null) {
        return $fail($remarkError);
    }
    if (!in_array($header['currency'], CURRENCIES, true)) {
        return $fail('กรุณาเลือกสกุลเงิน');
    }
    if ($header['incoterm'] !== '' && !in_array($header['incoterm'], INCOTERMS, true)) {
        return $fail('Incoterm ไม่ถูกต้อง');
    }
    if (!in_array($header['status'], RFQ_OPEN_STATUSES, true)) {
        return $fail('สถานะไม่ถูกต้อง');
    }
    if ($header['quote_date'] !== '' && !valid_date($header['quote_date'])) {
        return $fail('วันที่ใบเสนอราคาไม่ถูกต้อง');
    }
    if ($header['valid_until'] !== '' && !valid_date($header['valid_until'])) {
        return $fail('วันราคาใช้ได้ถึงไม่ถูกต้อง');
    }
    if ($header['quote_date'] !== '' && $header['valid_until'] !== '' && $header['valid_until'] < $header['quote_date']) {
        return $fail('วันราคาใช้ได้ถึงต้องไม่ก่อนวันที่ใบเสนอราคา');
    }
    $freightError = decimal_error($header['freight'] === '' ? '0' : $header['freight'], 2, 'ค่าขนส่ง', true, true);
    if ($freightError !== null) {
        return $fail($freightError);
    }
    $header['freight'] = money_norm($header['freight'] === '' ? '0' : $header['freight'], 2);
    if ($header['exchange_rate'] !== '') {
        $rateError = decimal_error($header['exchange_rate'], 6, 'อัตราแลกเปลี่ยน', true, false, 8);
        if ($rateError !== null) {
            return $fail($rateError);
        }
        $header['exchange_rate'] = money_norm($header['exchange_rate'], 6);
    }
    if ($display === []) {
        return $fail('กรุณาเพิ่มอย่างน้อย 1 รายการพาร์ท');
    }
    if (count($display) > 50) {
        return $fail('บันทึกได้ไม่เกิน 50 รายการต่อใบ');
    }

    $cleanLines = [];
    $quoted = $header['status'] === 'quoted';
    foreach ($display as $index => $line) {
        $n = $index + 1;
        $partError = text_error($line['part_no'], 80, 'รหัสพาร์ทแถวที่ ' . $n, true);
        if ($partError !== null) {
            return $fail($partError);
        }
        if (preg_match('/^[\p{L}\p{N}][\p{L}\p{N} ._\-\/#+%]{0,79}$/u', $line['part_no']) !== 1) {
            return $fail('รหัสพาร์ทแถวที่ ' . $n . ' ใช้ได้เฉพาะตัวอักษร ตัวเลข และเครื่องหมาย - _ . / # + %');
        }
        foreach ([
            ['part_name', 200, 'ชื่อพาร์ท'],
            ['specification', 500, 'สเปก'],
            ['brand', 120, 'ยี่ห้อ'],
            ['line_remark', 255, 'หมายเหตุรายการ'],
        ] as [$key, $max, $label]) {
            $problem = text_error($line[$key], $max, $label . 'แถวที่ ' . $n, $key === 'part_name');
            if ($problem !== null) {
                return $fail($problem);
            }
        }
        $unit = $line['unit_name'] !== '' ? $line['unit_name'] : 'ชิ้น';
        $unitError = text_error($unit, 30, 'หน่วยแถวที่ ' . $n, true);
        if ($unitError !== null) {
            return $fail($unitError);
        }
        $qtyError = decimal_error($line['qty'], 2, 'จำนวนแถวที่ ' . $n, true, false);
        if ($qtyError !== null) {
            return $fail($qtyError);
        }
        $priceRequired = $quoted || $line['unit_price'] !== '';
        $priceError = decimal_error($line['unit_price'], 4, 'ราคาต่อหน่วยแถวที่ ' . $n, $priceRequired, true);
        if ($priceError !== null) {
            return $fail($priceError);
        }
        $cleanLines[] = [
            'part_no' => $line['part_no'],
            'part_name' => $line['part_name'],
            'specification' => $line['specification'],
            'brand' => $line['brand'],
            'unit_name' => $unit,
            'qty' => money_norm($line['qty'], 2),
            'unit_price' => $line['unit_price'] === '' ? null : money_norm($line['unit_price'], 4),
            'line_remark' => $line['line_remark'],
        ];
        $display[$index]['unit_name'] = $unit;
    }

    if ($quoted && $header['quote_no'] === '') {
        return $fail('เมื่อได้ราคาแล้ว กรุณาระบุเลขที่ใบเสนอราคา');
    }
    if ($quoted && $header['quote_date'] === '') {
        return $fail('เมื่อได้ราคาแล้ว กรุณาระบุวันที่ใบเสนอราคา');
    }

    return ['error' => null, 'header' => $header, 'lines' => $display, 'clean_lines' => $cleanLines];
}

function next_doc_no(PDO $pdo, string $kind): string
{
    $map = [
        'rfq' => ['price_requests', 'request_no', 'RFQ'],
        'pr' => ['purchase_requests', 'pr_no', 'PR'],
    ];
    if (!isset($map[$kind])) {
        throw new InvalidArgumentException('ชนิดเอกสารไม่ถูกต้อง');
    }
    [$table, $column, $prefix] = $map[$kind];
    $year = (int) date('Y') + 543;
    $head = $prefix . '-' . $year . '-';
    $stmt = $pdo->prepare("SELECT {$column} AS n FROM {$table} WHERE {$column} LIKE ? ORDER BY {$column} DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$head . '%']);
    $row = $stmt->fetch();
    $seq = 1;
    if ($row && preg_match('/^' . preg_quote($head, '/') . '(\d+)$/', (string) $row['n'], $m)) {
        $seq = (int) $m[1] + 1;
    }
    if ($seq > 9999) {
        throw new RuntimeException('เลขที่เอกสารของปีนี้เต็มแล้ว');
    }
    return $head . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
}

function remember_supplier(PDO $pdo, string $name, string $country, string $contact): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO suppliers (name, country, contact) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE country = VALUES(country), contact = VALUES(contact)'
    );
    $stmt->execute([$name, $country, $contact]);
}

function active_purchase_request(PDO $pdo, int $priceRequestId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT id, pr_no, status FROM purchase_requests
         WHERE price_request_id = ? AND status <> 'cancelled'
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$priceRequestId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function save_price_request(PDO $pdo, ?int $id, array $header, array $lines, int $userId): int
{
    $pdo->beginTransaction();
    try {
        if ($id !== null && $id > 0) {
            $lock = $pdo->prepare('SELECT id, status FROM price_requests WHERE id = ? LIMIT 1 FOR UPDATE');
            $lock->execute([$id]);
            $existing = $lock->fetch();
            if (!$existing) {
                throw new RuntimeException('ไม่พบใบขอราคาที่ต้องการแก้ไข');
            }
            if ((string) $existing['status'] === 'cancelled') {
                throw new RuntimeException('ใบขอราคานี้ถูกยกเลิกแล้ว');
            }
            if (active_purchase_request($pdo, $id) !== null) {
                throw new RuntimeException('มีใบขอซื้อที่ยังไม่ยกเลิกจากใบนี้แล้ว ยกเลิกใบขอซื้อก่อนแก้ไขราคา');
            }
            $update = $pdo->prepare(
                'UPDATE price_requests SET
                    request_date = ?, requester_name = ?, department_name = ?, machine_name = ?, machine_code = ?,
                    purpose = ?, supplier_name = ?, supplier_country = ?, supplier_contact = ?, currency = ?,
                    incoterm = ?, payment_term = ?, quote_no = ?, quote_date = ?, valid_until = ?, lead_time = ?,
                    freight = ?, exchange_rate = ?, status = ?, remark = ?
                 WHERE id = ?'
            );
            $update->execute([
                $header['request_date'],
                $header['requester_name'],
                $header['department_name'],
                $header['machine_name'],
                $header['machine_code'],
                $header['purpose'],
                $header['supplier_name'],
                $header['supplier_country'],
                $header['supplier_contact'],
                $header['currency'],
                $header['incoterm'],
                $header['payment_term'],
                $header['quote_no'],
                $header['quote_date'] !== '' ? $header['quote_date'] : null,
                $header['valid_until'] !== '' ? $header['valid_until'] : null,
                $header['lead_time'],
                $header['freight'],
                $header['exchange_rate'] !== '' ? $header['exchange_rate'] : null,
                $header['status'],
                $header['remark'] !== '' ? $header['remark'] : null,
                $id,
            ]);
            $pdo->prepare('DELETE FROM price_request_items WHERE price_request_id = ?')->execute([$id]);
            $requestId = $id;
        } else {
            $requestNo = next_doc_no($pdo, 'rfq');
            $insert = $pdo->prepare(
                'INSERT INTO price_requests (
                    request_no, request_date, requester_name, department_name, machine_name, machine_code,
                    purpose, supplier_name, supplier_country, supplier_contact, currency, incoterm, payment_term,
                    quote_no, quote_date, valid_until, lead_time, freight, exchange_rate, status, remark, created_by
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                $requestNo,
                $header['request_date'],
                $header['requester_name'],
                $header['department_name'],
                $header['machine_name'],
                $header['machine_code'],
                $header['purpose'],
                $header['supplier_name'],
                $header['supplier_country'],
                $header['supplier_contact'],
                $header['currency'],
                $header['incoterm'],
                $header['payment_term'],
                $header['quote_no'],
                $header['quote_date'] !== '' ? $header['quote_date'] : null,
                $header['valid_until'] !== '' ? $header['valid_until'] : null,
                $header['lead_time'],
                $header['freight'],
                $header['exchange_rate'] !== '' ? $header['exchange_rate'] : null,
                $header['status'],
                $header['remark'] !== '' ? $header['remark'] : null,
                $userId,
            ]);
            $requestId = (int) $pdo->lastInsertId();
        }

        $item = $pdo->prepare(
            'INSERT INTO price_request_items
             (price_request_id, line_no, part_no, part_name, specification, brand, unit_name, qty, unit_price, line_remark)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($lines as $index => $line) {
            $item->execute([
                $requestId,
                $index + 1,
                $line['part_no'],
                $line['part_name'],
                $line['specification'],
                $line['brand'],
                $line['unit_name'],
                $line['qty'],
                $line['unit_price'],
                $line['line_remark'],
            ]);
        }
        remember_supplier($pdo, $header['supplier_name'], $header['supplier_country'], $header['supplier_contact']);
        $pdo->commit();
        return $requestId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function load_price_request(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM price_requests WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $header = $stmt->fetch();
    if (!$header) {
        return null;
    }
    $items = $pdo->prepare('SELECT * FROM price_request_items WHERE price_request_id = ? ORDER BY line_no, id');
    $items->execute([$id]);
    $header['items'] = $items->fetchAll();
    $header['active_pr'] = active_purchase_request($pdo, $id);
    return $header;
}

function cancel_price_request(PDO $pdo, int $id): void
{
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id, status FROM price_requests WHERE id = ? LIMIT 1 FOR UPDATE');
        $lock->execute([$id]);
        $row = $lock->fetch();
        if (!$row) {
            throw new RuntimeException('ไม่พบใบขอราคา');
        }
        if (active_purchase_request($pdo, $id) !== null) {
            throw new RuntimeException('ยกเลิกใบขอซื้อที่อ้างอิงใบนี้ก่อน');
        }
        $pdo->prepare("UPDATE price_requests SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function delete_price_request(PDO $pdo, int $id): void
{
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id, status FROM price_requests WHERE id = ? LIMIT 1 FOR UPDATE');
        $lock->execute([$id]);
        $row = $lock->fetch();
        if (!$row) {
            throw new RuntimeException('ไม่พบใบขอราคา');
        }
        if ((string) $row['status'] !== 'draft') {
            throw new RuntimeException('ลบได้เฉพาะใบที่ยังเป็นร่าง');
        }
        $used = $pdo->prepare('SELECT id FROM purchase_requests WHERE price_request_id = ? LIMIT 1');
        $used->execute([$id]);
        if ($used->fetch()) {
            throw new RuntimeException('ใบนี้ถูกใช้เขียนใบขอซื้อแล้ว จึงลบไม่ได้');
        }
        $pdo->prepare('DELETE FROM price_requests WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function validate_pr_extra(array $input): array
{
    $extra = [
        'pr_date' => trim((string) ($input['pr_date'] ?? '')),
        'needed_date' => trim((string) ($input['needed_date'] ?? '')),
        'deliver_to' => trim((string) ($input['deliver_to'] ?? '')),
        'reason' => trim((string) ($input['reason'] ?? '')),
    ];
    if (!valid_date($extra['pr_date'])) {
        return ['error' => 'วันที่ใบขอซื้อไม่ถูกต้อง', 'extra' => $extra];
    }
    if ($extra['needed_date'] !== '' && !valid_date($extra['needed_date'])) {
        return ['error' => 'วันที่ต้องการของไม่ถูกต้อง', 'extra' => $extra];
    }
    $place = text_error($extra['deliver_to'], 200, 'สถานที่ส่งมอบ', false);
    if ($place !== null) {
        return ['error' => $place, 'extra' => $extra];
    }
    $reason = text_error($extra['reason'], 2000, 'เหตุผลการขอซื้อ', true);
    if ($reason !== null) {
        return ['error' => $reason, 'extra' => $extra];
    }
    return ['error' => null, 'extra' => $extra];
}

function create_purchase_request(PDO $pdo, int $priceRequestId, int $userId, array $extra): int
{
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT * FROM price_requests WHERE id = ? LIMIT 1 FOR UPDATE');
        $lock->execute([$priceRequestId]);
        $rfq = $lock->fetch();
        if (!$rfq) {
            throw new RuntimeException('ไม่พบใบขอราคา');
        }
        if ((string) $rfq['status'] !== 'quoted') {
            throw new RuntimeException('เขียน PR ได้เมื่อใบขอราคาอยู่ในสถานะได้ราคาแล้ว');
        }
        if (active_purchase_request($pdo, $priceRequestId) !== null) {
            throw new RuntimeException('ใบขอราคานี้มีใบขอซื้อที่ยังไม่ยกเลิกอยู่แล้ว');
        }
        $items = $pdo->prepare('SELECT * FROM price_request_items WHERE price_request_id = ? ORDER BY line_no, id');
        $items->execute([$priceRequestId]);
        $lines = $items->fetchAll();
        if ($lines === []) {
            throw new RuntimeException('ใบขอราคายังไม่มีรายการพาร์ท');
        }
        foreach ($lines as $line) {
            if ($line['unit_price'] === null || $line['unit_price'] === '') {
                throw new RuntimeException('ยังมีรายการที่ไม่มีราคา ไม่สามารถเขียน PR ได้');
            }
        }
        $prNo = next_doc_no($pdo, 'pr');
        $insert = $pdo->prepare(
            'INSERT INTO purchase_requests (
                pr_no, pr_date, price_request_id, request_no, requester_name, department_name,
                machine_name, machine_code, supplier_name, supplier_country, supplier_contact,
                currency, incoterm, payment_term, quote_no, quote_date, valid_until, lead_time,
                freight, exchange_rate, needed_date, deliver_to, reason, remark, status, created_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $prNo,
            $extra['pr_date'],
            $priceRequestId,
            $rfq['request_no'],
            $rfq['requester_name'],
            $rfq['department_name'],
            $rfq['machine_name'],
            $rfq['machine_code'],
            $rfq['supplier_name'],
            $rfq['supplier_country'],
            $rfq['supplier_contact'],
            $rfq['currency'],
            $rfq['incoterm'],
            $rfq['payment_term'],
            $rfq['quote_no'],
            $rfq['quote_date'],
            $rfq['valid_until'],
            $rfq['lead_time'],
            $rfq['freight'],
            $rfq['exchange_rate'],
            $extra['needed_date'] !== '' ? $extra['needed_date'] : null,
            $extra['deliver_to'],
            $extra['reason'],
            null,
            'draft',
            $userId,
        ]);
        $prId = (int) $pdo->lastInsertId();
        $item = $pdo->prepare(
            'INSERT INTO purchase_request_items
             (purchase_request_id, line_no, part_no, part_name, specification, brand, unit_name, qty, quoted_qty, unit_price, line_remark)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($lines as $index => $line) {
            $item->execute([
                $prId,
                $index + 1,
                $line['part_no'],
                $line['part_name'],
                $line['specification'],
                $line['brand'],
                $line['unit_name'],
                $line['qty'],
                $line['qty'],
                $line['unit_price'],
                $line['line_remark'],
            ]);
        }
        $pdo->commit();
        return $prId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function load_purchase_request(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM purchase_requests WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $header = $stmt->fetch();
    if (!$header) {
        return null;
    }
    $items = $pdo->prepare('SELECT * FROM purchase_request_items WHERE purchase_request_id = ? ORDER BY line_no, id');
    $items->execute([$id]);
    $header['items'] = $items->fetchAll();
    return $header;
}

function save_purchase_request(PDO $pdo, int $id, array $fields, array $qtyByItem): void
{
    $checked = validate_pr_extra([
        'pr_date' => $fields['pr_date'] ?? '',
        'needed_date' => $fields['needed_date'] ?? '',
        'deliver_to' => $fields['deliver_to'] ?? '',
        'reason' => $fields['reason'] ?? '',
    ]);
    if ($checked['error'] !== null) {
        throw new RuntimeException((string) $checked['error']);
    }
    $remark = trim((string) ($fields['remark'] ?? ''));
    $remarkError = text_error($remark, 2000, 'หมายเหตุ', false);
    if ($remarkError !== null) {
        throw new RuntimeException($remarkError);
    }
    $extra = $checked['extra'];

    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id, status FROM purchase_requests WHERE id = ? LIMIT 1 FOR UPDATE');
        $lock->execute([$id]);
        $header = $lock->fetch();
        if (!$header) {
            throw new RuntimeException('ไม่พบใบขอซื้อ');
        }
        if ((string) $header['status'] !== 'draft') {
            throw new RuntimeException('แก้ไขได้เฉพาะใบขอซื้อที่ยังเป็นร่าง');
        }
        $items = $pdo->prepare('SELECT id, quoted_qty FROM purchase_request_items WHERE purchase_request_id = ? ORDER BY line_no, id');
        $items->execute([$id]);
        $rows = $items->fetchAll();
        if (count($rows) !== count($qtyByItem)) {
            throw new RuntimeException('จำนวนรายการไม่ครบ');
        }
        $updateQty = $pdo->prepare('UPDATE purchase_request_items SET qty = ? WHERE id = ? AND purchase_request_id = ?');
        foreach ($rows as $row) {
            $itemId = (int) $row['id'];
            if (!array_key_exists($itemId, $qtyByItem)) {
                throw new RuntimeException('จำนวนรายการไม่ครบ');
            }
            $qty = trim((string) $qtyByItem[$itemId]);
            $qtyError = decimal_error($qty, 2, 'จำนวนที่ขอซื้อ', true, false);
            if ($qtyError !== null) {
                throw new RuntimeException($qtyError);
            }
            $qty = money_norm($qty, 2);
            if (money_cmp($qty, (string) $row['quoted_qty'], 2) === 1) {
                throw new RuntimeException('จำนวนที่ขอซื้อต้องไม่เกินจำนวนในใบเสนอราคา');
            }
            $updateQty->execute([$qty, $itemId, $id]);
        }
        $update = $pdo->prepare(
            'UPDATE purchase_requests
             SET pr_date = ?, needed_date = ?, deliver_to = ?, reason = ?, remark = ?
             WHERE id = ?'
        );
        $update->execute([
            $extra['pr_date'],
            $extra['needed_date'] !== '' ? $extra['needed_date'] : null,
            $extra['deliver_to'],
            $extra['reason'],
            $remark !== '' ? $remark : null,
            $id,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function set_purchase_request_status(PDO $pdo, int $id, string $status): void
{
    if (!in_array($status, ['issued', 'cancelled'], true)) {
        throw new RuntimeException('สถานะไม่ถูกต้อง');
    }
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id, status FROM purchase_requests WHERE id = ? LIMIT 1 FOR UPDATE');
        $lock->execute([$id]);
        $header = $lock->fetch();
        if (!$header) {
            throw new RuntimeException('ไม่พบใบขอซื้อ');
        }
        $current = (string) $header['status'];
        $allowed = $status === 'issued'
            ? $current === 'draft'
            : in_array($current, ['draft', 'issued'], true);
        if (!$allowed) {
            throw new RuntimeException('เปลี่ยนสถานะรายการนี้ไม่ได้');
        }
        $pdo->prepare('UPDATE purchase_requests SET status = ? WHERE id = ?')->execute([$status, $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function delete_purchase_request(PDO $pdo, int $id): void
{
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare('SELECT id, status FROM purchase_requests WHERE id = ? LIMIT 1 FOR UPDATE');
        $lock->execute([$id]);
        $header = $lock->fetch();
        if (!$header) {
            throw new RuntimeException('ไม่พบใบขอซื้อ');
        }
        if ((string) $header['status'] !== 'draft') {
            throw new RuntimeException('ลบได้เฉพาะใบขอซื้อที่ยังเป็นร่าง');
        }
        $pdo->prepare('DELETE FROM purchase_requests WHERE id = ?')->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function search_price_requests(PDO $pdo, string $keyword, string $status, int $page): array
{
    $where = [];
    $params = [];
    if ($keyword !== '') {
        $like = like_contains($keyword);
        $where[] = '(r.request_no LIKE ? ESCAPE \'\\\\\'
            OR r.supplier_name LIKE ? ESCAPE \'\\\\\'
            OR r.machine_name LIKE ? ESCAPE \'\\\\\'
            OR r.machine_code LIKE ? ESCAPE \'\\\\\'
            OR r.requester_name LIKE ? ESCAPE \'\\\\\'
            OR EXISTS (
                SELECT 1 FROM price_request_items i
                WHERE i.price_request_id = r.id
                  AND (i.part_no LIKE ? ESCAPE \'\\\\\' OR i.part_name LIKE ? ESCAPE \'\\\\\')
            ))';
        $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
    }
    if (in_array($status, ['draft', 'requested', 'quoted', 'cancelled'], true)) {
        $where[] = 'r.status = ?';
        $params[] = $status;
    } else {
        $status = '';
    }
    $sqlWhere = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*) FROM price_requests r' . $sqlWhere);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / PER_PAGE));
    $page = min(max(1, $page), $pages);
    $offset = ($page - 1) * PER_PAGE;
    $sql = 'SELECT r.*,
            (SELECT COUNT(*) FROM price_request_items i WHERE i.price_request_id = r.id) AS item_count,
            (SELECT COUNT(*) FROM price_request_items i WHERE i.price_request_id = r.id AND i.unit_price IS NULL) AS unpriced_count,
            (SELECT COALESCE(SUM(ROUND(i.qty * i.unit_price, 2)), 0) FROM price_request_items i WHERE i.price_request_id = r.id) AS subtotal,
            (SELECT p.id FROM purchase_requests p WHERE p.price_request_id = r.id AND p.status <> \'cancelled\' ORDER BY p.id DESC LIMIT 1) AS active_pr_id,
            (SELECT p.pr_no FROM purchase_requests p WHERE p.price_request_id = r.id AND p.status <> \'cancelled\' ORDER BY p.id DESC LIMIT 1) AS active_pr_no
        FROM price_requests r' . $sqlWhere . ' ORDER BY r.request_date DESC, r.id DESC LIMIT ' . PER_PAGE . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return [
        'rows' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'status' => $status,
    ];
}

function search_purchase_requests(PDO $pdo, string $keyword, string $status, int $page): array
{
    $where = [];
    $params = [];
    if ($keyword !== '') {
        $like = like_contains($keyword);
        $where[] = '(p.pr_no LIKE ? ESCAPE \'\\\\\'
            OR p.request_no LIKE ? ESCAPE \'\\\\\'
            OR p.supplier_name LIKE ? ESCAPE \'\\\\\'
            OR p.machine_name LIKE ? ESCAPE \'\\\\\'
            OR p.requester_name LIKE ? ESCAPE \'\\\\\'
            OR EXISTS (
                SELECT 1 FROM purchase_request_items i
                WHERE i.purchase_request_id = p.id
                  AND (i.part_no LIKE ? ESCAPE \'\\\\\' OR i.part_name LIKE ? ESCAPE \'\\\\\')
            ))';
        $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
    }
    if (in_array($status, ['draft', 'issued', 'cancelled'], true)) {
        $where[] = 'p.status = ?';
        $params[] = $status;
    } else {
        $status = '';
    }
    $sqlWhere = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $count = $pdo->prepare('SELECT COUNT(*) FROM purchase_requests p' . $sqlWhere);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / PER_PAGE));
    $page = min(max(1, $page), $pages);
    $offset = ($page - 1) * PER_PAGE;
    $sql = 'SELECT p.*,
            (SELECT COUNT(*) FROM purchase_request_items i WHERE i.purchase_request_id = p.id) AS item_count,
            (SELECT COALESCE(SUM(ROUND(i.qty * i.unit_price, 2)), 0) FROM purchase_request_items i WHERE i.purchase_request_id = p.id) AS subtotal
        FROM purchase_requests p' . $sqlWhere . ' ORDER BY p.pr_date DESC, p.id DESC LIMIT ' . PER_PAGE . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return [
        'rows' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'status' => $status,
    ];
}

function supplier_options(PDO $pdo): array
{
    return $pdo->query('SELECT id, name, country, contact FROM suppliers ORDER BY name')->fetchAll();
}

function document_totals(array $header, array $lines): array
{
    $subtotal = lines_subtotal($lines);
    $freight = money_norm((string) ($header['freight'] ?? '0'), 2);
    $grand = $subtotal === null ? null : grand_total($subtotal, $freight);
    $rate = $header['exchange_rate'] ?? null;
    $rate = $rate === '' ? null : $rate;
    $thb = $grand === null ? null : thb_amount($grand, $rate === null ? null : (string) $rate, (string) $header['currency']);
    return [
        'subtotal' => $subtotal,
        'freight' => $freight,
        'grand' => $grand,
        'thb' => $thb,
    ];
}
