<?php

namespace App\Modules\PMC\Account\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Nam',
            self::Female => 'Nữ',
            self::Other => 'Khác',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
