<?php

namespace App\Modules\PMC\OgTicket\Models;

use App\Common\Traits\HasTenantAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OgTicketWarrantyRequest extends Model
{
    use HasTenantAttachments;

    /** @var list<string> */
    protected $fillable = [
        'og_ticket_id',
        'requester_name',
        'subject',
        'description',
    ];

    /**
     * @return BelongsTo<OgTicket, $this>
     */
    public function ogTicket(): BelongsTo
    {
        return $this->belongsTo(OgTicket::class);
    }
}
