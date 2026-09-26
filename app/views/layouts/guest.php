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
<body class="guest-body">
<main class="guest-main">
    <?= $content ?>
</main>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
<script src="<?= e(asset('js/ui.js')) ?>"></script>
<?php require __DIR__ . '/../partials/flash.php'; ?>
</body>
</html>
