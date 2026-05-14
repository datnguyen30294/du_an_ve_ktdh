<?php

namespace App\Modules\PMC\Shift\Models;

use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Shift\Enums\ShiftStatusEnum;
use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'project_id',
        'code',
        'name',
        'type',
        'work_group',
        'start_time',
        'end_time',
        'break_hours',
        'status',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'break_hours' => 'decimal:2',
            'sort_order' => 'integer',
            'status' => ShiftStatusEnum::class,
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<WorkSchedule, $this>
     */
    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class);
    }

    /**
     * A shift is overnight when it wraps past midnight (end_time <= start_time).
     */
    public function isOvernight(): bool
    {
        return $this->normalizeTime((string) $this->end_time) <= $this->normalizeTime((string) $this->start_time);
    }

    /**
     * Compute the working hours of this shift, excluding the break.
     */
    public function getWorkHoursAttribute(): float
    {
        $start = $this->timeToSeconds((string) $this->start_time);
        $end = $this->timeToSeconds((string) $this->end_time);
        $break = (float) ($this->break_hours ?? 0);

        $duration = $this->isOvernight()
            ? (86400 - $start + $end) / 3600
            : ($end - $start) / 3600;

        $hours = $duration - $break;

        return max(0.0, round($hours, 2));
    }

    /**
     * Normalize a time string to HH:MM:SS so comparisons are stable.
     */
    protected function normalizeTime(string $value): string
    {
        if ($value === '') {
            return '00:00:00';
        }

        $suffix = substr($value, -8);

        return strlen($suffix) === 8 ? $suffix : $value;
    }

    protected function timeToSeconds(string $value): int
    {
        $normalized = $this->normalizeTime($value);
        [$h, $m, $s] = array_pad(explode(':', $normalized), 3, '0');

        return ((int) $h) * 3600 + ((int) $m) * 60 + (int) $s;
    }

    protected static function newFactory(): \Database\Factories\Tenant\ShiftFactory
    {
        return \Database\Factories\Tenant\ShiftFactory::new();
    }
}
