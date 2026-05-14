<?php

namespace App\Modules\PMC\Reconciliation\Contracts;

use App\Modules\PMC\Receivable\Models\PaymentReceipt;
use App\Modules\PMC\Receivable\Models\Receivable;
use App\Modules\PMC\Reconciliation\Models\FinancialReconciliation;
use App\Modules\PMC\Treasury\Models\CashTransaction;
use Illuminate\Pagination\LengthAwarePaginator;

interface ReconciliationServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): FinancialReconciliation;

    /**
     * @param  array<string, mixed>  $filters
     * @return array{total_count: int, pending_count: int, reconciled_count: int, rejected_count: int, pending_amount: string, reconciled_amount: string, rejected_amount: string}
     */
    public function summary(array $filters = []): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function reconcile(int $reconciliationId, array $data): FinancialReconciliation;

    /**
     * @param  array<string, mixed>  $data
     */
    public function reject(int $reconciliationId, array $data): FinancialReconciliation;

    /**
     * @param  array<string, mixed>  $data
     * @return array{reconciled_count: int, skipped_count: int}
     */
    public function batchReconcile(array $ids, array $data): array;

    /**
     * Auto-create reconciliation when a payment receipt is created.
     */
    public function createFromPaymentReceipt(Receivable $receivable, PaymentReceipt $paymentReceipt): FinancialReconciliation;

    /**
     * Reset reconciliation to pending when payment receipt is updated.
     */
    public function resetForPaymentReceipt(PaymentReceipt $paymentReceipt): void;

    /**
     * Auto-create a pending reconciliation record for a manual cash transaction
     * (topup/withdraw). The cash transaction already exists and already affects
     * the balance; the reconciliation is purely an audit gate.
     */
    public function createFromManualCashTransaction(CashTransaction $cashTransaction): FinancialReconciliation;
}
