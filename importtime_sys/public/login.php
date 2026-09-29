<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

db();

if (current_user() !== null) {
    redirect('index.php');
}

$formError = null;
$loginUsername = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf('login.php');
    $loginUsername = post_string('username');
    $password = $_POST['password'] ?? '';
    if (!is_string($password)) {
        $password = '';
    }
    if ($loginUsername === '' || $password === '') {
        $formError = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } elseif (mb_strlen($loginUsername) > 50 || mb_strlen($password) > 72) {
        $formError = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    } else {
        try {
            $stmt = db()->prepare('SELECT id, password_hash, is_active FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$loginUsername]);
            $row = $stmt->fetch();
            $hash = is_array($row) ? (string) $row['password_hash'] : DUMMY_PASSWORD_HASH;
            $valid = password_verify($password, $hash);
            if (!is_array($row) || !$valid) {
                $formError = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            } elseif ((int) $row['is_active'] !== 1) {
                $formError = 'บัญชีนี้ถูกปิดการใช้งาน';
            } else {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $row['id'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                flash('success', 'เข้าสู่ระบบสำเร็จ');
                redirect('index.php');
            }
        } catch (Throwable $e) {
            error_log($e->__toString());
            $formError = 'ไม่สามารถเข้าสู่ระบบได้ในขณะนี้';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/vendor/fonts/sarabun.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="login-body">
    <div class="login-card">
        <h1><?= e(APP_NAME) ?></h1>
        <p class="lead">บันทึกราคาพาร์ทจากผู้ขายต่างประเทศ แล้วใช้เขียนใบขอซื้อ</p>
        <form class="stack" method="post" action="login.php" style="margin-top:18px" autocomplete="on">
            <?= csrf_input() ?>
            <div>
                <label class="req" for="username">ชื่อผู้ใช้</label>
                <input id="username" name="username" maxlength="50" required autofocus value="<?= e($loginUsername) ?>" autocomplete="username">
            </div>
            <div>
                <label class="req" for="password">รหัสผ่าน</label>
                <input id="password" name="password" type="password" maxlength="72" required autocomplete="current-password">
            </div>
            <button class="btn" type="submit">เข้าสู่ระบบ</button>
        </form>
    </div>
    <script src="assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <?php render_alerts($formError); ?>
</body>
</html>
