<?php

namespace App\Modules\PMC\Quote\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case ManagerApproved = 'manager_approved';
    case Approved = 'approved';
    case ManagerRejected = 'manager_rejected';
    case ResidentRejected = 'resident_rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nháp',
            self::Sent => 'Đã gửi',
            self::ManagerApproved => 'QL đã duyệt',
            self::Approved => 'Đã chấp thuận',
            self::ManagerRejected => 'QL từ chối',
            self::ResidentRejected => 'Cư dân từ chối',
            self::Cancelled => 'Đã huỷ',
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
            self::Draft => [self::Sent],
            self::Sent => [self::ManagerApproved, self::ManagerRejected],
            self::ManagerApproved => [self::Approved, self::ResidentRejected],
            self::Approved, self::ManagerRejected, self::ResidentRejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return \in_array($target, $this->allowedTransitions(), true);
    }

    public function isRejected(): bool
    {
        return \in_array($this, [self::ManagerRejected, self::ResidentRejected], true);
    }

    /**
     * Statuses that are considered "transitionable" (can be sent to transition endpoint).
     *
     * @return list<string>
     */
    public static function transitionTargets(): array
    {
        return [
            self::Sent->value,
            self::ManagerApproved->value,
            self::Approved->value,
            self::ManagerRejected->value,
            self::ResidentRejected->value,
        ];
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
