<?php

namespace App\Modules\PMC\Account\Enums;

enum RoleType: string
{
    case Default = 'default';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Mặc định',
            self::Custom => 'Tùy chỉnh',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
