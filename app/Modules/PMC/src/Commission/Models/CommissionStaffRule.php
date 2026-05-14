<?php

namespace App\Modules\PMC\Commission\Models;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Commission\Enums\CommissionValueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionStaffRule extends Model
{
    protected $fillable = [
        'dept_rule_id',
        'account_id',
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
     * @return BelongsTo<CommissionDeptRule, $this>
     */
    public function deptRule(): BelongsTo
    {
        return $this->belongsTo(CommissionDeptRule::class, 'dept_rule_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
