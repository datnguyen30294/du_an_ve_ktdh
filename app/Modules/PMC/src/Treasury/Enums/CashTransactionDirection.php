<?php

namespace App\Modules\PMC\Treasury\Enums;

enum CashTransactionDirection: string
{
    case Inflow = 'inflow';
    case Outflow = 'outflow';

    public function label(): string
    {
        return match ($this) {
            self::Inflow => 'Tiền vào',
            self::Outflow => 'Tiền ra',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
