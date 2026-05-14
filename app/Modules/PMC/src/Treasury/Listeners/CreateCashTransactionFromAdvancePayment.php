<?php

namespace App\Modules\PMC\Treasury\Listeners;

use App\Modules\PMC\Treasury\Contracts\TreasuryServiceInterface;
use App\Modules\PMC\Treasury\Events\AdvancePaymentRecorded;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class CreateCashTransactionFromAdvancePayment
{
    public function __construct(protected TreasuryServiceInterface $service) {}

    public function handle(AdvancePaymentRecorded $event): void
    {
        try {
            $this->service->recordFromAdvancePayment($event->record);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            Log::info('Treasury: skipped duplicate cash transaction for advance payment record', [
                'advance_payment_record_id' => $event->record->id,
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
