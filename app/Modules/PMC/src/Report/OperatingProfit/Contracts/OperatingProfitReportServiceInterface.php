<?php

namespace App\Modules\PMC\Report\OperatingProfit\Contracts;

interface OperatingProfitReportServiceInterface
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
    public function getMonthly(array $filters): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getByProject(array $filters): array;
}
