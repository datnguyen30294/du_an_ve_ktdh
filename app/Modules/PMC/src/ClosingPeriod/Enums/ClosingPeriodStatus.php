<?php

namespace App\Modules\PMC\ClosingPeriod\Enums;

enum ClosingPeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Đang mở',
            self::Closed => 'Đã chốt',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'primary',
            self::Closed => 'success',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
