<?php

namespace App\Modules\PMC\OgTicket\Enums;

enum OgTicketStatus: string
{
    case Received = 'received';
    case Assigned = 'assigned';
    case Surveying = 'surveying';
    case Quoted = 'quoted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Ordered = 'ordered';
    case InProgress = 'in_progress';
    case Accepted = 'accepted';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Đã tiếp nhận',
            self::Assigned => 'Đã phân công',
            self::Surveying => 'Đang khảo sát',
            self::Quoted => 'Đã báo giá',
            self::Approved => 'Đã chấp thuận',
            self::Rejected => 'Từ chối',
            self::Ordered => 'Đã lên đơn',
            self::InProgress => 'Đang thực hiện',
            self::Accepted => 'Đã nghiệm thu',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã hủy',
        };
    }

    /**
     * Thứ tự workflow (happy path). Dùng để xác định backtrack khi transition.
     */
    public function workflowIndex(): int
    {
        return match ($this) {
            self::Received => 0,
            self::Assigned => 1,
            self::Surveying => 2,
            self::Quoted => 3,
            self::Approved => 4,
            self::Ordered => 5,
            self::InProgress => 6,
            self::Accepted => 7,
            self::Completed => 8,
            self::Rejected => 4,
            self::Cancelled => -1,
        };
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
