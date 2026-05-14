<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketReceivedByOrganization
{
    use Dispatchable, SerializesModels;

    /**
     * @param  int  $customerId  Customer ID in the central database.
     * @param  array{
     *   ticket_code: string,
     *   ticket_subject: string,
     *   organization_name: string|null,
     *   customer_name: string,
     *   tenant_subdomain: string|null
     * }  $payload
     */
    public function __construct(
        public int $customerId,
        public array $payload,
    ) {}
}
