<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\UserModel;

final class AuthController extends Controller
{
    private const DUMMY_HASH = '$2y$12$NxbHBrQC5eoQ8xrhTTjNKumlhhCm/x.bm679B3TdufvwPRpRopFdq';

    public function showLogin(): void
    {
        if (current_user()) {
            redirect('calendar');
        }

        view('auth/login', [], 'layouts/guest');
    }

    public function login(): void
    {
        verify_csrf();

        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->fail('กรุณากรอกอีเมลและรหัสผ่าน', $email);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('รูปแบบอีเมลไม่ถูกต้อง', $email);
        }
        if ($this->throttled()) {
            $this->fail('พยายามเข้าสู่ระบบหลายครั้งเกินไป กรุณารอ 2 นาที', $email);
        }

        $user = (new UserModel($this->db))->findByEmail($email);
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $valid = password_verify($password, $hash);

        if (!$user || !$valid) {
            $this->hitFail();
            $this->fail('อีเมลหรือรหัสผ่านไม่ถูกต้อง', $email);
        }
        if ((int) $user['is_active'] !== 1) {
            $this->fail('บัญชีนี้ถูกปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ', $email);
        }

        unset($_SESSION['login_fail']);
        Auth::login($user);
        redirect('calendar');
    }

    public function logout(): void
    {
        require_login();
        verify_csrf();
        Auth::logout();
        flash('success', 'ออกจากระบบแล้ว');
        redirect('login');
    }

    private function fail(string $message, string $email): never
    {
        $_SESSION['old'] = ['email' => $email];
        flash('error', $message);
        redirect('login');
    }

    private function throttled(): bool
    {
        $info = $_SESSION['login_fail'] ?? null;
        if (!is_array($info)) {
            return false;
        }
        if (time() - (int) ($info['at'] ?? 0) >= 120) {
            unset($_SESSION['login_fail']);
            return false;
        }

        return (int) ($info['count'] ?? 0) >= 5;
    }

    private function hitFail(): void
    {
        $info = $_SESSION['login_fail'] ?? ['count' => 0, 'at' => time()];
        if (!is_array($info) || time() - (int) ($info['at'] ?? 0) >= 120) {
            $info = ['count' => 0, 'at' => time()];
        }
        $info['count'] = (int) $info['count'] + 1;
        $info['at'] = time();
        $_SESSION['login_fail'] = $info;
    }
}
