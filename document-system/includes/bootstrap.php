<?php

declare(strict_types=1);

const APP_NAME = 'ระบบจัดเก็บเอกสาร';
const MAX_UPLOAD_BYTES = 20971520;
const PER_PAGE = 10;
const DUMMY_PASSWORD_HASH = '$2y$10$JgGMQxxvKeknvlWMkd2truUrwExijeVIxusxnIBcv3I8vkhA8KWEa';
const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];

const ALLOWED_MIMES = [
    'pdf' => ['application/pdf'],
    'png' => ['image/png'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'doc' => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/octet-stream'],
    'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/octet-stream', 'application/vnd.ms-excel.sheet.macroEnabled.12'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
];

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . '/storage/uploads');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/php-error.log');
date_default_timezone_set('Asia/Bangkok');
mb_internal_encoding('UTF-8');

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('DOCARCHIVESESSID');
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

function render_setup_required(string $message): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    $payload = [
        'icon' => 'error',
        'title' => 'ยังตั้งค่าระบบไม่ครบ',
        'text' => $message,
        'confirmButtonText' => 'ตกลง',
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $safe = e($message);
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>ตั้งค่าระบบ</title>';
    echo '<link rel="stylesheet" href="assets/vendor/fonts/sarabun.css">';
    echo '<link rel="stylesheet" href="assets/vendor/adminlte/adminlte.min.css">';
    echo '<script src="assets/vendor/sweetalert2/sweetalert2.all.min.js"></script></head>';
    echo '<body class="hold-transition"><div class="container py-5"><div class="card card-outline card-danger">';
    echo '<div class="card-header"><h1 class="card-title h4 mb-0">ตั้งค่าระบบจัดเก็บเอกสาร</h1></div>';
    echo '<div class="card-body"><p>' . $safe . '</p>';
    echo '<ol><li>สร้างฐานข้อมูลแล้วนำเข้าไฟล์ database.sql ผ่าน phpMyAdmin</li>';
    echo '<li>คัดลอก config.sample.php เป็น config.php แล้วใส่ค่าเชื่อมต่อฐานข้อมูล</li>';
    echo '<li>เปิดสิทธิ์เขียนให้โฟลเดอร์ storage/uploads</li></ol>';
    echo '<p class="mb-0">รายละเอียดอยู่ใน README</p></div></div></div>';
    echo '<script>Swal.fire(' . $json . ');</script></body></html>';
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
    $config = $loaded;
    return $config;
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
    $stmt = db()->prepare('SELECT id, username, full_name, role, is_active FROM users WHERE id = ? LIMIT 1');
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

function require_admin(): array
{
    $user = require_login();
    if (($user['role'] ?? '') !== 'admin') {
        flash('error', 'คุณไม่มีสิทธิ์จัดการผู้ใช้งาน');
        redirect('index.php');
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

function format_display_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
    if (!$dt) {
        try {
            $dt = new DateTimeImmutable($value);
        } catch (Exception) {
            return '-';
        }
    }
    $year = (int) $dt->format('Y') + 543;
    return $dt->format('d/m/') . $year . ' ' . $dt->format('H:i');
}

function format_filesize(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return number_format($bytes / 1048576, 1) . ' MB';
}

function status_badge(string $status): string
{
    if ($status === 'active') {
        return '<span class="badge badge-success">ใช้งาน</span>';
    }
    return '<span class="badge badge-secondary">จัดเก็บถาวร</span>';
}

function role_badge(string $role): string
{
    if ($role === 'admin') {
        return '<span class="badge badge-primary">ผู้ดูแลระบบ</span>';
    }
    return '<span class="badge badge-info">เจ้าหน้าที่</span>';
}

function active_badge(int $active): string
{
    if ($active === 1) {
        return '<span class="badge badge-success">เปิดใช้งาน</span>';
    }
    return '<span class="badge badge-danger">ปิดใช้งาน</span>';
}

function selected_attr(string $current, string $expected): string
{
    return $current === $expected ? ' selected' : '';
}

function valid_date(string $value): bool
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    return $dt !== false && $dt->format('Y-m-d') === $value;
}

function like_contains(string $value): string
{
    $clean = str_replace(['\\', '%', '_'], '', $value);
    return '%' . $clean . '%';
}

function whitelist_query(array $source): string
{
    $allowed = ['title', 'doc_number', 'category_id', 'date_from', 'date_to', 'status', 'page'];
    $clean = [];
    foreach ($allowed as $key) {
        if (!isset($source[$key]) || is_array($source[$key])) {
            continue;
        }
        $clean[$key] = (string) $source[$key];
    }
    return http_build_query($clean);
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
    echo '<nav aria-label="การแบ่งหน้า"><ul class="pagination pagination-sm mb-0">';
    if ($page <= 1) {
        echo '<li class="page-item disabled"><span class="page-link">ก่อนหน้า</span></li>';
    } else {
        echo '<li class="page-item"><a class="page-link" href="' . e($link($page - 1)) . '">ก่อนหน้า</a></li>';
    }
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    if ($start > 1) {
        echo '<li class="page-item"><a class="page-link" href="' . e($link(1)) . '">1</a></li>';
        if ($start > 2) {
            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $page) {
            echo '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . '</span></li>';
        } else {
            echo '<li class="page-item"><a class="page-link" href="' . e($link($i)) . '">' . $i . '</a></li>';
        }
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        echo '<li class="page-item"><a class="page-link" href="' . e($link($totalPages)) . '">' . $totalPages . '</a></li>';
    }
    if ($page >= $totalPages) {
        echo '<li class="page-item disabled"><span class="page-link">ถัดไป</span></li>';
    } else {
        echo '<li class="page-item"><a class="page-link" href="' . e($link($page + 1)) . '">ถัดไป</a></li>';
    }
    echo '</ul></nav>';
}

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์อนุญาต (สูงสุด 20MB) กรุณาตรวจ upload_max_filesize และ post_max_size',
        UPLOAD_ERR_PARTIAL => 'อัปโหลดไฟล์ไม่สมบูรณ์ กรุณาลองใหม่',
        UPLOAD_ERR_NO_FILE => 'กรุณาเลือกไฟล์',
        UPLOAD_ERR_NO_TMP_DIR => 'เซิร์ฟเวอร์ไม่มีโฟลเดอร์ชั่วคราวสำหรับอัปโหลด',
        UPLOAD_ERR_CANT_WRITE => 'เซิร์ฟเวอร์เขียนไฟล์ไม่สำเร็จ',
        UPLOAD_ERR_EXTENSION => 'เซิร์ฟเวอร์ปฏิเสธไฟล์นี้',
        default => 'อัปโหลดไฟล์ไม่สำเร็จ',
    };
}

