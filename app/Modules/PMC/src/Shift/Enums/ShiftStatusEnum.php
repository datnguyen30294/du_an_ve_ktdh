<?php

namespace App\Modules\PMC\Shift\Enums;

enum ShiftStatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang sử dụng',
            self::Inactive => 'Tạm ẩn',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
