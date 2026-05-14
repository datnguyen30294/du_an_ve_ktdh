<?php

namespace App\Modules\PMC\Commission\Enums;

enum CommissionValueType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Phần trăm',
            self::Fixed => 'Tiền cứng',
            self::Both => 'Cả hai',
        };
    }

    public function requiresPercent(): bool
    {
        return $this === self::Percent || $this === self::Both;
    }

    public function requiresFixed(): bool
    {
        return $this === self::Fixed || $this === self::Both;
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
