<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuoteCreatedForTicket
{
    use Dispatchable, SerializesModels;

    /**
     * @param  int  $customerId  Customer ID in the central database.
     * @param  array{
     *   ticket_code: string,
     *   ticket_subject: string,
     *   quote_code: string,
     *   quote_total_amount: float,
     *   quote_lines: array<int, array{name: string, quantity: int, unit: string|null, line_amount: float}>,
     *   customer_name: string,
     *   tenant_subdomain: string|null
     * }  $payload
     */
    public function __construct(
        public int $customerId,
        public array $payload,
    ) {}
}
