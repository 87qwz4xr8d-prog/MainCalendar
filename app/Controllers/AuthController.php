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

        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($login === '' || $password === '') {
            $this->fail('กรุณากรอกรหัสพนักงานหรืออีเมล และรหัสผ่าน', $login);
        }
        if ($this->throttled()) {
            $this->fail('พยายามเข้าสู่ระบบหลายครั้งเกินไป กรุณารอ 2 นาที', $login);
        }

        $users = new UserModel($this->db);
        if (str_contains($login, '@')) {
            $email = mb_strtolower($login);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->fail('รูปแบบอีเมลไม่ถูกต้อง', $login);
            }
            $user = $users->findByEmail($email);
        } else {
            $code = mb_strtoupper($login);
            if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,19}$/', $code)) {
                $this->fail('รูปแบบรหัสพนักงานไม่ถูกต้อง', $login);
            }
            $user = $users->findByEmployeeCode($code);
        }

        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $valid = password_verify($password, $hash);

        if (!$user || !$valid) {
            $this->hitFail();
            $this->fail('รหัสพนักงาน อีเมล หรือรหัสผ่านไม่ถูกต้อง', $login);
        }
        if ((int) $user['is_active'] !== 1) {
            $this->fail('บัญชีนี้ถูกปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ', $login);
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
        redirect('calendar');
    }

    private function fail(string $message, string $login): never
    {
        $_SESSION['old'] = ['login' => $login];
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
