<?php

namespace App\Modules\PMC\Treasury\Support;

use App\Modules\PMC\Treasury\Enums\CashTransactionDirection;
use App\Modules\PMC\Treasury\Repositories\CashTransactionRepository;
use Carbon\CarbonInterface;

class CashTransactionCodeGenerator
{
    private const PREFIX_INFLOW = 'PT';

    private const PREFIX_OUTFLOW = 'PC';

    public function __construct(protected CashTransactionRepository $repository) {}

    /**
     * Generate the next cash transaction code for the given direction and date.
     *
     * Format: {prefix}-{year}-{counter:04d}
     *   - PT → inflow (phiếu thu)
     *   - PC → outflow (phiếu chi)
     *
     * Counter is derived from the highest existing counter (including soft-deleted
     * rows) for the same prefix/year pair and reset yearly.
     */
    public function generate(CashTransactionDirection $direction, CarbonInterface $date): string
    {
        $prefix = $direction === CashTransactionDirection::Inflow
            ? self::PREFIX_INFLOW
            : self::PREFIX_OUTFLOW;

        $year = $date->year;
        $lastCounter = $this->repository->getLastCodeCounterForYear($prefix, $year);
        $nextCounter = $lastCounter + 1;

        return sprintf('%s-%d-%04d', $prefix, $year, $nextCounter);
    }
}
