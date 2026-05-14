<?php

namespace App\Modules\PMC\Catalog\Enums;

enum CatalogStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Đang sử dụng',
            self::Inactive => 'Ngưng sử dụng',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
