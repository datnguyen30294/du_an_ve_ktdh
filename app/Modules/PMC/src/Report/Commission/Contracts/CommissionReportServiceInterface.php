<?php

namespace App\Modules\PMC\Report\Commission\Contracts;

interface CommissionReportServiceInterface
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
    public function getByStaff(array $filters): array;
}
