<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DepartmentModel;
use App\Models\EventModel;
use App\Models\UserModel;

final class UserController extends Controller
{
    public function index(): void
    {
        require_admin();
        view('users/index', [
            'users' => (new UserModel($this->db))->allWithDepartment(),
            'departments' => (new DepartmentModel($this->db))->all(),
        ]);
    }

    public function save(): void
    {
        require_admin();
        verify_csrf();

        $id = (int) ($_POST['id'] ?? 0);
        $name = preg_replace('/\s+/u', ' ', trim((string) ($_POST['name'] ?? ''))) ?? '';
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        $role = (string) ($_POST['role'] ?? 'employee');
        $isActive = filter_var($_POST['is_active'] ?? '0', FILTER_VALIDATE_BOOLEAN);
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        $users = new UserModel($this->db);
        $existing = $id > 0 ? $users->find($id) : null;
        if ($id > 0 && !$existing) {
            flash('error', 'ไม่พบพนักงานนี้');
            redirect('users');
        }

        if ($name === '' || mb_strlen($name) > 120) {
            flash('error', 'กรุณาระบุชื่อไม่เกิน 120 ตัวอักษร');
            redirect('users');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            flash('error', 'อีเมลไม่ถูกต้อง');
            redirect('users');
        }
        if (!in_array($role, ['admin', 'employee'], true)) {
            flash('error', 'บทบาทไม่ถูกต้อง');
            redirect('users');
        }
        if (!(new DepartmentModel($this->db))->find($departmentId)) {
            flash('error', 'ไม่พบแผนกที่เลือก');
            redirect('users');
        }
        if ($users->emailTaken($email, $id > 0 ? $id : null)) {
            flash('error', 'อีเมลนี้ถูกใช้แล้ว');
            redirect('users');
        }

        $actorId = (int) current_user()['id'];
        if ($existing && $actorId === $id && !$isActive) {
            flash('error', 'ปิดใช้งานบัญชีของตนเองไม่ได้');
            redirect('users');
        }
        if ($existing && $actorId === $id && $role !== 'admin') {
            flash('error', 'เปลี่ยนบทบาทของตนเองไม่ได้');
            redirect('users');
        }
        if ($existing && $existing['role'] === 'admin' && (int) $existing['is_active'] === 1) {
            $demote = $role !== 'admin' || !$isActive;
            if ($demote && $users->countActiveAdmins() <= 1) {
                flash('error', 'ต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อย 1 คน');
                redirect('users');
            }
        }

        if (!$existing && $password === '') {
            flash('error', 'กรุณากำหนดรหัสผ่านเริ่มต้น');
            redirect('users');
        }
        if ($password !== '' || $confirm !== '') {
            if ($password !== $confirm) {
                flash('error', 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน');
                redirect('users');
            }
            $passwordError = valid_password($password);
            if ($passwordError) {
                flash('error', $passwordError);
                redirect('users');
            }
        }

        if ($existing) {
            $users->update($id, $departmentId, $name, $email, $role, $isActive);
            if ($password !== '') {
                $users->updatePassword($id, password_hash($password, PASSWORD_DEFAULT));
            }
            flash('success', 'บันทึกข้อมูลพนักงานแล้ว');
        } else {
            $users->create($departmentId, $name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $isActive);
            flash('success', 'เพิ่มพนักงานแล้ว');
        }

        redirect('users');
    }

    public function delete(): void
    {
        require_admin();
        verify_csrf();

        $id = (int) ($_POST['id'] ?? 0);
        $users = new UserModel($this->db);
        $existing = $users->find($id);
        if (!$existing) {
            flash('error', 'ไม่พบพนักงานนี้');
            redirect('users');
        }
        if ($id === (int) current_user()['id']) {
            flash('error', 'ลบบัญชีของตนเองไม่ได้');
            redirect('users');
        }
        if ($existing['role'] === 'admin' && (int) $existing['is_active'] === 1 && $users->countActiveAdmins() <= 1) {
            flash('error', 'ต้องมีผู้ดูแลระบบที่ใช้งานได้อย่างน้อย 1 คน');
            redirect('users');
        }
        if ((new EventModel($this->db))->countByUser($id) > 0) {
            flash('error', 'พนักงานคนนี้ยังมีงานในปฏิทิน กรุณาลบหรือย้ายงานก่อน');
            redirect('users');
        }

        $users->delete($id);
        flash('success', 'ลบพนักงานแล้ว');
        redirect('users');
    }
}
