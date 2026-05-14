<?php

namespace App\Modules\PMC\Catalog\Enums;

enum SupplierStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang hợp tác',
            self::Inactive => 'Ngưng hợp tác',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
