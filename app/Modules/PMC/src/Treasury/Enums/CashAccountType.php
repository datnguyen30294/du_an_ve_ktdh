<?php

namespace App\Modules\PMC\Treasury\Enums;

enum CashAccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case EWallet = 'e_wallet';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tiền mặt',
            self::Bank => 'Ngân hàng',
            self::EWallet => 'Ví điện tử',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