function sanitize_original_name(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'document';
    }
    if (mb_strlen($name) > 200) {
        $name = mb_substr($name, 0, 200);
    }
    return $name;
}

function magic_matches(string $path, string $ext): bool
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return false;
    }
    $head = fread($handle, 16);
    fclose($handle);
    if (!is_string($head) || $head === '') {
        return false;
    }
    if ($ext === 'pdf') {
        return str_starts_with(ltrim($head, "\xEF\xBB\xBF \t\r\n"), '%PDF');
    }
    if ($ext === 'png') {
        return str_starts_with($head, "\x89PNG\r\n\x1a\n");
    }
    if ($ext === 'jpg' || $ext === 'jpeg') {
        return str_starts_with($head, "\xFF\xD8\xFF");
    }
    if ($ext === 'docx' || $ext === 'xlsx') {
        return str_starts_with($head, "PK\x03\x04");
    }
    if ($ext === 'doc' || $ext === 'xls') {
        return str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1");
    }
    return false;
}

/**
 * @param array<string, mixed> $file
 * @return array{ok:bool, error?:string, skipped?:bool, ext?:string, mime?:string, size?:int, original?:string, tmp?:string}
 */
function inspect_upload(array $file, bool $required): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        if ($required) {
            return ['ok' => false, 'error' => 'กรุณาเลือกไฟล์'];
        }
        return ['ok' => true, 'skipped' => true];
    }
    if ($error !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => upload_error_message($error)];
    }
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return ['ok' => false, 'error' => 'ไฟล์ว่างเปล่า'];
    }
    if ($size > MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'error' => 'ขนาดไฟล์ต้องไม่เกิน 20MB'];
    }
    $original = sanitize_original_name((string) ($file['name'] ?? ''));
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        return ['ok' => false, 'error' => 'อนุญาตเฉพาะไฟล์ pdf, doc, docx, xls, xlsx, png, jpg และ jpeg'];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'ไฟล์อัปโหลดไม่ถูกต้อง'];
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!in_array($mime, ALLOWED_MIMES[$ext], true) || !magic_matches($tmp, $ext)) {
        return ['ok' => false, 'error' => 'ชนิดของไฟล์ไม่ตรงกับนามสกุลที่อนุญาต'];
    }
    if (in_array($ext, ['png', 'jpg', 'jpeg'], true) && getimagesize($tmp) === false) {
        return ['ok' => false, 'error' => 'ไฟล์รูปภาพไม่ถูกต้อง'];
    }
    return [
        'ok' => true,
        'skipped' => false,
        'ext' => $ext,
        'mime' => $mime,
        'size' => $size,
        'original' => $original,
        'tmp' => $tmp,
    ];
}

/**
 * @param array{ext:string, mime:string, size:int, original:string, tmp:string} $inspected
 * @return array{ok:bool, error?:string, stored_name?:string, original_name?:string, size?:int, mime?:string}
 */
function move_upload_to_storage(array $inspected): array
{
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0750, true) && !is_dir(UPLOAD_DIR)) {
        return ['ok' => false, 'error' => 'ไม่สามารถสร้างโฟลเดอร์จัดเก็บไฟล์ได้'];
    }
    if (!is_writable(UPLOAD_DIR)) {
        return ['ok' => false, 'error' => 'โฟลเดอร์ storage/uploads เขียนไม่ได้ กรุณาตรวจสิทธิ์ของเว็บเซิร์ฟเวอร์'];
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $inspected['ext'];
    $dest = UPLOAD_DIR . '/' . $stored;
    if (!move_uploaded_file($inspected['tmp'], $dest)) {
        return ['ok' => false, 'error' => 'บันทึกไฟล์ไม่สำเร็จ'];
    }
    @chmod($dest, 0640);
    return [
        'ok' => true,
        'stored_name' => $stored,
        'original_name' => $inspected['original'],
        'size' => $inspected['size'],
        'mime' => $inspected['mime'],
    ];
}

function delete_stored_file(string $storedName): void
{
    $base = basename($storedName);
    if ($base === '' || $base === '.' || $base === '..') {
        return;
    }
    $path = UPLOAD_DIR . '/' . $base;
    $root = realpath(UPLOAD_DIR);
    $real = realpath($path);
    if ($root === false || $real === false || !is_file($real)) {
        return;
    }
    $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($real, $prefix)) {
        return;
    }
    if (!unlink($real)) {
        error_log('Cannot delete stored file: ' . $base);
    }
}

function post_too_large(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return false;
    }
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    return $length > 0 && $_POST === [] && $_FILES === [];
}

function active_admin_count(int $exceptId = 0): int
{
    if ($exceptId > 0) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?");
        $stmt->execute([$exceptId]);
    } else {
        $stmt = db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1");
    }
    return (int) $stmt->fetchColumn();
}
