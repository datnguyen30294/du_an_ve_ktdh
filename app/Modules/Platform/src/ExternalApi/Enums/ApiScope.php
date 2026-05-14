<?php

namespace App\Modules\Platform\ExternalApi\Enums;

enum ApiScope: string
{
    case DepartmentsRead = 'departments:read';
    case DepartmentsWrite = 'departments:write';
    case AccountsRead = 'accounts:read';
    case AccountsWrite = 'accounts:write';
    case JobTitlesRead = 'job_titles:read';
    case JobTitlesWrite = 'job_titles:write';
    case ProjectsRead = 'projects:read';
    case ProjectsWrite = 'projects:write';
    case ShiftsRead = 'shifts:read';
    case ShiftsWrite = 'shifts:write';
    case WorkSchedulesRead = 'work-schedules:read';
    case WorkSchedulesWrite = 'work-schedules:write';

    public function label(): string
    {
        return match ($this) {
            self::DepartmentsRead => 'Xem phòng ban',
            self::DepartmentsWrite => 'Thêm/sửa/xóa phòng ban',
            self::AccountsRead => 'Xem nhân viên',
            self::AccountsWrite => 'Thêm/sửa/xóa nhân viên',
            self::JobTitlesRead => 'Xem chức danh',
            self::JobTitlesWrite => 'Thêm/sửa/xóa chức danh',
            self::ProjectsRead => 'Xem dự án',
            self::ProjectsWrite => 'Thêm/sửa/xóa dự án',
            self::ShiftsRead => 'Xem ca làm việc',
            self::ShiftsWrite => 'Thêm/sửa/xóa ca làm việc',
            self::WorkSchedulesRead => 'Xem đăng ký ca làm việc',
            self::WorkSchedulesWrite => 'Ghi đăng ký ca làm việc',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Structured catalog of scope groups for UI consumption (scope picker, docs).
     *
     * @return list<array{key: string, label: string, icon: string, scopes: list<array{value: string, label: string}>}>
     */
    public static function groups(): array
    {
        $read = 'Xem';
        $write = 'Thêm / Sửa / Xóa';

        return [
            [
                'key' => 'departments',
                'label' => 'Phòng ban',
                'icon' => 'i-lucide-building-2',
                'scopes' => [
                    ['value' => self::DepartmentsRead->value, 'label' => $read],
                    ['value' => self::DepartmentsWrite->value, 'label' => $write],
                ],
            ],
            [
                'key' => 'accounts',
                'label' => 'Nhân viên',
                'icon' => 'i-lucide-users',
                'scopes' => [
                    ['value' => self::AccountsRead->value, 'label' => $read],
                    ['value' => self::AccountsWrite->value, 'label' => $write],
                ],
            ],
            [
                'key' => 'job_titles',
                'label' => 'Chức danh',
                'icon' => 'i-lucide-badge-check',
                'scopes' => [
                    ['value' => self::JobTitlesRead->value, 'label' => $read],
                    ['value' => self::JobTitlesWrite->value, 'label' => $write],
                ],
            ],
            [
                'key' => 'projects',
                'label' => 'Dự án',
                'icon' => 'i-lucide-folder-kanban',
                'scopes' => [
                    ['value' => self::ProjectsRead->value, 'label' => $read],
                    ['value' => self::ProjectsWrite->value, 'label' => $write],
                ],
            ],
            [
                'key' => 'shifts',
                'label' => 'Ca làm việc',
                'icon' => 'i-lucide-clock',
                'scopes' => [
                    ['value' => self::ShiftsRead->value, 'label' => $read],
                    ['value' => self::ShiftsWrite->value, 'label' => $write],
                ],
            ],
            [
                'key' => 'work_schedules',
                'label' => 'Đăng ký ca làm việc',
                'icon' => 'i-lucide-calendar-check',
                'scopes' => [
                    ['value' => self::WorkSchedulesRead->value, 'label' => $read],
                    ['value' => self::WorkSchedulesWrite->value, 'label' => $write],
                ],
            ],
        ];
    }
}
