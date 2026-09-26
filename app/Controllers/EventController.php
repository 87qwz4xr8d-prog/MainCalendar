<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\DepartmentModel;
use App\Models\EventModel;
use App\Models\UserModel;
use DateTimeImmutable;

final class EventController extends Controller
{
    public function index(): void
    {
        require_login();

        $start = $this->dateTime((string) ($_GET['start'] ?? ''));
        $end = $this->dateTime((string) ($_GET['end'] ?? ''));
        if (!$start || !$end || $end <= $start) {
            json_response(['ok' => false, 'message' => 'ช่วงวันที่ไม่ถูกต้อง'], 422);
        }

        $days = (int) $start->diff($end)->days;
        if ($days > 400) {
            json_response(['ok' => false, 'message' => 'ช่วงวันที่ยาวเกินไป'], 422);
        }

        $departmentIds = array_map('intval', explode(',', (string) ($_GET['departments'] ?? '')));
        $mine = (($_GET['mine'] ?? '0') === '1');
        $query = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $limit = $query !== '' ? 20 : 1000;

        $rows = (new EventModel($this->db))->between(
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $departmentIds,
            $mine ? (int) current_user()['id'] : null,
            $query,
            $limit
        );

        json_response([
            'ok' => true,
            'events' => array_map($this->present(...), $rows),
        ]);
    }

    public function save(): void
    {
        require_login();
        verify_csrf();

        $body = request_body();
        $errors = [];
        $title = preg_replace('/\s+/u', ' ', trim((string) ($body['title'] ?? ''))) ?? '';
        if ($title === '' || mb_strlen($title) > 150) {
            $errors[] = 'กรุณาระบุชื่องานไม่เกิน 150 ตัวอักษร';
        }

        $description = trim((string) ($body['description'] ?? ''));
        if (mb_strlen($description) > 4000) {
            $errors[] = 'รายละเอียดยาวเกิน 4000 ตัวอักษร';
        }

        $location = trim((string) ($body['location'] ?? ''));
        if (mb_strlen($location) > 150) {
            $errors[] = 'สถานที่ยาวเกิน 150 ตัวอักษร';
        }

        $status = (string) ($body['status'] ?? 'planned');
        if (!in_array($status, ['planned', 'in_progress', 'done'], true)) {
            $errors[] = 'สถานะไม่ถูกต้อง';
        }

        $departmentId = (int) ($body['department_id'] ?? 0);
        $department = (new DepartmentModel($this->db))->find($departmentId);
        if (!$department) {
            $errors[] = 'ไม่พบแผนกที่เลือก';
        }

        $start = $this->dateTime((string) ($body['start'] ?? ''));
        $end = $this->dateTime((string) ($body['end'] ?? ''));
        if (!$start || !$end) {
            $errors[] = 'วันเวลาไม่ถูกต้อง';
        } elseif ($end <= $start) {
            $errors[] = 'เวลาสิ้นสุดต้องอยู่หลังเวลาเริ่ม';
        } elseif ((int) $start->diff($end)->days > 62) {
            $errors[] = 'งานหนึ่งรายการยาวได้ไม่เกิน 62 วัน';
        }

        $actor = current_user();
        $users = new UserModel($this->db);
        $ownerId = (int) ($body['user_id'] ?? $actor['id']);
        $existing = null;
        $events = new EventModel($this->db);
        $id = (int) ($body['id'] ?? 0);

        if ($id > 0) {
            $existing = $events->find($id);
            if (!$existing) {
                json_response(['ok' => false, 'message' => 'ไม่พบงานนี้'], 404);
            }
            if (!can_manage_event($existing)) {
                json_response(['ok' => false, 'message' => 'แก้ไขได้เฉพาะงานของตนเอง'], 403);
            }
        }

        if (($actor['role'] ?? '') !== 'admin') {
            $ownerId = (int) $actor['id'];
        }

        $owner = $users->find($ownerId);
        if (!$owner) {
            $errors[] = 'ไม่พบพนักงานเจ้าของงาน';
        } elseif ((int) $owner['is_active'] !== 1 && (int) ($existing['user_id'] ?? 0) !== $ownerId) {
            $errors[] = 'มอบหมายงานให้บัญชีที่ปิดใช้งานไม่ได้';
        }

        if ($errors !== []) {
            json_response(['ok' => false, 'message' => implode(' · ', $errors)], 422);
        }

        $payload = [
            'user_id' => $ownerId,
            'department_id' => $departmentId,
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'location' => $location === '' ? null : $location,
            'start_at' => $start->format('Y-m-d H:i:s'),
            'end_at' => $end->format('Y-m-d H:i:s'),
            'all_day' => filter_var($body['all_day'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'status' => $status,
        ];

        if ($existing) {
            $events->update($id, $payload);
        } else {
            $id = $events->create($payload);
        }

        $saved = $events->find($id);
        json_response([
            'ok' => true,
            'message' => 'บันทึกงานแล้ว',
            'event' => $saved ? $this->present($saved) : null,
        ]);
    }

    public function delete(): void
    {
        require_login();
        verify_csrf();

        $id = (int) (request_body()['id'] ?? 0);
        $events = new EventModel($this->db);
        $existing = $events->find($id);
        if (!$existing) {
            json_response(['ok' => false, 'message' => 'ไม่พบงานนี้'], 404);
        }
        if (!can_manage_event($existing)) {
            json_response(['ok' => false, 'message' => 'ลบได้เฉพาะงานของตนเอง'], 403);
        }

        $events->delete($id);
        json_response(['ok' => true, 'message' => 'ลบงานแล้ว']);
    }

    private function present(array $row): array
    {
        $updated = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $row['updated_at']);

        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'description' => $row['description'] ?? '',
            'location' => $row['location'] ?? '',
            'start' => $row['start_at'],
            'end' => $row['end_at'],
            'all_day' => (int) $row['all_day'] === 1,
            'status' => $row['status'],
            'department_id' => (int) $row['department_id'],
            'department_name' => $row['department_name'],
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $row['color']) ? $row['color'] : '#1a73e8',
            'user_id' => (int) $row['user_id'],
            'user_name' => $row['user_name'],
            'can_edit' => can_manage_event($row),
            'updated_at' => $updated ? $updated->format('d/m/Y H:i') : '',
        ];
    }

    private function dateTime(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
            return null;
        }
        if ($date->format('Y-m-d H:i:s') !== $value) {
            return null;
        }

        return $date;
    }
}
