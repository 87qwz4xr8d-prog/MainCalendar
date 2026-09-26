<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/helpers.php';

$missing = array_values(array_filter(
    ['mysqli', 'mbstring', 'json'],
    static fn (string $extension): bool => !extension_loaded($extension)
));

if ($missing !== []) {
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>ยังไม่พร้อมใช้งาน</title>';
    echo '<p style="font-family:sans-serif">เซิร์ฟเวอร์ยังไม่มีส่วนขยาย PHP: ' . e(implode(', ', $missing)) . '</p>';
    exit;
}

date_default_timezone_set((string) app_config('timezone'));
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    $message = app_config('debug') ? $e->getMessage() : 'เกิดข้อผิดพลาดภายในระบบ';
    if (wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
        return;
    }

    echo '<!doctype html><meta charset="utf-8"><title>ข้อผิดพลาด</title>';
    echo '<p style="font-family:sans-serif">' . e($message) . '</p>';
});

if (PHP_SAPI !== 'cli') {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $cookiePath = app_web_base();
    session_name((string) app_config('session_name'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => ($cookiePath === '' ? '/' : $cookiePath),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Content-Type: text/html; charset=utf-8');
}
