<?php

namespace App\Modules\PMC\Commission\Models;

use App\Modules\PMC\Commission\Enums\CommissionPartyType;
use App\Modules\PMC\Commission\Enums\CommissionValueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionPartyRule extends Model
{
    protected $fillable = [
        'config_id',
        'party_type',
        'value_type',
        'percent',
        'value_fixed',
    ];

    protected function casts(): array
    {
        return [
            'party_type' => CommissionPartyType::class,
            'value_type' => CommissionValueType::class,
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
}
