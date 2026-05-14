<?php

namespace App\Modules\PMC\Order\Enums;

enum CommissionOverrideRecipientType: string
{
    case OperatingCompany = 'operating_company';
    case BoardOfDirectors = 'board_of_directors';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::OperatingCompany => 'Công ty vận hành',
            self::BoardOfDirectors => 'Ban quản trị',
            self::Staff => 'Nhân viên',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
