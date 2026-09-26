<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Models\UserModel;

final class ProfileController extends Controller
{
    public function index(): void
    {
        require_login();
        view('profile/index');
    }

    public function update(): void
    {
        require_login();
        verify_csrf();

        $name = preg_replace('/\s+/u', ' ', trim((string) ($_POST['name'] ?? ''))) ?? '';
        if ($name === '' || mb_strlen($name) > 120) {
            flash('error', 'กรุณาระบุชื่อไม่เกิน 120 ตัวอักษร');
            redirect('profile');
        }

        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $users = new UserModel($this->db);
        $id = (int) current_user()['id'];

        if ($current !== '' || $password !== '' || $confirm !== '') {
            $hash = $users->passwordHash($id);
            if ($hash === null || !password_verify($current, $hash)) {
                flash('error', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
                redirect('profile');
            }
            if ($password !== $confirm) {
                flash('error', 'รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน');
                redirect('profile');
            }
            $passwordError = valid_password($password);
            if ($passwordError) {
                flash('error', $passwordError);
                redirect('profile');
            }
            $users->updatePassword($id, password_hash($password, PASSWORD_DEFAULT));
        }

        $users->updateProfile($id, $name);
        $fresh = $users->find($id);
        if ($fresh) {
            $_SESSION['user'] = Auth::payload($fresh);
        }

        flash('success', 'บันทึกโปรไฟล์แล้ว');
        redirect('profile');
    }
}
