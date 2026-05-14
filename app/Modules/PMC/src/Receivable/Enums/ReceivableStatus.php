<?php

namespace App\Modules\PMC\Receivable\Enums;

enum ReceivableStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overpaid = 'overpaid';
    case Overdue = 'overdue';
    case Completed = 'completed';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Chưa thu',
            self::Partial => 'Thu một phần',
            self::Paid => 'Đã thu đủ',
            self::Overpaid => 'Thu thừa',
            self::Overdue => 'Quá hạn',
            self::Completed => 'Hoàn thành',
            self::WrittenOff => 'Xóa nợ',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'neutral',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Overpaid => 'info',
            self::Overdue => 'error',
            self::Completed => 'success',
            self::WrittenOff => 'neutral',
        };
    }

    /**
     * Statuses that allow payment collection.
     *
     * @return list<self>
     */
    public static function payable(): array
    {
        return [self::Unpaid, self::Partial, self::Overdue];
    }

    /**
     * Statuses that allow refund.
     *
     * @return list<self>
     */
    public static function refundable(): array
    {
        return [self::Overpaid];
    }

    /**
     * Statuses that allow write-off.
     *
     * @return list<self>
     */
    public static function writableOff(): array
    {
        return [self::Unpaid, self::Partial, self::Overdue];
    }

    /**
     * Statuses that allow marking as completed.
     *
     * @return list<self>
     */
    public static function completable(): array
    {
        return [self::Paid];
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
