<?php

namespace App\Modules\PMC\Commission\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Commission\Models\ProjectCommissionConfig;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Http\Request;

/**
 * Commission config detail resource.
 * Wraps project + config + dept rules + adjusters.
 */
class CommissionConfigDetailResource extends BaseResource
{
    public function __construct(
        private Project $project,
        private ?ProjectCommissionConfig $config,
        private ?\Illuminate\Database\Eloquent\Collection $adjusters = null,
    ) {
        parent::__construct($project);
    }

    /**
     * @return array{
     *     project: array{id: int, code: string, name: string},
     *     platform: array{percent: float, value_fixed: float, source: string},
     *     party_rules: list<array{id: int, party_type: array{value: string, label: string}, value_type: array{value: string, label: string}, percent: string|null, value_fixed: string|null}>,
     *     dept_rules: list<array{id: int, department: array{id: int, name: string}, sort_order: int, value_type: array{value: string, label: string}, percent: string|null, value_fixed: string|null, staff_rules: list<array{id: int, account: array{id: int, name: string, employee_code: string|null}, sort_order: int, value_type: array{value: string, label: string}, percent: string|null, value_fixed: string|null}>}>,
     *     adjusters: list<array{id: int, account: array{id: int, name: string, employee_code: string|null}}>,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var array{id: int, code: string, name: string} */
            'project' => [
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ],
            /** @var array{percent: float, value_fixed: float, source: string} */
            'platform' => [
                'percent' => (float) config('commission.platform_default_percent', 5),
                'value_fixed' => (float) config('commission.platform_default_fixed', 1000),
                'source' => 'fallback',
            ],
            /** @var list<array{id: int, party_type: array{value: string, label: string}, value_type: array{value: string, label: string}, percent: string|null, value_fixed: string|null}> */
            'party_rules' => $this->config
                ? CommissionPartyRuleResource::collection($this->config->partyRulesOrdered)->resolve()
                : [],
            /** @var list<array{id: int, department: array{id: int, name: string}, sort_order: int, value_type: array{value: string, label: string}, percent: string|null, value_fixed: string|null, staff_rules: list<array{id: int, account: array{id: int, name: string, employee_code: string|null}, sort_order: int, value_type: array{value: string, label: string}, percent: string|null, value_fixed: string|null}>}> */
            'dept_rules' => $this->config
                ? CommissionDeptRuleResource::collection($this->config->deptRules)->resolve()
                : [],
            /** @var list<array{id: int, account: array{id: int, name: string, employee_code: string|null}}> */
            'adjusters' => $this->adjusters
                ? CommissionAdjusterResource::collection($this->adjusters)->resolve()
                : [],
        ];
    }
}
