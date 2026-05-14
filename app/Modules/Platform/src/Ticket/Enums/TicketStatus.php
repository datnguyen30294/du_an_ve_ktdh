<?php

namespace App\Modules\Platform\Ticket\Enums;

enum TicketStatus: string
{
    case Pending = 'pending';
    case Received = 'received';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xử lý',
            self::Received => 'Đã tiếp nhận',
            self::InProgress => 'Đang xử lý',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã hủy',
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
