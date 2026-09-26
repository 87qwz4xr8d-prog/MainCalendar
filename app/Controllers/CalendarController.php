<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DepartmentModel;
use App\Models\UserModel;

final class CalendarController extends Controller
{
    public function index(): void
    {
        require_login();

        $departments = array_map(
            fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'color' => $row['color'],
                'description' => $row['description'] ?? '',
            ],
            (new DepartmentModel($this->db))->all()
        );

        $assignees = [];
        if ((current_user()['role'] ?? '') === 'admin') {
            $assignees = array_map(
                fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'department_id' => (int) $row['department_id'],
                    'is_active' => (int) $row['is_active'] === 1,
                ],
                (new UserModel($this->db))->allWithDepartment()
            );
        }

        view('calendar/index', [
            'departments' => $departments,
            'assignees' => $assignees,
        ]);
    }
}
