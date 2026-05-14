<?php

namespace App\Modules\PMC\OgTicket\Models;

use App\Common\Models\BaseModel;
use App\Common\Traits\HasTenantAttachments;
use App\Modules\PMC\Account\Models\Account;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OgTicketSurvey extends BaseModel
{
    use HasTenantAttachments;

    /** @var list<string> */
    protected $fillable = [
        'og_ticket_id',
        'note',
        'surveyed_by',
        'surveyed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'surveyed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OgTicket, $this>
     */
    public function ogTicket(): BelongsTo
    {
        return $this->belongsTo(OgTicket::class, 'og_ticket_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'surveyed_by');
    }
}
