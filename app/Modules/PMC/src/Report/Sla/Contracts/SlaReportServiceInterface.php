<?php

namespace App\Modules\PMC\Report\Sla\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

interface SlaReportServiceInterface
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
    public function getTrend(array $filters): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getByProject(array $filters): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function getByStaff(array $filters): array;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getByTicket(array $filters): LengthAwarePaginator;
}
