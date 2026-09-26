<?php

declare(strict_types=1);

function app_config(?string $key = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config/app.php';
    }

    if ($key === null) {
        return $config;
    }

    return $config[$key] ?? null;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function app_web_base(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    return rtrim($dir, '/');
}

function url(string $route = ''): string
{
    $index = app_web_base() . '/index.php';
    if ($route === '') {
        return $index;
    }

    return $index . '?r=' . $route;
}

function asset(string $path): string
{
    return app_web_base() . '/assets/' . ltrim($path, '/');
}

function redirect(string $route): never
{
    header('Location: ' . url($route));
    exit;
}

function current_route(): string
{
    $route = trim((string) ($_GET['r'] ?? 'calendar'), '/');
    return $route === '' ? 'calendar' : $route;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_starts_with(current_route(), 'api/') || str_contains($accept, 'application/json');
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function request_body(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        $cached = is_array($decoded) ? $decoded : [];
        return $cached;
    }

    $cached = $_POST;
    return $cached;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || $sent === '') {
        $sent = (string) (request_body()['_token'] ?? '');
    }

    $known = $_SESSION['csrf'] ?? '';
    if (!is_string($known) || $known === '' || !hash_equals($known, $sent)) {
        if (wants_json()) {
            json_response(['ok' => false, 'message' => 'คำขอหมดอายุ กรุณารีเฟรชหน้าแล้วลองอีกครั้ง'], 419);
        }

        flash('error', 'คำขอหมดอายุ กรุณาลองอีกครั้ง');
        $route = current_route();
        $back = match (true) {
            str_starts_with($route, 'departments') => 'departments',
            str_starts_with($route, 'users') => 'users',
            $route === 'profile' => 'profile',
            $route === 'login' => 'login',
            default => 'calendar',
        };
        redirect($back);
    }
}

function flash(string $type, string $message): void
{
    $allowed = ['success', 'error', 'warning', 'info'];
    if (!in_array($type, $allowed, true)) {
        $type = 'info';
    }

    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function pull_old(): array
{
    $old = $_SESSION['old'] ?? [];
    unset($_SESSION['old']);
    return is_array($old) ? $old : [];
}

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;
    return is_array($user) ? $user : null;
}

function require_login(): void
{
    if (current_user()) {
        return;
    }

    if (wants_json()) {
        json_response(['ok' => false, 'message' => 'กรุณาเข้าสู่ระบบ'], 401);
    }

    if (current_route() !== 'calendar') {
        flash('warning', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
    }
    redirect('login');
}

function require_admin(): void
{
    require_login();
    if ((current_user()['role'] ?? '') === 'admin') {
        return;
    }

    if (wants_json()) {
        json_response(['ok' => false, 'message' => 'ไม่มีสิทธิ์ดำเนินการนี้'], 403);
    }

    http_response_code(403);
    view('errors/http', [
        'code' => 403,
        'message' => 'เฉพาะผู้ดูแลระบบเท่านั้นที่เข้าหน้านี้ได้',
    ]);
    exit;
}

function can_manage_event(array $event): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    return ($user['role'] ?? '') === 'admin' || (int) $user['id'] === (int) ($event['user_id'] ?? 0);
}

function status_label(string $status): string
{
    return match ($status) {
        'planned' => 'วางแผน',
        'in_progress' => 'กำลังทำ',
        'done' => 'เสร็จแล้ว',
        default => $status,
    };
}

function valid_password(string $password): ?string
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

function json_attr(array $data): string
{
    return e(json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR
    ));
}

function view(string $template, array $data = [], string $layout = 'layouts/app'): void
{
    $viewFile = __DIR__ . '/views/' . $template . '.php';
    $layoutFile = __DIR__ . '/views/' . $layout . '.php';
    if (!is_file($viewFile) || !is_file($layoutFile)) {
        throw new RuntimeException('ไม่พบไฟล์หน้าจอที่ต้องการ');
    }

    $data['flash'] = pull_flash();
    $data['old'] = pull_old();
    $data['currentUser'] = current_user();
    $data['appName'] = (string) app_config('name');
    $data['companyName'] = (string) app_config('company');
    $data['routeName'] = current_route();
    $data['csrfToken'] = csrf_token();
    extract($data, EXTR_SKIP);

    ob_start();
    require $viewFile;
    $content = ob_get_clean();
    require $layoutFile;
}
