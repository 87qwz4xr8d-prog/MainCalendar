<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash('error', 'คำขอไม่ถูกต้อง');
    redirect('index.php');
}

require_csrf('index.php');
logout_user();
