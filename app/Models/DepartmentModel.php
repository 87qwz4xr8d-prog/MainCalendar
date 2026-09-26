<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class DepartmentModel
{
    public function __construct(private Database $db)
    {
    }

    public function all(): array
    {
        return $this->db->all(
            'SELECT id, name, color, description
             FROM departments
             ORDER BY name ASC'
        );
    }

    public function allWithCounts(): array
    {
        return $this->db->all(
            'SELECT d.id, d.name, d.color, d.description,
                    (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id) AS user_count,
                    (SELECT COUNT(*) FROM events e WHERE e.department_id = d.id) AS event_count
             FROM departments d
             ORDER BY d.name ASC'
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->one(
            'SELECT id, name, color, description FROM departments WHERE id = ?',
            'i',
            [$id]
        );
    }

    public function create(string $name, string $color, ?string $description): int
    {
        return $this->db->insert(
            'INSERT INTO departments (name, color, description) VALUES (?, ?, ?)',
            'sss',
            [$name, $color, $description]
        );
    }

    public function update(int $id, string $name, string $color, ?string $description): void
    {
        $this->db->execute(
            'UPDATE departments SET name = ?, color = ?, description = ? WHERE id = ?',
            'sssi',
            [$name, $color, $description, $id]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM departments WHERE id = ?', 'i', [$id]);
    }
}
