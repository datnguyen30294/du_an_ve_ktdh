<?php

namespace App\Modules\PMC\Report\Overview\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class OverviewSummaryResource extends BaseResource
{
    /**
     * @return array{
     *     period_label: string,
     *     sla: array{on_time_rate: float, breached_count: int}|null,
     *     revenue: array{revenue: string, margin_percent: float}|null,
     *     csat: array{avg_score: float, max_score: int, response_rate: float}|null,
     *     commission: array{
     *         party_totals: array{
     *             operating_company: string,
     *             board_of_directors: string,
     *             management: string,
     *             platform: string,
     *         },
     *         total_all_parties: string,
     *     }|null,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'period_label' => (string) ($data['period_label'] ?? ''),
            'sla' => $this->formatSla($data['sla'] ?? null),
            'revenue' => $this->formatRevenue($data['revenue'] ?? null),
            'csat' => $this->formatCsat($data['csat'] ?? null),
            'commission' => $this->formatCommission($data['commission'] ?? null),
        ];
    }

    /**
     * @param  mixed  $sla
     * @return array{on_time_rate: float, breached_count: int}|null
     */
    private function formatSla($sla): ?array
    {
        if (! is_array($sla)) {
            return null;
        }

        return [
            'on_time_rate' => (float) ($sla['on_time_rate'] ?? 0),
            'breached_count' => (int) ($sla['breached_count'] ?? 0),
        ];
    }

    /**
     * @param  mixed  $revenue
     * @return array{revenue: string, margin_percent: float}|null
     */
    private function formatRevenue($revenue): ?array
    {
        if (! is_array($revenue)) {
            return null;
        }

        return [
            'revenue' => (string) ($revenue['revenue'] ?? '0.00'),
            'margin_percent' => (float) ($revenue['margin_percent'] ?? 0),
        ];
    }

    /**
     * @param  mixed  $csat
     * @return array{avg_score: float, max_score: int, response_rate: float}|null
     */
    private function formatCsat($csat): ?array
    {
        if (! is_array($csat)) {
            return null;
        }

        return [
            'avg_score' => (float) ($csat['avg_score'] ?? 0),
            'max_score' => (int) ($csat['max_score'] ?? 5),
            'response_rate' => (float) ($csat['response_rate'] ?? 0),
        ];
    }

    /**
     * @param  mixed  $commission
     * @return array{
     *     party_totals: array{operating_company: string, board_of_directors: string, management: string, platform: string},
     *     total_all_parties: string,
     * }|null
     */
    private function formatCommission($commission): ?array
    {
        if (! is_array($commission)) {
            return null;
        }

        /** @var array<string, mixed> $party */
        $party = $commission['party_totals'] ?? [];

        return [
            'party_totals' => [
                'operating_company' => (string) ($party['operating_company'] ?? '0.00'),
                'board_of_directors' => (string) ($party['board_of_directors'] ?? '0.00'),
                'management' => (string) ($party['management'] ?? '0.00'),
                'platform' => (string) ($party['platform'] ?? '0.00'),
            ],
            'total_all_parties' => (string) ($commission['total_all_parties'] ?? '0.00'),
        ];
    }
}
