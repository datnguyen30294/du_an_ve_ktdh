<?php

namespace App\Modules\PMC\OgTicket\Models;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OgTicketLifecycleSegment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'og_ticket_id',
        'status',
        'cycle',
        'cycle_confirmed',
        'started_at',
        'ended_at',
        'note',
        'assignee_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => OgTicketStatus::class,
            'cycle' => 'integer',
            'cycle_confirmed' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OgTicket, $this>
     */
    public function ogTicket(): BelongsTo
    {
        return $this->belongsTo(OgTicket::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'assignee_id');
    }
}
