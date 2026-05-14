<?php

namespace App\Modules\PMC\Report\Csat\Contracts;

interface CsatReportServiceInterface
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
}
