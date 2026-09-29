<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('index.php');
}

require_csrf('index.php');
logout_user();
