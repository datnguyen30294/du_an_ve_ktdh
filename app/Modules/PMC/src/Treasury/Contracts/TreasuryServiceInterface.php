<?php

namespace App\Modules\PMC\Treasury\Contracts;

use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\Order\AdvancePayment\Models\AdvancePaymentRecord;
use App\Modules\PMC\Reconciliation\Models\FinancialReconciliation;
use App\Modules\PMC\Treasury\Models\CashAccount;
use App\Modules\PMC\Treasury\Models\CashTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface TreasuryServiceInterface
{
    // --- CashAccount ---

    /**
     * @return Collection<int, CashAccount>
     */
    public function listCashAccounts(): Collection;

    public function findCashAccountById(int $id): CashAccount;

    public function getDefaultCashAccount(): CashAccount;

    public function getCurrentBalance(CashAccount $account): float;

    // --- CashTransaction list/detail ---

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CashTransaction>
     */
    public function listTransactions(array $filters): LengthAwarePaginator;

    public function findTransactionById(int $id): CashTransaction;

    // --- Manual CRUD ---

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordManualTopup(array $data): CashTransaction;

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordManualWithdraw(array $data): CashTransaction;

    public function softDeleteManual(int $transactionId, string $reason): void;

    // --- Auto-sourced (called from listeners) ---

    public function recordFromReconciliation(FinancialReconciliation $reconciliation): ?CashTransaction;

    public function recordFromCommissionSnapshot(OrderCommissionSnapshot $snapshot): ?CashTransaction;

    public function recordFromAdvancePayment(AdvancePaymentRecord $record): ?CashTransaction;

    public function softDeleteFromReconciliationReset(FinancialReconciliation $reconciliation): void;

    public function softDeleteFromCommissionUnpaid(OrderCommissionSnapshot $snapshot): void;

    public function softDeleteFromAdvancePaymentDeleted(AdvancePaymentRecord $record): void;

    // --- Reporting ---

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters): array;
}
