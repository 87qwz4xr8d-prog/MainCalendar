<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class EventModel
{
    public function __construct(private Database $db)
    {
    }

    /**
     * งานที่ทับช่วงเวลาที่มองเห็น: เริ่มก่อนปลายหน้าต่าง และจบหลังต้นหน้าต่าง
     */
    public function between(
        string $windowStart,
        string $windowEnd,
        array $departmentIds,
        ?int $userId,
        string $query,
        int $limit
    ): array {
        $departmentIds = array_values(array_unique(array_map('intval', $departmentIds)));
        $departmentIds = array_values(array_filter($departmentIds, static fn (int $id): bool => $id > 0));
        if ($departmentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($departmentIds), '?'));
        $sql = "SELECT e.id, e.user_id, e.department_id, e.title, e.description, e.location,
                       e.start_at, e.end_at, e.all_day, e.status, e.updated_at,
                       u.name AS user_name, d.name AS department_name, d.color
                FROM events e
                INNER JOIN users u ON u.id = e.user_id
                INNER JOIN departments d ON d.id = e.department_id
                WHERE e.start_at < ? AND e.end_at > ?
                  AND e.department_id IN ($placeholders)";

        $types = 'ss' . str_repeat('i', count($departmentIds));
        $params = array_merge([$windowEnd, $windowStart], $departmentIds);

        if ($userId) {
            $sql .= ' AND e.user_id = ?';
            $types .= 'i';
            $params[] = $userId;
        }

        if ($query !== '') {
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $sql .= " AND (e.title LIKE ? ESCAPE '\\\\' OR e.location LIKE ? ESCAPE '\\\\' OR u.name LIKE ? ESCAPE '\\\\')";
            $types .= 'sss';
            array_push($params, $like, $like, $like);
        }

        $limit = max(1, min($limit, 1000));
        $sql .= ' ORDER BY e.start_at ASC, e.id ASC LIMIT ' . $limit;

        return $this->db->all($sql, $types, $params);
    }

    public function find(int $id): ?array
    {
        return $this->db->one(
            'SELECT e.id, e.user_id, e.department_id, e.title, e.description, e.location,
                    e.start_at, e.end_at, e.all_day, e.status, e.updated_at,
                    u.name AS user_name, d.name AS department_name, d.color
             FROM events e
             INNER JOIN users u ON u.id = e.user_id
             INNER JOIN departments d ON d.id = e.department_id
             WHERE e.id = ?',
            'i',
            [$id]
        );
    }

    public function countByUser(int $userId): int
    {
        $row = $this->db->one(
            'SELECT COUNT(*) AS total FROM events WHERE user_id = ?',
            'i',
            [$userId]
        );

        return (int) ($row['total'] ?? 0);
    }

    public function create(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO events
                (user_id, department_id, title, description, location, start_at, end_at, all_day, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'iisssssis',
            [
                $data['user_id'],
                $data['department_id'],
                $data['title'],
                $data['description'],
                $data['location'],
                $data['start_at'],
                $data['end_at'],
                $data['all_day'],
                $data['status'],
            ]
        );
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE events
             SET user_id = ?, department_id = ?, title = ?, description = ?, location = ?,
                 start_at = ?, end_at = ?, all_day = ?, status = ?
             WHERE id = ?',
            'iisssssisi',
            [
                $data['user_id'],
                $data['department_id'],
                $data['title'],
                $data['description'],
                $data['location'],
                $data['start_at'],
                $data['end_at'],
                $data['all_day'],
                $data['status'],
                $id,
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM events WHERE id = ?', 'i', [$id]);
    }
}
