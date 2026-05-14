<?php

namespace App\Modules\PMC\Report\Overview\Contracts;

interface OverviewReportServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function getSummary(array $filters): array;
}
