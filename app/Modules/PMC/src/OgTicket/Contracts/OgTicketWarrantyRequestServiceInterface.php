<?php

namespace App\Modules\PMC\OgTicket\Contracts;

use App\Modules\PMC\OgTicket\Models\OgTicketWarrantyRequest;
use Illuminate\Database\Eloquent\Collection;

interface OgTicketWarrantyRequestServiceInterface
{
    /**
     * Create a warranty request for an OgTicket (snapshot requester name).
     *
     * @param  array{subject: string, description: string}  $data
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     */
    public function create(int $ogTicketId, string $requesterName, array $data, array $files): OgTicketWarrantyRequest;

    /**
     * List warranty requests for an OgTicket (with attachments loaded).
     *
     * @return Collection<int, OgTicketWarrantyRequest>
     */
    public function listByOgTicketId(int $ogTicketId): Collection;
}
