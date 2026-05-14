<?php

namespace App\Modules\PMC\WorkSchedule\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Shift\Models\Shift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSchedule extends BaseModel
{
    use HasFactory;

    protected $table = 'work_schedules';

    /** @var list<string> */
    protected $fillable = [
        'account_id',
        'project_id',
        'shift_id',
        'date',
        'note',
        'external_ref',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Shift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * @param  Builder<static>  $query
     * @param  list<int>  $accountIds
     * @return Builder<static>
     */
    public function scopeForAccounts(Builder $query, array $accountIds): Builder
    {
        return $query->whereIn('account_id', $accountIds);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Filter by `YYYY-MM` month string.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeInMonth(Builder $query, string $yearMonth): Builder
    {
        $from = $yearMonth.'-01';
        $to = date('Y-m-t', strtotime($from));

        return $query->whereBetween('date', [$from, $to]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    protected static function newFactory(): \Database\Factories\Tenant\WorkScheduleFactory
    {
        return \Database\Factories\Tenant\WorkScheduleFactory::new();
    }
}
