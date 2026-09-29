<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$user = require_login();
$pageTitle = isset($pageTitle) && is_string($pageTitle) && $pageTitle !== '' ? $pageTitle : APP_NAME;
$activeNav = isset($activeNav) && is_string($activeNav) ? $activeNav : '';
$navClass = static function (string $name) use ($activeNav): string {
    return $activeNav === $name ? 'is-active' : '';
};
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/vendor/fonts/sarabun.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="topbar no-print">
    <a class="brand" href="index.php">
        <span class="brand-mark" aria-hidden="true"><i class="bi bi-box-seam"></i></span>
        <span>
            <strong><?= e(APP_NAME) ?></strong>
            <small><?= e(company_name()) ?></small>
        </span>
    </a>
    <nav class="topnav" aria-label="เมนูหลัก">
        <a class="<?= e($navClass('home')) ?>" href="index.php">แดชบอร์ด</a>
        <a class="<?= e($navClass('rfq')) ?>" href="price-requests.php">ใบขอราคา</a>
        <a class="<?= e($navClass('pr')) ?>" href="purchase-requests.php">ใบขอซื้อ</a>
    </nav>
    <div class="userbox">
        <a class="user-link" href="profile.php"><?= e((string) $user['full_name']) ?></a>
        <form method="post" action="logout.php">
            <?= csrf_input() ?>
            <button type="submit" class="btn btn-quiet">ออกจากระบบ</button>
        </form>
    </div>
</header>
<main class="page">
