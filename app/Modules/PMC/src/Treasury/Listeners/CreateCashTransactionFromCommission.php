<?php

namespace App\Modules\PMC\Treasury\Listeners;

use App\Modules\PMC\Treasury\Contracts\TreasuryServiceInterface;
use App\Modules\PMC\Treasury\Events\CommissionSnapshotPaid;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class CreateCashTransactionFromCommission
{
    public function __construct(protected TreasuryServiceInterface $service) {}

    public function handle(CommissionSnapshotPaid $event): void
    {
        try {
            $this->service->recordFromCommissionSnapshot($event->snapshot);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            Log::info('Treasury: skipped duplicate cash transaction for commission snapshot', [
                'snapshot_id' => $event->snapshot->id,
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
