<?php

declare(strict_types=1);

namespace App\Core;

use mysqli;
use mysqli_stmt;
use RuntimeException;

/**
 * ตัวห่อ mysqli ที่บังคับใช้ prepared statement ทุกคำสั่ง
 */
final class Database
{
    private function __construct(private mysqli $mysqli)
    {
    }

    public static function fromConfig(array $config): self
    {
        $mysqli = new mysqli(
            (string) $config['host'],
            (string) $config['username'],
            (string) $config['password'],
            (string) $config['database'],
            (int) $config['port']
        );
        $mysqli->set_charset((string) ($config['charset'] ?? 'utf8mb4'));

        return new self($mysqli);
    }

    public function all(string $sql, string $types = '', array $params = []): array
    {
        $statement = $this->run($sql, $types, $params);
        $result = $statement->get_result();
        if ($result === false) {
            throw new RuntimeException('อ่านผลลัพธ์จากฐานข้อมูลไม่ได้');
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function one(string $sql, string $types = '', array $params = []): ?array
    {
        $rows = $this->all($sql, $types, $params);
        return $rows[0] ?? null;
    }

    public function insert(string $sql, string $types, array $params): int
    {
        $this->run($sql, $types, $params);
        return (int) $this->mysqli->insert_id;
    }

    public function execute(string $sql, string $types = '', array $params = []): int
    {
        $statement = $this->run($sql, $types, $params);
        return $statement->affected_rows;
    }

    private function run(string $sql, string $types, array $params): mysqli_stmt
    {
        $statement = $this->mysqli->prepare($sql);
        if ($types !== '') {
            if (strlen($types) !== count($params)) {
                throw new RuntimeException('จำนวนพารามิเตอร์ไม่ตรงกับคำสั่ง SQL');
            }
            $this->bind($statement, $types, $params);
        }
        $statement->execute();

        return $statement;
    }

    private function bind(mysqli_stmt $statement, string $types, array $params): void
    {
        $refs = [];
        foreach ($params as $index => $value) {
            $refs[$index] = &$params[$index];
        }
        $statement->bind_param($types, ...$refs);
    }
}
