<?php

namespace App\Modules\PMC\Treasury\Listeners;

use App\Modules\PMC\Treasury\Contracts\TreasuryServiceInterface;
use App\Modules\PMC\Treasury\Events\FinancialReconciliationApproved;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class CreateCashTransactionFromReconciliation
{
    public function __construct(protected TreasuryServiceInterface $service) {}

    public function handle(FinancialReconciliationApproved $event): void
    {
        $reconciliation = $event->reconciliation->loadMissing([
            'paymentReceipt',
            'receivable:id,order_id',
        ]);

        try {
            $this->service->recordFromReconciliation($reconciliation);
        } catch (QueryException $exception) {
            // Unique-violation races are expected (partial unique index). Anything
            // else should surface so we can investigate.
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            Log::info('Treasury: skipped duplicate cash transaction for reconciliation', [
                'reconciliation_id' => $reconciliation->id,
            ]);
        }
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        if ($sqlState === '23505' || $sqlState === '23000') {
            return true;
        }

        return str_contains(strtolower($exception->getMessage()), 'unique constraint');
    }
}
