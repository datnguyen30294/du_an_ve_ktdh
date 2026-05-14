<?php

namespace App\Modules\PMC\OgTicket\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\OgTicket\Models\OgTicketSurvey;

class OgTicketSurveyRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new OgTicketSurvey);
    }

    public function findByOgTicketId(int $ogTicketId): ?OgTicketSurvey
    {
        /** @var OgTicketSurvey|null */
        return $this->newQuery()
            ->with(['attachments', 'surveyor'])
            ->where('og_ticket_id', $ogTicketId)
            ->first();
    }
}
