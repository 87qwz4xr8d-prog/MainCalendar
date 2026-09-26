<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DepartmentModel;
use mysqli_sql_exception;

final class DepartmentController extends Controller
{
    public function index(): void
    {
        require_admin();
        view('departments/index', [
            'departments' => (new DepartmentModel($this->db))->allWithCounts(),
        ]);
    }

    public function save(): void
    {
        require_admin();
        verify_csrf();

        $id = (int) ($_POST['id'] ?? 0);
        $name = preg_replace('/\s+/u', ' ', trim((string) ($_POST['name'] ?? ''))) ?? '';
        $color = strtolower(trim((string) ($_POST['color'] ?? '')));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($name === '' || mb_strlen($name) > 100) {
            flash('error', 'กรุณาระบุชื่อแผนกไม่เกิน 100 ตัวอักษร');
            redirect('departments');
        }
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            flash('error', 'สีแผนกไม่ถูกต้อง');
            redirect('departments');
        }
        if (mb_strlen($description) > 255) {
            flash('error', 'คำอธิบายยาวเกิน 255 ตัวอักษร');
            redirect('departments');
        }

        $description = $description === '' ? null : $description;
        $model = new DepartmentModel($this->db);

        try {
            if ($id > 0) {
                if (!$model->find($id)) {
                    flash('error', 'ไม่พบแผนกนี้');
                    redirect('departments');
                }
                $model->update($id, $name, $color, $description);
                flash('success', 'บันทึกแผนกแล้ว');
            } else {
                $model->create($name, $color, $description);
                flash('success', 'เพิ่มแผนกแล้ว');
            }
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() === 1062) {
                flash('error', 'ชื่อแผนกนี้มีอยู่แล้ว');
                redirect('departments');
            }
            throw $e;
        }

        redirect('departments');
    }

    public function delete(): void
    {
        require_admin();
        verify_csrf();

        $id = (int) ($_POST['id'] ?? 0);
        $model = new DepartmentModel($this->db);
        $rows = $model->allWithCounts();
        $current = null;
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                $current = $row;
                break;
            }
        }

        if (!$current) {
            flash('error', 'ไม่พบแผนกนี้');
            redirect('departments');
        }
        if ((int) $current['user_count'] > 0 || (int) $current['event_count'] > 0) {
            flash('error', 'ลบแผนกไม่ได้ เพราะยังมีพนักงานหรืองานผูกอยู่');
            redirect('departments');
        }

        $model->delete($id);
        flash('success', 'ลบแผนกแล้ว');
        redirect('departments');
    }
}
