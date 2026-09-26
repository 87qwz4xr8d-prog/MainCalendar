<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class UserModel
{
    public function __construct(private Database $db)
    {
    }

    public function allWithDepartment(): array
    {
        return $this->db->all(
            'SELECT u.id, u.department_id, u.name, u.employee_code, u.email, u.role, u.is_active,
                    d.name AS department_name, d.color AS department_color
             FROM users u
             INNER JOIN departments d ON d.id = u.department_id
             ORDER BY d.name ASC, u.name ASC'
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->one(
            'SELECT u.id, u.department_id, u.name, u.employee_code, u.email, u.role, u.is_active,
                    d.name AS department_name, d.color AS department_color
             FROM users u
             INNER JOIN departments d ON d.id = u.department_id
             WHERE u.id = ?',
            'i',
            [$id]
        );
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->one(
            'SELECT u.id, u.department_id, u.name, u.employee_code, u.email, u.password_hash, u.role, u.is_active,
                    d.name AS department_name
             FROM users u
             INNER JOIN departments d ON d.id = u.department_id
             WHERE u.email = ?',
            's',
            [$email]
        );
    }

    public function findByEmployeeCode(string $code): ?array
    {
        return $this->db->one(
            'SELECT u.id, u.department_id, u.name, u.employee_code, u.email, u.password_hash, u.role, u.is_active,
                    d.name AS department_name
             FROM users u
             INNER JOIN departments d ON d.id = u.department_id
             WHERE u.employee_code = ?',
            's',
            [$code]
        );
    }

    public function employeeCodeTaken(string $code, ?int $exceptId = null): bool
    {
        if ($exceptId) {
            $row = $this->db->one(
                'SELECT id FROM users WHERE employee_code = ? AND id <> ?',
                'si',
                [$code, $exceptId]
            );
        } else {
            $row = $this->db->one('SELECT id FROM users WHERE employee_code = ?', 's', [$code]);
        }

        return $row !== null;
    }

    public function emailTaken(string $email, ?int $exceptId = null): bool
    {
        if ($exceptId) {
            $row = $this->db->one(
                'SELECT id FROM users WHERE email = ? AND id <> ?',
                'si',
                [$email, $exceptId]
            );
        } else {
            $row = $this->db->one('SELECT id FROM users WHERE email = ?', 's', [$email]);
        }

        return $row !== null;
    }

    public function countActiveAdmins(): int
    {
        $row = $this->db->one(
            "SELECT COUNT(*) AS total FROM users WHERE role = 'admin' AND is_active = 1"
        );

        return (int) ($row['total'] ?? 0);
    }

    public function create(
        int $departmentId,
        string $name,
        ?string $employeeCode,
        string $email,
        string $passwordHash,
        string $role,
        bool $isActive
    ): int {
        return $this->db->insert(
            'INSERT INTO users (department_id, name, employee_code, email, password_hash, role, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            'isssssi',
            [$departmentId, $name, $employeeCode, $email, $passwordHash, $role, $isActive ? 1 : 0]
        );
    }

    public function update(
        int $id,
        int $departmentId,
        string $name,
        ?string $employeeCode,
        string $email,
        string $role,
        bool $isActive
    ): void {
        $this->db->execute(
            'UPDATE users
             SET department_id = ?, name = ?, employee_code = ?, email = ?, role = ?, is_active = ?
             WHERE id = ?',
            'issssii',
            [$departmentId, $name, $employeeCode, $email, $role, $isActive ? 1 : 0, $id]
        );
    }

    public function updateProfile(int $id, string $name): void
    {
        $this->db->execute('UPDATE users SET name = ? WHERE id = ?', 'si', [$name, $id]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->execute(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            'si',
            [$passwordHash, $id]
        );
    }

    public function passwordHash(int $id): ?string
    {
        $row = $this->db->one('SELECT password_hash FROM users WHERE id = ?', 'i', [$id]);
        return $row['password_hash'] ?? null;
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM users WHERE id = ?', 'i', [$id]);
    }
}
