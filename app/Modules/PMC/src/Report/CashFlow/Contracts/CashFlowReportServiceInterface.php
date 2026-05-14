<?php

namespace App\Modules\PMC\Report\CashFlow\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface CashFlowReportServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getDaily(array $filters): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, mixed>
     */
    public function getTransactions(array $filters): LengthAwarePaginator;
}
