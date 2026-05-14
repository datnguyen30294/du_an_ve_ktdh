<?php

namespace App\Modules\PMC\Commission\Models;

use App\Modules\PMC\Commission\Enums\CommissionValueType;
use App\Modules\PMC\Department\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionDeptRule extends Model
{
    protected $fillable = [
        'config_id',
        'department_id',
        'sort_order',
        'value_type',
        'percent',
        'value_fixed',
    ];

    protected function casts(): array
    {
        return [
            'value_type' => CommissionValueType::class,
            'sort_order' => 'integer',
            'percent' => 'decimal:2',
            'value_fixed' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ProjectCommissionConfig, $this>
     */
    public function config(): BelongsTo
    {
        return $this->belongsTo(ProjectCommissionConfig::class, 'config_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<CommissionStaffRule, $this>
     */
    public function staffRules(): HasMany
    {
        return $this->hasMany(CommissionStaffRule::class, 'dept_rule_id');
    }
}
