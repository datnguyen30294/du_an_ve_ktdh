<?php

namespace App\Modules\PMC\ClosingPeriod\Models;

use App\Common\Models\BaseModel;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\ClosingPeriod\Enums\ClosingPeriodStatus;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ClosingPeriod extends BaseModel implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    /** @var list<string> */
    protected $fillable = [
        'project_id',
        'name',
        'period_start',
        'period_end',
        'status',
        'closed_at',
        'closed_by_id',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClosingPeriodStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'closed_by_id');
    }

    /**
     * @return HasMany<ClosingPeriodOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(ClosingPeriodOrder::class, 'closing_period_id');
    }

    /**
     * @return HasMany<OrderCommissionSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(OrderCommissionSnapshot::class, 'closing_period_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        return $query->where('name', static::likeOperator(), "%{$keyword}%");
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByStatus(Builder $query, ClosingPeriodStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeByProject(Builder $query, ?int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    protected static function newFactory(): \Database\Factories\Tenant\ClosingPeriodFactory
    {
        return \Database\Factories\Tenant\ClosingPeriodFactory::new();
    }
}
