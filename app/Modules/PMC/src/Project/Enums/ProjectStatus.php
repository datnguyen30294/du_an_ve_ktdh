<?php

namespace App\Modules\PMC\Project\Enums;

enum ProjectStatus: string
{
    case Managing = 'managing';
    case Stopped = 'stopped';

    public function label(): string
    {
        return match ($this) {
            self::Managing => 'Đang quản lý',
            self::Stopped => 'Đã dừng',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
