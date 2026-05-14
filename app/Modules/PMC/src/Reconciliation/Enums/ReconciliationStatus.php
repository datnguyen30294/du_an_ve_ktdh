<?php

namespace App\Modules\PMC\Reconciliation\Enums;

enum ReconciliationStatus: string
{
    case Pending = 'pending';
    case Reconciled = 'reconciled';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ đối soát',
            self::Reconciled => 'Đã đối soát',
            self::Rejected => 'Từ chối',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Reconciled => 'success',
            self::Rejected => 'error',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
