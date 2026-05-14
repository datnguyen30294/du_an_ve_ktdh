<?php

namespace App\Modules\PMC\Report\Sla\Enums;

enum SlaResult: string
{
    case OnTime = 'on_time';
    case Breached = 'breached';

    public function label(): string
    {
        return match ($this) {
            self::OnTime => 'Đúng hạn',
            self::Breached => 'Vi phạm',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
