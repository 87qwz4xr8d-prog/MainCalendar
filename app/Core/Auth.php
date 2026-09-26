<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\UserModel;

final class Auth
{
    public static function login(array $row): void
    {
        session_regenerate_id(true);
        unset($_SESSION['csrf'], $_SESSION['login_fail'], $_SESSION['old']);
        $_SESSION['user'] = self::payload($row);
    }

    public static function logout(): void
    {
        unset($_SESSION['user'], $_SESSION['csrf'], $_SESSION['login_fail'], $_SESSION['old']);
        session_regenerate_id(true);
    }

    public static function refresh(Database $db): void
    {
        $current = current_user();
        if (!$current) {
            return;
        }

        $row = (new UserModel($db))->find((int) $current['id']);
        if (!$row || (int) $row['is_active'] !== 1) {
            unset($_SESSION['user']);
            return;
        }

        $_SESSION['user'] = self::payload($row);
    }

    public static function payload(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'role' => (string) $row['role'],
            'department_id' => (int) $row['department_id'],
            'department_name' => (string) ($row['department_name'] ?? ''),
        ];
    }
}
