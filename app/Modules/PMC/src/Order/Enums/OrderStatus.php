<?php

namespace App\Modules\PMC\Order\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Accepted = 'accepted';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Confirmed => 'Đã xác nhận',
            self::InProgress => 'Đang thực hiện',
            self::Accepted => 'Đã nghiệm thu',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã hủy',
        };
    }

    /**
     * Valid next statuses from current status.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Accepted, self::Cancelled],
            self::Accepted => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return \in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Statuses that are valid transition targets (used in validation).
     *
     * @return list<string>
     */
    public static function transitionTargets(): array
    {
        return [
            self::Confirmed->value,
            self::InProgress->value,
            self::Accepted->value,
            self::Completed->value,
            self::Cancelled->value,
        ];
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
