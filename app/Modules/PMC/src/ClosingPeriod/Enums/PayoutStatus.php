<?php

namespace App\Modules\PMC\ClosingPeriod\Enums;

enum PayoutStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Chưa thanh toán',
            self::Paid => 'Đã thanh toán',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::Paid => 'success',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
