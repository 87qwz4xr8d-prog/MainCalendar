<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!empty($requireAdmin)) {
    $user = require_admin();
} else {
    $user = require_login();
}

$pageTitle = isset($pageTitle) && is_string($pageTitle) && $pageTitle !== '' ? $pageTitle : APP_NAME;
$activeNav = isset($activeNav) && is_string($activeNav) ? $activeNav : '';
$nav = static function (string $name) use ($activeNav): string {
    return $activeNav === $name ? 'nav-link active' : 'nav-link';
};
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/vendor/fonts/sarabun.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/vendor/adminlte/adminlte.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="สลับเมนู"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item d-none d-sm-inline-block">
                <span class="nav-link"><?= e($user['full_name']) ?> · <?= $user['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'เจ้าหน้าที่' ?></span>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="index.php" class="brand-link text-center">
            <span class="brand-text font-weight-light"><?= e(APP_NAME) ?></span>
        </a>
        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="info">
                    <span class="d-block text-white"><?= e($user['full_name']) ?></span>
                    <?= role_badge((string) $user['role']) ?>
                </div>
            </div>
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="index.php" class="<?= e($nav('dashboard')) ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>แดชบอร์ด</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="documents.php" class="<?= e($nav('documents')) ?>">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>เอกสาร</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="categories.php" class="<?= e($nav('categories')) ?>">
                            <i class="nav-icon fas fa-folder-open"></i>
                            <p>หมวดหมู่</p>
                        </a>
                    </li>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a href="users.php" class="<?= e($nav('users')) ?>">
                                <i class="nav-icon fas fa-users"></i>
                                <p>ผู้ใช้งาน</p>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <form method="post" action="logout.php">
                            <?= csrf_input() ?>
                            <button type="submit" class="nav-link">
                                <i class="nav-icon fas fa-sign-out-alt"></i>
                                <p>ออกจากระบบ</p>
                            </button>
                        </form>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-12">
                        <h1 class="m-0"><?= e($pageTitle) ?></h1>
                    </div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
