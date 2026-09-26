<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e($csrfToken) ?>">
    <title><?= e(($pageTitle ?? $appName) . ' · ' . $companyName) ?></title>
    <link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app-body">
<div class="app-shell">
    <header class="topbar">
        <a class="brand" href="<?= e(url('calendar')) ?>">
            <span class="brand-mark" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
            <span class="brand-text">
                <strong><?= e($appName) ?></strong>
                <small><?= e($companyName) ?></small>
            </span>
        </a>
        <nav class="topnav" aria-label="เมนูหลัก">
            <a class="<?= $routeName === 'calendar' ? 'is-active' : '' ?>" href="<?= e(url('calendar')) ?>">ปฏิทิน</a>
            <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
                <a class="<?= $routeName === 'departments' ? 'is-active' : '' ?>" href="<?= e(url('departments')) ?>">แผนก</a>
                <a class="<?= $routeName === 'users' ? 'is-active' : '' ?>" href="<?= e(url('users')) ?>">พนักงาน</a>
            <?php endif; ?>
        </nav>
        <?php if ($currentUser): ?>
            <div class="dropdown ms-auto">
                <button class="user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar"><?= e(mb_substr($currentUser['name'], 0, 1)) ?></span>
                    <span class="user-meta">
                        <strong><?= e($currentUser['name']) ?></strong>
                        <small><?= e($currentUser['department_name']) ?></small>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header"><?= e(($currentUser['employee_code'] ?? '') !== '' ? $currentUser['employee_code'] : $currentUser['email']) ?></h6></li>
                    <li><a class="dropdown-item" href="<?= e(url('profile')) ?>"><i class="bi bi-person me-2"></i>โปรไฟล์</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="<?= e(url('logout')) ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>ออกจากระบบ</button>
                        </form>
                    </li>
                </ul>
            </div>
        <?php else: ?>
            <a class="btn btn-primary ms-auto" href="<?= e(url('login')) ?>">เข้าสู่ระบบ</a>
        <?php endif; ?>
    </header>
    <main class="<?= !empty($fullWidth) ? 'main-flush' : 'main-page' ?>">
        <?= $content ?>
    </main>
</div>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
<script src="<?= e(asset('js/ui.js')) ?>"></script>
<?php foreach (($scripts ?? []) as $script): ?>
    <script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>
<?php require __DIR__ . '/../partials/flash.php'; ?>
</body>
</html>
