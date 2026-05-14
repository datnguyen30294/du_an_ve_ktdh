<?php

namespace App\Modules\PMC\Treasury\Enums;

enum CashTransactionCategory: string
{
    case ManualTopup = 'manual_topup';
    case ManualWithdraw = 'manual_withdraw';
    case ReceivableCollection = 'receivable_collection';
    case CustomerRefund = 'customer_refund';
    case CommissionPayout = 'commission_payout';
    case AdvancePaymentPayout = 'advance_payment_payout';

    public function label(): string
    {
        return match ($this) {
            self::ManualTopup => 'Nạp tiền thủ công',
            self::ManualWithdraw => 'Rút tiền thủ công',
            self::ReceivableCollection => 'Thu công nợ',
            self::CustomerRefund => 'Hoàn tiền khách',
            self::CommissionPayout => 'Chi hoa hồng',
            self::AdvancePaymentPayout => 'Chi tiền ứng vật tư',
        };
    }

    /**
     * Derive fixed direction from category (category ↔ direction is a 1:1 mapping).
     */
    public function direction(): CashTransactionDirection
    {
        return match ($this) {
            self::ManualTopup, self::ReceivableCollection => CashTransactionDirection::Inflow,
            self::ManualWithdraw, self::CustomerRefund, self::CommissionPayout, self::AdvancePaymentPayout => CashTransactionDirection::Outflow,
        };
    }

    public function isManual(): bool
    {
        return match ($this) {
            self::ManualTopup, self::ManualWithdraw => true,
            default => false,
        };
    }

    public function isAutoSourced(): bool
    {
        return ! $this->isManual();
    }

    public function requiresReconciliation(): bool
    {
        return match ($this) {
            self::ReceivableCollection, self::CustomerRefund => true,
            default => false,
        };
    }

    public function requiresCommissionSnapshot(): bool
    {
        return $this === self::CommissionPayout;
    }

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
