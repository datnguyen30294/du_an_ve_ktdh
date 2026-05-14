<?php

namespace App\Modules\PMC\Commission\Models;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionAdjuster extends Model
{
    protected $fillable = [
        'project_id',
        'account_id',
    ];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
