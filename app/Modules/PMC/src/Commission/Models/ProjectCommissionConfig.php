<?php

namespace App\Modules\PMC\Commission\Models;

use App\Modules\PMC\Commission\Enums\CommissionPartyType;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectCommissionConfig extends Model
{
    protected $fillable = [
        'project_id',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<CommissionPartyRule, $this>
     */
    public function partyRules(): HasMany
    {
        return $this->hasMany(CommissionPartyRule::class, 'config_id');
    }

    /**
     * Party rules ordered by fixed deduction priority.
     *
     * @return HasMany<CommissionPartyRule, $this>
     */
    public function partyRulesOrdered(): HasMany
    {
        return $this->partyRules()
            ->orderByRaw('CASE party_type
                WHEN ? THEN 2
                WHEN ? THEN 3
                WHEN ? THEN 4
                END', [
                CommissionPartyType::OperatingCompany->value,
                CommissionPartyType::BoardOfDirectors->value,
                CommissionPartyType::Management->value,
            ]);
    }

    /**
     * @return HasMany<CommissionDeptRule, $this>
     */
    public function deptRules(): HasMany
    {
        return $this->hasMany(CommissionDeptRule::class, 'config_id');
    }
}
