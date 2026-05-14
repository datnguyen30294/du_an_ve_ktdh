<?php

namespace App\Modules\PMC\ClosingPeriod\Enums;

enum SnapshotRecipientType: string
{
    case Platform = 'platform';
    case OperatingCompany = 'operating_company';
    case BoardOfDirectors = 'board_of_directors';
    case Management = 'management';
    case Department = 'department';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Platform',
            self::OperatingCompany => 'Công ty vận hành',
            self::BoardOfDirectors => 'Ban quản trị',
            self::Management => 'Ban quản lý',
            self::Department => 'Phòng ban',
            self::Staff => 'Nhân viên',
        };
    }

    /**
     * Top-level recipient types used for commission total calculation.
     * Management/Department/Staff are internal distributions and should not be double-counted.
     *
     * @return list<self>
     */
    public static function topLevel(): array
    {
        return [self::Platform, self::OperatingCompany, self::BoardOfDirectors, self::Management];
    }

    /**
     * Intermediary recipients are distribution buckets — the money flows
     * through them down to the next level (Management → Department → Staff).
     * They should never appear as "people being paid" in commission reports.
     */
    public function isIntermediary(): bool
    {
        return match ($this) {
            self::Management, self::Department => true,
            default => false,
        };
    }

    /**
     * Terminal recipients are the actual payees — money stops here.
     * Commission reports aggregate and list only terminal recipients.
     */
    public function isTerminal(): bool
    {
        return ! $this->isIntermediary();
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
