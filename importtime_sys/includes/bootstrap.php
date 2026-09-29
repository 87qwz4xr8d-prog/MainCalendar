<?php

declare(strict_types=1);

const APP_NAME = 'ขอราคาพาร์ทต่างประเทศ';
const PER_PAGE = 10;
const DUMMY_PASSWORD_HASH = '$2y$10$dRDB4YADJLKkx493.w9bU.IFWIRjXaEo7A26/OwLUV5faBUU.jlyK';
const CURRENCIES = ['USD', 'EUR', 'JPY', 'CNY', 'GBP', 'THB', 'SGD', 'AUD', 'KRW', 'TWD'];
const INCOTERMS = ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'];
const RFQ_OPEN_STATUSES = ['draft', 'requested', 'quoted'];

define('APP_ROOT', dirname(__DIR__));

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/php-error.log');
date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');

$missing = array_values(array_filter(
    ['pdo_mysql', 'mbstring', 'bcmath', 'json'],
    static fn (string $extension): bool => !extension_loaded($extension)
));
if ($missing !== []) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><title>ยังไม่พร้อมใช้งาน</title>';
    echo '<p style="font-family:sans-serif">เซิร์ฟเวอร์ยังไม่มีส่วนขยาย PHP: ' . htmlspecialchars(implode(', ', $missing), ENT_QUOTES, 'UTF-8') . '</p>';
    exit;
}

require_once __DIR__ . '/money.php';

if (PHP_SAPI !== 'cli') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('IMPORTTIMESESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    set_exception_handler(static function (Throwable $e): void {
        error_log($e->__toString());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        $payload = [
            'icon' => 'error',
            'title' => 'ไม่สำเร็จ',
            'text' => 'เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่',
            'confirmButtonText' => 'ตกลง',
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><title>ไม่สำเร็จ</title>';
        echo '<script src="assets/vendor/sweetalert2/sweetalert2.all.min.js"></script></head><body>';
        echo '<script>Swal.fire(' . $json . ').then(function(){window.location.href="index.php";});</script>';
        echo '</body></html>';
        exit;
    });
}

function render_setup_required(string $message): void
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $safe = e($message);
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>ตั้งค่าระบบ</title></head><body style="font-family:sans-serif;max-width:640px;margin:40px auto">';
    echo '<h1>ตั้งค่าระบบขอราคาพาร์ท</h1><p>' . $safe . '</p>';
    echo '<ol><li>สร้างฐานข้อมูลแล้วนำเข้าไฟล์ database.sql ผ่าน phpMyAdmin</li>';
    echo '<li>คัดลอก config.sample.php เป็น config.php แล้วใส่ค่าเชื่อมต่อฐานข้อมูล</li></ol>';
    echo '<p>รายละเอียดอยู่ใน README</p></body></html>';
    exit;
}

function app_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }
    $path = APP_ROOT . '/config.php';
    if (!is_file($path)) {
        render_setup_required('ยังไม่มีไฟล์ config.php กรุณาคัดลอกจาก config.sample.php แล้วแก้ไขค่าฐานข้อมูล');
    }
    $loaded = require $path;
    if (!is_array($loaded)) {
        render_setup_required('ไฟล์ config.php ต้องคืนค่าเป็นอาเรย์');
    }
    foreach (['db_host', 'db_name', 'db_user', 'db_pass'] as $key) {
        if (!array_key_exists($key, $loaded)) {
            render_setup_required('ไฟล์ config.php ไม่ครบ กรุณาระบุ db_host, db_name, db_user และ db_pass');
        }
    }
    $port = (string) ($loaded['db_port'] ?? '3306');
    if (!preg_match('/^\d{1,5}$/', $port)) {
        render_setup_required('พอร์ตฐานข้อมูลใน config.php ไม่ถูกต้อง');
    }
    $loaded['db_port'] = $port;
    $company = trim((string) ($loaded['company_name'] ?? 'บริษัทของเรา'));
    $loaded['company_name'] = $company !== '' ? $company : 'บริษัทของเรา';
    $config = $loaded;
    return $config;
}

function company_name(): string
{
    return (string) app_config()['company_name'];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $config = app_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['db_host'],
        $config['db_port'],
        $config['db_name']
    );
    try {
        $pdo = new PDO($dsn, (string) $config['db_user'], (string) $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+07:00'");
    } catch (PDOException $e) {
        error_log($e->getMessage());
        render_setup_required('ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาตรวจสอบ config.php และนำเข้า database.sql');
    }
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    if (preg_match('#^[a-z0-9_-]+\.php(?:\?[A-Za-z0-9%&=._+\-]*)?$#', $path) !== 1) {
        $path = 'index.php';
    }
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pull_flash(): ?array
{
    if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function render_alerts(?string $inlineError = null): void
{
    $flash = pull_flash();
    $type = 'error';
    $message = null;
    if ($inlineError !== null && $inlineError !== '') {
        $message = $inlineError;
    } elseif ($flash !== null) {
        $message = (string) ($flash['message'] ?? '');
        $flashType = (string) ($flash['type'] ?? 'error');
        $type = in_array($flashType, ['success', 'error', 'warning'], true) ? $flashType : 'error';
    }
    if ($message === null || $message === '') {
        return;
    }
    $titles = [
        'success' => 'สำเร็จ',
        'error' => 'ไม่สำเร็จ',
        'warning' => 'แจ้งเตือน',
    ];
    $payload = [
        'icon' => $type,
        'title' => $titles[$type],
        'text' => $message,
        'confirmButtonText' => 'ตกลง',
    ];
    echo '<script>Swal.fire(' . json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) . ');</script>';
}

function csrf_token(): string
{
    $token = $_SESSION['csrf_token'] ?? '';
    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
    }
    return $token;
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function require_csrf(string $redirectTo): void
{
    $sent = $_POST['csrf_token'] ?? '';
    $known = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sent) || !is_string($known) || $known === '' || !hash_equals($known, $sent)) {
        flash('error', 'เซสชันหมดอายุหรือคำขอไม่ถูกต้อง กรุณาลองอีกครั้ง');
        redirect($redirectTo);
    }
}

