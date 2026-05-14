<?php

namespace App\Modules\PMC\WorkSnapshot\Models;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\WorkSnapshot\Enums\SnapshotEntityTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkSlotSnapshot extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'account_id',
        'date',
        'shift_id',
        'entity_type',
        'entity_id',
        'snapshot_data',
        'captured_start_at',
        'finalized_at',
        'removed_mid_shift',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'entity_type' => SnapshotEntityTypeEnum::class,
            'snapshot_data' => 'array',
            'captured_start_at' => 'datetime',
            'finalized_at' => 'datetime',
            'removed_mid_shift' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
