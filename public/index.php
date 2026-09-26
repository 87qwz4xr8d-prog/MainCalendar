<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\CalendarController;
use App\Controllers\DepartmentController;
use App\Controllers\EventController;
use App\Controllers\ProfileController;
use App\Controllers\UserController;
use App\Core\Auth;
use App\Core\Database;

// 1. เชื่อมฐานข้อมูล และตรวจว่านำเข้าตารางแล้ว
try {
    $db = Database::fromConfig(require dirname(__DIR__) . '/app/config/database.php');
    $db->one('SELECT id FROM departments LIMIT 1');
} catch (Throwable $e) {
    view('errors/setup', [
        'error' => $e->getMessage(),
    ], 'layouts/guest');
    exit;
}

// 2. ทำให้ข้อมูลในเซสชันตรงกับฐานข้อมูลทุกครั้งที่เปิดหน้า
Auth::refresh($db);

$auth = new AuthController($db);
$calendar = new CalendarController($db);
$events = new EventController($db);
$departments = new DepartmentController($db);
$users = new UserController($db);
$profile = new ProfileController($db);

$route = current_route();
$method = request_method();

if ($method === 'GET' && $route === 'calendar' && !current_user()) {
    redirect('login');
}

// 3. แผนที่เส้นทาง — เพิ่มหน้าใหม่ได้โดยใส่เมธอดและตัวควบคุมที่นี่
$routes = [
    'GET' => [
        'login' => fn () => $auth->showLogin(),
        'calendar' => fn () => $calendar->index(),
        'departments' => fn () => $departments->index(),
        'users' => fn () => $users->index(),
        'profile' => fn () => $profile->index(),
        'api/events' => fn () => $events->index(),
    ],
    'POST' => [
        'login' => fn () => $auth->login(),
        'logout' => fn () => $auth->logout(),
        'api/events/save' => fn () => $events->save(),
        'api/events/delete' => fn () => $events->delete(),
        'departments/save' => fn () => $departments->save(),
        'departments/delete' => fn () => $departments->delete(),
        'users/save' => fn () => $users->save(),
        'users/delete' => fn () => $users->delete(),
        'profile' => fn () => $profile->update(),
    ],
];

$handler = $routes[$method][$route] ?? null;
if ($handler) {
    $handler();
    exit;
}

$allowed = [];
foreach ($routes as $verb => $map) {
    if (isset($map[$route])) {
        $allowed[] = $verb;
    }
}

if ($allowed !== []) {
    http_response_code(405);
    header('Allow: ' . implode(', ', $allowed));
    if (wants_json()) {
        json_response(['ok' => false, 'message' => 'เมธอดไม่ถูกต้อง'], 405);
    }
    view('errors/http', ['code' => 405, 'message' => 'ไม่รองรับวิธีเรียกหน้านี้']);
    exit;
}

http_response_code(404);
if (current_user()) {
    view('errors/http', ['code' => 404, 'message' => 'ไม่พบหน้าที่ต้องการ']);
} else {
    view('errors/http', ['code' => 404, 'message' => 'ไม่พบหน้าที่ต้องการ'], 'layouts/guest');
}
