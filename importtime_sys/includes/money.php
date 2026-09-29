<?php

declare(strict_types=1);

function bc_round(string $number, int $scale): string
{
    $negative = str_starts_with($number, '-');
    if ($negative) {
        $number = substr($number, 1);
    }
    if (!preg_match('/^\d+(\.\d+)?$/', $number)) {
        throw new InvalidArgumentException('ตัวเลขไม่ถูกต้อง');
    }
    $increment = $scale === 0 ? '0.5' : '0.' . str_repeat('0', $scale) . '5';
    $rounded = bcadd($number, $increment, $scale);
    return $negative ? '-' . $rounded : $rounded;
}

function money_norm(string $number, int $scale): string
{
    return bcadd($number, '0', $scale);
}

function money_mul(string $qty, string $price): string
{
    return bc_round(bcmul($qty, $price, 8), 2);
}

function money_add(string $left, string $right): string
{
    return bcadd($left, $right, 2);
}

function money_cmp(string $left, string $right, int $scale = 2): int
{
    return bccomp($left, $right, $scale);
}

function lines_subtotal(array $lines): ?string
{
    $sum = '0.00';
    foreach ($lines as $line) {
        $price = $line['unit_price'] ?? null;
        if ($price === null || $price === '') {
            return null;
        }
        $sum = money_add($sum, money_mul((string) $line['qty'], (string) $price));
    }
    return $sum;
}

function grand_total(string $subtotal, string $freight): string
{
    return money_add($subtotal, $freight);
}

function thb_amount(string $foreign, ?string $rate, string $currency): ?string
{
    if ($currency === 'THB') {
        return money_norm($foreign, 2);
    }
    if ($rate === null || $rate === '' || money_cmp($rate, '0', 6) !== 1) {
        return null;
    }
    return bc_round(bcmul($foreign, $rate, 8), 2);
}

function format_qty(string $number): string
{
    return number_format((float) $number, 2);
}

function format_price(?string $number): string
{
    if ($number === null || $number === '') {
        return '-';
    }
    return number_format((float) $number, 4);
}

function format_amount(?string $number): string
{
    if ($number === null || $number === '') {
        return '-';
    }
    return number_format((float) $number, 2);
}

function format_rate(?string $number): string
{
    if ($number === null || $number === '') {
        return '-';
    }
    return rtrim(rtrim(number_format((float) $number, 6, '.', ''), '0'), '.');
}
