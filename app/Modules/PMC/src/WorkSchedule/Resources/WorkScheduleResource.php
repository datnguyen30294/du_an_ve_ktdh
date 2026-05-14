<?php

namespace App\Modules\PMC\WorkSchedule\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use Illuminate\Http\Request;

/**
 * @mixin WorkSchedule
 */
class WorkScheduleResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     date: string|null,
     *     account: array{id: int, employee_code: string|null, name: string|null}|null,
     *     project: array{id: int, code: string|null, name: string|null}|null,
     *     shift: array{id: int, code: string|null, name: string|null, start_time: string|null, end_time: string|null}|null,
     *     note: string|null,
     *     external_ref: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            'date' => $this->date?->format('Y-m-d'),
            'account' => $this->whenLoaded('account', fn () => [
                /** @var int */
                'id' => $this->account->id,
                'employee_code' => $this->account->employee_code,
                'name' => $this->account->name,
            ]),
            'project' => $this->whenLoaded('project', fn () => [
                /** @var int */
                'id' => $this->project->id,
                'code' => $this->project->code,
                'name' => $this->project->name,
            ]),
            'shift' => $this->whenLoaded('shift', fn () => [
                /** @var int */
                'id' => $this->shift->id,
                'code' => $this->shift->code,
                'name' => $this->shift->name,
                'start_time' => $this->formatTime((string) $this->shift->start_time),
                'end_time' => $this->formatTime((string) $this->shift->end_time),
            ]),
            'note' => $this->note,
            'external_ref' => $this->external_ref,
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
