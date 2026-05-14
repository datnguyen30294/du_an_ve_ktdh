<?php

namespace App\Modules\PMC\Catalog\Enums;

enum CatalogItemType: string
{
    case Material = 'material';
    case Service = 'service';
    case Adhoc = 'adhoc';

    public function label(): string
    {
        return match ($this) {
            self::Material => 'Vật tư',
            self::Service => 'Dịch vụ',
            self::Adhoc => 'Dịch vụ tùy chọn',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
