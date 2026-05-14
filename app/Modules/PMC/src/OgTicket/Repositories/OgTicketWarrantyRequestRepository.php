<?php

namespace App\Modules\PMC\OgTicket\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\OgTicket\Models\OgTicketWarrantyRequest;
use Illuminate\Database\Eloquent\Collection;

class OgTicketWarrantyRequestRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new OgTicketWarrantyRequest);
    }

    /**
     * @return Collection<int, OgTicketWarrantyRequest>
     */
    public function listByOgTicketId(int $ogTicketId): Collection
    {
        return $this->newQuery()
            ->with('attachments')
            ->where('og_ticket_id', $ogTicketId)
            ->oldest()
            ->get();
    }
}
