<?php

namespace App\Modules\PMC\Receivable\Enums;

enum PaymentReceiptType: string
{
    case Collection = 'collection';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Collection => 'Thu tiền',
            self::Refund => 'Hoàn trả',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Collection => 'success',
            self::Refund => 'warning',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
