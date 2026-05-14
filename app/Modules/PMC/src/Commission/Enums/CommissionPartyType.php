<?php

namespace App\Modules\PMC\Commission\Enums;

enum CommissionPartyType: string
{
    case OperatingCompany = 'operating_company';
    case BoardOfDirectors = 'board_of_directors';
    case Management = 'management';

    public function label(): string
    {
        return match ($this) {
            self::OperatingCompany => 'Công ty vận hành',
            self::BoardOfDirectors => 'Ban quản trị',
            self::Management => 'Ban quản lý',
        };
    }

    /**
     * Fixed deduction order (Platform = 1, handled externally).
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::OperatingCompany => 2,
            self::BoardOfDirectors => 3,
            self::Management => 4,
        };
    }

    /**
     * @return list<CommissionPartyType>
     */
    public static function ordered(): array
    {
        $cases = self::cases();
        usort($cases, fn (self $a, self $b) => $a->sortOrder() <=> $b->sortOrder());

        return $cases;
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