function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return trim($value);
}

function post_int(string $key): int
{
    $value = $_POST[$key] ?? 0;
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
        return (int) $value;
    }
    return 0;
}

function post_list(string $key): array
{
    $value = $_POST[$key] ?? [];
    if (!is_array($value)) {
        return [];
    }
    $items = [];
    foreach ($value as $item) {
        $items[] = is_string($item) ? trim($item) : '';
    }
    return $items;
}

function query_string(string $key, int $max = 255): string
{
    $value = $_GET[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    if (mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
        $user = null;
        return null;
    }
    $stmt = db()->prepare('SELECT id, username, full_name, department_name, role, is_active FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['is_active'] !== 1) {
        unset($_SESSION['user_id']);
        if ($row && (int) $row['is_active'] !== 1) {
            flash('error', 'บัญชีนี้ถูกปิดการใช้งาน');
        }
        $user = null;
        return null;
    }
    $user = $row;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }
    return $user;
}

function logout_user(): void
{
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] !== '' ? $params['path'] : '/',
        'domain' => $params['domain'],
        'secure' => (bool) $params['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_destroy();
    session_start();
    session_regenerate_id(true);
    flash('success', 'ออกจากระบบแล้ว');
    redirect('login.php');
}

function format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));
    if (!$dt || $dt->format('Y-m-d') !== substr($value, 0, 10)) {
        return '-';
    }
    return $dt->format('d/m/') . ((int) $dt->format('Y') + 543);
}

function format_display_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    try {
        $dt = new DateTimeImmutable($value);
    } catch (Exception) {
        return '-';
    }
    return $dt->format('d/m/') . ((int) $dt->format('Y') + 543) . ' ' . $dt->format('H:i');
}

function valid_date(string $value): bool
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        return false;
    }
    $year = (int) substr($value, 0, 4);
    if ($year < 2000 || $year > 2100) {
        return false;
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $dt !== false && $dt->format('Y-m-d') === $value;
}

function has_bad_chars(string $value): bool
{
    return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1;
}

function like_contains(string $value): string
{
    $clean = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    return '%' . $clean . '%';
}

function selected_attr(string $current, string $expected): string
{
    return $current === $expected ? ' selected' : '';
}

function rfq_status_label(string $status): string
{
    return match ($status) {
        'draft' => 'ร่าง',
        'requested' => 'ขอราคาแล้ว',
        'quoted' => 'ได้ราคาแล้ว',
        'cancelled' => 'ยกเลิก',
        default => $status,
    };
}

function pr_status_label(string $status): string
{
    return match ($status) {
        'draft' => 'ร่าง',
        'issued' => 'ออกใบขอซื้อแล้ว',
        'cancelled' => 'ยกเลิก',
        default => $status,
    };
}

function rfq_status_badge(string $status): string
{
    $class = match ($status) {
        'draft' => 'badge-muted',
        'requested' => 'badge-warn',
        'quoted' => 'badge-ok',
        'cancelled' => 'badge-stop',
        default => 'badge-muted',
    };
    return '<span class="badge ' . $class . '">' . e(rfq_status_label($status)) . '</span>';
}

function pr_status_badge(string $status): string
{
    $class = match ($status) {
        'draft' => 'badge-warn',
        'issued' => 'badge-ok',
        'cancelled' => 'badge-stop',
        default => 'badge-muted',
    };
    return '<span class="badge ' . $class . '">' . e(pr_status_label($status)) . '</span>';
}

function password_problem(string $password): ?string
{
    $length = mb_strlen($password);
    if ($length < 8) {
        return 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    }
    if ($length > 72) {
        return 'รหัสผ่านยาวเกินไป';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'รหัสผ่านต้องมีทั้งตัวอักษรและตัวเลข';
    }
    return null;
}

function is_duplicate_key(Throwable $e): bool
{
    return $e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062;
}

function render_pagination(int $page, int $totalPages, string $basePath, array $query): void
{
    if ($totalPages <= 1) {
        return;
    }
    $link = static function (int $target) use ($basePath, $query): string {
        $query['page'] = (string) $target;
        return $basePath . '?' . http_build_query($query);
    };
    echo '<nav class="pager" aria-label="การแบ่งหน้า">';
    if ($page <= 1) {
        echo '<span class="pager-disabled">ก่อนหน้า</span>';
    } else {
        echo '<a href="' . e($link($page - 1)) . '">ก่อนหน้า</a>';
    }
    echo '<span>หน้า ' . $page . ' / ' . $totalPages . '</span>';
    if ($page >= $totalPages) {
        echo '<span class="pager-disabled">ถัดไป</span>';
    } else {
        echo '<a href="' . e($link($page + 1)) . '">ถัดไป</a>';
    }
    echo '</nav>';
}

require_once __DIR__ . '/records.php';
