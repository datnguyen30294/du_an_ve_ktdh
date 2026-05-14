<?php

namespace App\Modules\PMC\Shift\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Shift\Models\Shift;
use Illuminate\Http\Request;

/**
 * @mixin Shift
 */
class ShiftResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     project_id: int,
     *     project: array{id: int, code: string|null, name: string}|null,
     *     code: string,
     *     name: string,
     *     type: string,
     *     work_group: string,
     *     start_time: string,
     *     end_time: string,
     *     is_overnight: bool,
     *     break_hours: float,
     *     work_hours: float,
     *     status: array{value: string, label: string},
     *     sort_order: int,
     *     created_at: string|null,
     *     updated_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var int */
            'project_id' => (int) $this->project_id,
            'project' => $this->whenLoaded('project', fn () => [
                'id' => (int) $this->project->id,
                'code' => $this->project->code ?? null,
                'name' => (string) $this->project->name,
            ]),
            'code' => (string) $this->code,
            'name' => (string) $this->name,
            'type' => (string) $this->type,
            'work_group' => (string) $this->work_group,
            'start_time' => $this->formatTime((string) $this->start_time),
            'end_time' => $this->formatTime((string) $this->end_time),
            'is_overnight' => $this->isOvernight(),
            /** @var float */
            'break_hours' => (float) $this->break_hours,
            /** @var float */
            'work_hours' => $this->work_hours,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            /** @var int */
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function formatTime(string $value): string
    {
        if ($value === '') {
            return '00:00';
        }

        $tail = substr($value, -8);
        $time = strlen($tail) === 8 ? $tail : $value;

        return substr($time, 0, 5);
    }
}
