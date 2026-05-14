<?php

namespace App\Modules\PMC\Quote\Enums;

enum ResidentApprovedVia: string
{
    case ResidentSelf = 'resident_self';
    case AdminOnBehalf = 'admin_on_behalf';

    public function label(): string
    {
        return match ($this) {
            self::ResidentSelf => 'Cư dân tự duyệt',
            self::AdminOnBehalf => 'Admin duyệt hộ',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
