<?php

namespace App\Modules\PMC\Report\Commission\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class CommissionSummaryResource extends BaseResource
{
    /**
     * @return array{
     *     period_label: string,
     *     party_totals: array{
     *         operating_company: string,
     *         board_of_directors: string,
     *         management: string,
     *         platform: string,
     *     },
     *     estimated_gross_profit: string,
     *     platform_rules: array{percent: float, fixed_per_order: float},
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        /** @var array<string, string> $party */
        $party = $data['party_totals'];

        /** @var array<string, float> $rules */
        $rules = $data['platform_rules'];

        return [
            'period_label' => (string) $data['period_label'],
            'party_totals' => [
                'operating_company' => (string) $party['operating_company'],
                'board_of_directors' => (string) $party['board_of_directors'],
                'management' => (string) $party['management'],
                'platform' => (string) $party['platform'],
            ],
            'estimated_gross_profit' => (string) $data['estimated_gross_profit'],
            'platform_rules' => [
                'percent' => (float) $rules['percent'],
                'fixed_per_order' => (float) $rules['fixed_per_order'],
            ],
        ];
    }
}
